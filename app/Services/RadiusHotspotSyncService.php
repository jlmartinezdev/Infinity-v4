<?php

namespace App\Services;

use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RadiusHotspotSyncService
{
    public const BYTES_POR_GB = 1073741824;

    public const BYTES_POR_MB = 1048576;

    public const GIGAWORD = 4294967296;

    protected ?string $exprOctetos = null;

    protected ?bool $corteDisponible = null;

    public function enabled(): bool
    {
        return (bool) config('radius.enabled');
    }

    public function connection(): string
    {
        return (string) config('radius.connection', 'radius');
    }

    /**
     * @return array{success: bool, skipped?: bool, error?: string}
     */
    public function sync(ServicioHotspot $sh, bool $incluirSesionViva = true): array
    {
        if (! $this->enabled()) {
            return ['success' => true, 'skipped' => true];
        }

        if (! $this->conexionAlcanzable()) {
            return ['success' => false, 'error' => 'No hay conexión a la base RADIUS.'];
        }

        try {
            $sh->loadMissing(['servicio', 'hotspotPerfil', 'router']);
            $this->syncNas($sh->router);
            $this->escribirUsuario($sh, $incluirSesionViva);
            $sh->update(['last_synced' => now()]);

            return ['success' => true];
        } catch (Throwable $e) {
            Log::error('[RADIUS] sync hotspot', [
                'servicio_hotspot_id' => $sh->getKey(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, skipped?: bool, error?: string}
     */
    public function forget(string $username): array
    {
        if (! $this->enabled()) {
            return ['success' => true, 'skipped' => true];
        }

        $username = trim($username);
        if ($username === '') {
            return ['success' => true];
        }

        if (! $this->conexionAlcanzable()) {
            return ['success' => false, 'error' => 'No hay conexión a la base RADIUS.'];
        }

        try {
            $db = DB::connection($this->connection());
            foreach (['radcheck', 'radreply', 'radusergroup'] as $tabla) {
                $db->table($tabla)->where('username', $username)->delete();
            }

            return ['success' => true];
        } catch (Throwable $e) {
            Log::error('[RADIUS] forget hotspot', ['username' => $username, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Usuarios tal como están en radcheck / radreply (y sesión abierta en radacct).
     *
     * @return array{ok: bool, error?: string, usuarios: list<array<string, mixed>>}
     */
    public function listarUsuarios(?string $buscar = null): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'RADIUS_ENABLED no está activo.', 'usuarios' => []];
        }

        if (! $this->conexionAlcanzable()) {
            return ['ok' => false, 'error' => 'No hay conexión a la base RADIUS.', 'usuarios' => []];
        }

        try {
            $db = DB::connection($this->connection());
            $buscar = trim((string) $buscar);

            $check = $db->table('radcheck')
                ->when($buscar !== '', fn ($q) => $q->where('username', 'like', '%'.$buscar.'%'))
                ->orderBy('username')
                ->get(['username', 'attribute', 'op', 'value']);

            $usernames = $check->pluck('username')->unique()->filter()->values();
            $reply = $usernames->isEmpty()
                ? collect()
                : $db->table('radreply')->whereIn('username', $usernames->all())->get(['username', 'attribute', 'value']);

            $online = [];
            if ($usernames->isNotEmpty() && $db->getSchemaBuilder()->hasTable('radacct')) {
                $online = $db->table('radacct')
                    ->whereIn('username', $usernames->all())
                    ->whereNull('acctstoptime')
                    ->orderByDesc('acctstarttime')
                    ->get(['username', 'nasipaddress', 'callingstationid', 'acctstarttime', 'framedipaddress'])
                    ->groupBy('username');
            }

            $porUsuario = [];
            foreach ($check as $fila) {
                $user = (string) $fila->username;
                if (! isset($porUsuario[$user])) {
                    $porUsuario[$user] = [
                        'username' => $user,
                        'password' => null,
                        'rechazado' => false,
                        'simultaneous' => null,
                        'rate_limit' => null,
                        'cuota' => null,
                        'idle_timeout' => null,
                        'session_timeout' => null,
                        'sesiones' => [],
                    ];
                }
                if ($fila->attribute === 'Cleartext-Password') {
                    $porUsuario[$user]['password'] = (string) $fila->value;
                }
                if ($fila->attribute === 'Auth-Type' && strcasecmp((string) $fila->value, 'Reject') === 0) {
                    $porUsuario[$user]['rechazado'] = true;
                }
                if ($fila->attribute === 'Simultaneous-Use') {
                    $porUsuario[$user]['simultaneous'] = (string) $fila->value;
                }
                if ($fila->attribute === 'Max-Data') {
                    $porUsuario[$user]['cuota'] = self::bytesAEtiqueta((int) $fila->value);
                }
            }

            foreach ($reply as $fila) {
                $user = (string) $fila->username;
                if (! isset($porUsuario[$user])) {
                    continue;
                }
                if ($fila->attribute === 'Mikrotik-Rate-Limit') {
                    $porUsuario[$user]['rate_limit'] = (string) $fila->value;
                }
                if ($fila->attribute === 'Mikrotik-Total-Limit' && empty($porUsuario[$user]['cuota'])) {
                    $porUsuario[$user]['cuota'] = self::bytesAEtiqueta((int) $fila->value);
                }
                if ($fila->attribute === 'Idle-Timeout') {
                    $porUsuario[$user]['idle_timeout'] = (string) $fila->value;
                }
                if ($fila->attribute === 'Session-Timeout') {
                    $porUsuario[$user]['session_timeout'] = (string) $fila->value;
                }
            }

            foreach ($porUsuario as $user => $fila) {
                $sesiones = $online[$user] ?? collect();
                $porUsuario[$user]['sesiones'] = $sesiones->map(fn ($s) => [
                    'nas' => $s->nasipaddress,
                    'mac' => $s->callingstationid,
                    'ip' => $s->framedipaddress ?? null,
                    'desde' => $s->acctstarttime,
                ])->values()->all();
            }

            return ['ok' => true, 'usuarios' => array_values($porUsuario)];
        } catch (Throwable $e) {
            Log::error('[RADIUS] listar usuarios', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage(), 'usuarios' => []];
        }
    }

    public function operativo(): bool
    {
        return $this->enabled() && $this->conexionAlcanzable();
    }

    protected function conexionAlcanzable(): bool
    {
        $cfg = config('database.connections.'.$this->connection(), []);
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);
        $fp = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($fp === false) {
            Log::warning('[RADIUS] DB inalcanzable', [
                'host' => $host,
                'port' => $port,
                'error' => $errstr !== '' ? $errstr : (string) $errno,
            ]);

            return false;
        }
        fclose($fp);

        return true;
    }

    public function syncNas(?Router $router): void
    {
        if (! $this->enabled() || ! $router || trim((string) $router->ip) === '') {
            return;
        }

        $db = DB::connection($this->connection());
        $nasname = trim((string) $router->ip);
        $payload = [
            'nasname' => $nasname,
            'shortname' => mb_substr((string) ($router->nombre ?: $nasname), 0, 32),
            'type' => 'other',
            'secret' => (string) config('radius.secret'),
            'description' => (string) ($router->nombre ?: 'MikroTik'),
        ];

        $existente = $db->table('nas')->where('nasname', $nasname)->first();
        if ($existente) {
            $db->table('nas')->where('id', $existente->id)->update($payload);

            return;
        }

        $db->table('nas')->insert($payload);
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function atributosCheck(ServicioHotspot $sh, int $bytesUsados = 0): array
    {
        $sh->loadMissing(['servicio', 'hotspotPerfil']);
        $check = [
            ['Cleartext-Password', ':=', (string) $sh->password],
        ];

        if (! $this->servicioPermiteAcceso($sh->servicio) || $this->cuotaAgotada($sh, $bytesUsados)) {
            $check[] = ['Auth-Type', ':=', 'Reject'];
        }

        $shared = trim((string) ($sh->hotspotPerfil?->shared_users ?? ''));
        if ($shared !== '' && ctype_digit($shared) && (int) $shared > 0) {
            $check[] = ['Simultaneous-Use', ':=', $shared];
        }

        $cuotaBytes = self::cuotaGbABytes($sh->hotspotPerfil?->cuota_gb);
        if ($cuotaBytes !== null) {
            $check[] = ['Max-Data', ':=', (string) $cuotaBytes];
        }

        return $check;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function atributosReply(ServicioHotspot $sh, int $bytesUsados = 0): array
    {
        $sh->loadMissing('hotspotPerfil');
        $perfil = $sh->hotspotPerfil;
        $reply = [];
        $reply[] = ['Acct-Interim-Interval', '=', '60'];

        $rate = trim((string) ($perfil?->rate_limit ?? ''));
        if ($rate !== '') {
            $reply[] = ['Mikrotik-Rate-Limit', '=', $rate];
        }

        $idle = self::timeoutASegundos($perfil?->idle_timeout);
        if ($idle !== null) {
            $reply[] = ['Idle-Timeout', '=', (string) $idle];
        }

        $session = self::timeoutASegundos($perfil?->session_timeout);
        if ($session !== null) {
            $reply[] = ['Session-Timeout', '=', (string) $session];
        }

        $cuotaBytes = self::cuotaGbABytes($perfil?->cuota_gb);
        if ($cuotaBytes !== null && ! $this->cuotaAgotada($sh, $bytesUsados)) {
            $restante = max(0, $cuotaBytes - max(0, $bytesUsados));
            foreach (self::mikrotikAtributosVolumen($restante) as $fila) {
                $reply[] = $fila;
            }
        }

        return $reply;
    }

    public static function cuotaGbABytes(mixed $gb): ?int
    {
        if ($gb === null || $gb === '') {
            return null;
        }
        $gb = (int) $gb;
        if ($gb <= 0) {
            return null;
        }

        return $gb * self::BYTES_POR_GB;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function mikrotikAtributosVolumen(int $bytes): array
    {
        $bytes = max(0, $bytes);
        $gigawords = intdiv($bytes, self::GIGAWORD);
        $limite = $bytes % self::GIGAWORD;

        return [
            ['Mikrotik-Total-Limit', '=', (string) $limite],
            ['Mikrotik-Total-Limit-Gigawords', '=', (string) $gigawords],
        ];
    }

    public static function bytesAEtiqueta(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 GB';
        }
        $gb = $bytes / self::BYTES_POR_GB;
        if (abs($gb - round($gb)) < 0.05) {
            return ((int) round($gb)).' GB';
        }

        return rtrim(rtrim(number_format($gb, 2, '.', ''), '0'), '.').' GB';
    }

    public static function bytesAMb(int $bytes): int
    {
        return (int) round(max(0, $bytes) / self::BYTES_POR_MB);
    }

    public static function consumoEtiqueta(int $bytesUsados, mixed $cuotaGb): string
    {
        return self::consumoVista($bytesUsados, $cuotaGb)['etiqueta'];
    }

    /**
     * @return array{etiqueta: string, porcentaje: float|null, centro_num: string, centro_unit: string, tiene_cuota: bool}
     */
    public static function consumoVista(int $bytesUsados, mixed $cuotaGb): array
    {
        $cuotaBytes = self::cuotaGbABytes($cuotaGb);
        $mb = self::bytesAMb($bytesUsados);
        if ($mb >= 1024) {
            $gb = $mb / 1024;
            $centroNum = abs($gb - round($gb)) < 0.05
                ? (string) ((int) round($gb))
                : number_format($gb, 1, ',', '');
            $centroUnit = 'GB';
        } else {
            $centroNum = number_format($mb, 0, ',', '.');
            $centroUnit = 'MB';
        }

        $usado = $centroNum.' '.$centroUnit;
        $etiqueta = $cuotaBytes === null
            ? $usado.' · ilimitado'
            : $usado.' / '.(int) $cuotaGb.' GB';

        $porcentaje = null;
        if ($cuotaBytes !== null) {
            $porcentaje = min(100.0, round(max(0, $bytesUsados) / $cuotaBytes * 100, 1));
        }

        return [
            'etiqueta' => $etiqueta,
            'porcentaje' => $porcentaje,
            'centro_num' => $centroNum,
            'centro_unit' => $centroUnit,
            'tiene_cuota' => $cuotaBytes !== null,
        ];
    }

    public static function timeoutASegundos(?string $valor): ?int
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strcasecmp($valor, 'none') === 0) {
            return null;
        }
        if (ctype_digit($valor)) {
            return max(0, (int) $valor) ?: null;
        }
        if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $valor, $m)) {
            $seg = ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];

            return $seg > 0 ? $seg : null;
        }
        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/i', $valor, $m)) {
            $horas = (int) ($m[1] ?? 0);
            $minutos = (int) ($m[2] ?? 0);
            $segundos = (int) ($m[3] ?? 0);
            $seg = $horas * 3600 + $minutos * 60 + $segundos;

            return $seg > 0 ? $seg : null;
        }

        return null;
    }

    protected function servicioPermiteAcceso(?Servicio $servicio): bool
    {
        return $servicio?->estado === Servicio::ESTADO_ACTIVO;
    }

    protected function escribirUsuario(ServicioHotspot $sh, bool $incluirSesionViva = true): void
    {
        $username = trim((string) $sh->username);
        if ($username === '') {
            throw new \InvalidArgumentException('El usuario hotspot no tiene username.');
        }

        $usados = $this->bytesUsados($username);
        if ($incluirSesionViva) {
            try {
                $vivo = app(MikroTikService::class)->bytesHotspotActivosPorUsuarios([$username]);
                if ($vivo !== []) {
                    $usados = self::combinarConsumoSesionViva(
                        $this->bytesRadacctPorUsuarios([$username]),
                        $vivo
                    )[$username] ?? $usados;
                }
            } catch (Throwable $e) {
                Log::warning('[RADIUS] no se pudo sumar consumo vivo al sync', [
                    'username' => $username,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $this->reemplazarAtributos('radcheck', $username, $this->atributosCheck($sh, $usados));
        $this->reemplazarAtributos('radreply', $username, $this->atributosReply($sh, $usados));
    }

    protected function cuotaAgotada(ServicioHotspot $sh, int $bytesUsados = 0): bool
    {
        $cuotaBytes = self::cuotaGbABytes($sh->hotspotPerfil?->cuota_gb);
        if ($cuotaBytes === null) {
            return false;
        }

        return max(0, $bytesUsados) >= $cuotaBytes;
    }

    public function bytesUsados(string $username): int
    {
        $username = trim($username);
        if ($username === '') {
            return 0;
        }

        try {
            $db = DB::connection($this->connection());
            if (! $db->getSchemaBuilder()->hasTable('radacct')) {
                return 0;
            }

            $expr = $this->expresionOctetos();
            $resta = $this->tieneCorteCuota()
                ? ' - COALESCE((SELECT bytes_base FROM hotspot_cuota_corte WHERE username = ? LIMIT 1), 0)'
                : '';
            $params = $resta !== '' ? [$username, $username] : [$username];
            $fila = $db->selectOne(
                "SELECT GREATEST(0, COALESCE(SUM({$expr}), 0){$resta}) AS usados FROM radacct WHERE username = ?",
                $params
            );

            return max(0, (int) ($fila->usados ?? 0));
        } catch (Throwable $e) {
            Log::warning('[RADIUS] no se pudo leer consumo', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param  list<string>  $usernames
     * @return array<string, int>
     */
    public function bytesUsadosPorUsuarios(array $usernames): array
    {
        $out = [];
        foreach ($this->bytesRadacctPorUsuarios($usernames) as $user => $partes) {
            $out[$user] = $partes['cerradas'] + $partes['abiertas'];
        }

        return $out;
    }

    /**
     * @param  list<string>  $usernames
     * @return array<string, array{cerradas: int, abiertas: int}>
     */
    public function bytesRadacctPorUsuarios(array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter(array_map(
            static fn ($u) => trim((string) $u),
            $usernames
        ), static fn (string $u) => $u !== '')));

        if ($usernames === [] || ! $this->enabled() || ! $this->conexionAlcanzable()) {
            return [];
        }

        try {
            $db = DB::connection($this->connection());
            if (! $db->getSchemaBuilder()->hasTable('radacct')) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($usernames), '?'));
            $expr = $this->expresionOctetos();
            $filas = $db->select(
                "SELECT username,
                    COALESCE(SUM(CASE WHEN acctstoptime IS NULL THEN 0 ELSE {$expr} END), 0) AS cerradas,
                    COALESCE(SUM(CASE WHEN acctstoptime IS NULL THEN {$expr} ELSE 0 END), 0) AS abiertas
                FROM radacct
                WHERE username IN ({$placeholders})
                GROUP BY username",
                $usernames
            );

            $out = [];
            foreach ($filas as $fila) {
                $user = trim((string) ($fila->username ?? ''));
                if ($user === '') {
                    continue;
                }
                $out[$user] = [
                    'cerradas' => max(0, (int) ($fila->cerradas ?? 0)),
                    'abiertas' => max(0, (int) ($fila->abiertas ?? 0)),
                ];
            }

            return $this->aplicarCortes($out);
        } catch (Throwable $e) {
            Log::warning('[RADIUS] no se pudo leer consumo por usuarios', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sesiones cerradas en radacct + el mayor entre la sesión abierta y el contador vivo del MikroTik.
     *
     * @param  array<string, array{cerradas?: int, abiertas?: int}>  $radacct
     * @param  array<string, int>  $vivo
     * @return array<string, int>
     */
    public static function combinarConsumoSesionViva(array $radacct, array $vivo): array
    {
        $out = [];
        $users = array_unique(array_merge(array_keys($radacct), array_keys($vivo)));
        foreach ($users as $user) {
            $user = trim((string) $user);
            if ($user === '') {
                continue;
            }
            $partes = $radacct[$user] ?? [];
            $cerradas = max(0, (int) ($partes['cerradas'] ?? 0));
            $abiertas = max(0, (int) ($partes['abiertas'] ?? 0));
            $live = max(0, (int) ($vivo[$user] ?? 0));
            $out[$user] = $cerradas + max($abiertas, $live);
        }

        return $out;
    }

    /**
     * Resta el corte del período: primero a las sesiones cerradas y el resto a la abierta.
     *
     * @return array{cerradas: int, abiertas: int}
     */
    public static function restarBaseContador(int $cerradas, int $abiertas, int $base): array
    {
        $base = max(0, $base);
        $cerradas = max(0, $cerradas);
        $abiertas = max(0, $abiertas);
        $deCerradas = min($cerradas, $base);

        return [
            'cerradas' => $cerradas - $deCerradas,
            'abiertas' => max(0, $abiertas - ($base - $deCerradas)),
        ];
    }

    /**
     * Cierra las sesiones RADIUS abiertas para que el corte no siga sumando tráfico viejo.
     *
     * @param  list<string>  $usernames
     */
    public function cerrarContabilidadAbierta(array $usernames): int
    {
        $usernames = $this->normalizarUsernames($usernames);
        if ($usernames === []) {
            return 0;
        }

        $cerradas = 0;
        $db = DB::connection($this->connection());
        foreach (array_chunk($usernames, 200) as $lote) {
            $cerradas += $db->table('radacct')
                ->whereIn('username', $lote)
                ->whereNull('acctstoptime')
                ->update([
                    'acctstoptime' => now()->format('Y-m-d H:i:s'),
                    'acctterminatecause' => 'Admin-Reset',
                ]);
        }

        return $cerradas;
    }

    /**
     * Suma histórica de radacct, sin restar el corte del período.
     *
     * @param  list<string>  $usernames
     * @return array<string, int>
     */
    public function consumoAcumulado(array $usernames): array
    {
        $usernames = $this->normalizarUsernames($usernames);
        if ($usernames === [] || ! $this->enabled()) {
            return [];
        }

        $db = DB::connection($this->connection());
        if (! $db->getSchemaBuilder()->hasTable('radacct')) {
            return [];
        }

        $expr = $this->expresionOctetos();
        $out = [];
        foreach (array_chunk($usernames, 200) as $lote) {
            $placeholders = implode(',', array_fill(0, count($lote), '?'));
            $filas = $db->select(
                "SELECT username, COALESCE(SUM({$expr}), 0) AS usados
                FROM radacct WHERE username IN ({$placeholders}) GROUP BY username",
                $lote
            );
            foreach ($filas as $fila) {
                $user = trim((string) ($fila->username ?? ''));
                if ($user !== '') {
                    $out[$user] = max(0, (int) ($fila->usados ?? 0));
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $bases
     */
    public function registrarCortesCuota(array $bases, string $periodo): void
    {
        $ahora = now()->format('Y-m-d H:i:s');
        $filas = [];
        foreach ($bases as $username => $bytes) {
            $username = trim((string) $username);
            if ($username === '') {
                continue;
            }
            $filas[] = [
                'username' => $username,
                'bytes_base' => max(0, (int) $bytes),
                'periodo' => $periodo,
                'cortado_en' => $ahora,
            ];
        }
        if ($filas === []) {
            return;
        }

        $db = DB::connection($this->connection());
        foreach (array_chunk($filas, 200) as $lote) {
            $db->table('hotspot_cuota_corte')->upsert(
                $lote,
                ['username'],
                ['bytes_base', 'periodo', 'cortado_en']
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function periodosCorte(array $usernames): array
    {
        $usernames = $this->normalizarUsernames($usernames);
        if ($usernames === [] || ! $this->tieneCorteCuota()) {
            return [];
        }

        $out = [];
        $db = DB::connection($this->connection());
        foreach (array_chunk($usernames, 200) as $lote) {
            $filas = $db->table('hotspot_cuota_corte')
                ->whereIn('username', $lote)
                ->get(['username', 'periodo']);
            foreach ($filas as $fila) {
                $user = trim((string) ($fila->username ?? ''));
                if ($user !== '') {
                    $out[$user] = (string) ($fila->periodo ?? '');
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $usernames
     * @return list<string>
     */
    protected function normalizarUsernames(array $usernames): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($u) => trim((string) $u),
            $usernames
        ), static fn (string $u) => $u !== '')));
    }

    protected function expresionOctetos(): string
    {
        if ($this->exprOctetos !== null) {
            return $this->exprOctetos;
        }

        $expr = 'CAST(COALESCE(acctinputoctets, 0) AS UNSIGNED) + CAST(COALESCE(acctoutputoctets, 0) AS UNSIGNED)';
        try {
            $schema = DB::connection($this->connection())->getSchemaBuilder();
            if ($schema->hasColumn('radacct', 'acctinputgigawords') && $schema->hasColumn('radacct', 'acctoutputgigawords')) {
                $expr .= ' + CAST(COALESCE(acctinputgigawords, 0) AS UNSIGNED) * '.self::GIGAWORD
                    .' + CAST(COALESCE(acctoutputgigawords, 0) AS UNSIGNED) * '.self::GIGAWORD;
            }
        } catch (Throwable) {
        }

        return $this->exprOctetos = $expr;
    }

    protected function tieneCorteCuota(): bool
    {
        if ($this->corteDisponible !== null) {
            return $this->corteDisponible;
        }

        try {
            $this->corteDisponible = DB::connection($this->connection())
                ->getSchemaBuilder()
                ->hasTable('hotspot_cuota_corte');
        } catch (Throwable) {
            $this->corteDisponible = false;
        }

        return $this->corteDisponible;
    }

    /**
     * @param  array<string, array{cerradas: int, abiertas: int}>  $partes
     * @return array<string, array{cerradas: int, abiertas: int}>
     */
    protected function aplicarCortes(array $partes): array
    {
        if ($partes === [] || ! $this->tieneCorteCuota()) {
            return $partes;
        }

        $bases = [];
        $db = DB::connection($this->connection());
        foreach (array_chunk(array_keys($partes), 200) as $lote) {
            $filas = $db->table('hotspot_cuota_corte')->whereIn('username', $lote)->get(['username', 'bytes_base']);
            foreach ($filas as $fila) {
                $user = trim((string) ($fila->username ?? ''));
                if ($user !== '') {
                    $bases[$user] = max(0, (int) ($fila->bytes_base ?? 0));
                }
            }
        }

        foreach ($partes as $user => $par) {
            if (! isset($bases[$user])) {
                continue;
            }
            $partes[$user] = self::restarBaseContador($par['cerradas'], $par['abiertas'], $bases[$user]);
        }

        return $partes;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $filas
     */
    protected function reemplazarAtributos(string $tabla, string $username, array $filas): void
    {
        $db = DB::connection($this->connection());
        $db->table($tabla)->where('username', $username)->delete();
        foreach ($filas as [$attribute, $op, $value]) {
            $db->table($tabla)->insert([
                'username' => $username,
                'attribute' => $attribute,
                'op' => $op,
                'value' => $value,
            ]);
        }
    }
}
