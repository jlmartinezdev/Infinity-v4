<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Huawei\HuaweiOnuService;
use App\Services\Huawei\HuaweiOnuWeb;
use App\Support\CpeInventario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Wi‑Fi del CPE del cliente portal.
 * ACS (TR-069) o Huawei ONU (SSH/web, misma fuente que el panel).
 * La clave nunca se lee.
 */
class PortalCpeWifiService
{
    public const REASON_NO_SERVICE = 'no_service';

    public const REASON_NO_ACS = 'no_acs';

    public const REASON_SSH_CPE = 'ssh_cpe';

    public const REASON_ACS_NOT_CONFIGURED = 'acs_not_configured';

    public const REASON_CPE_NOT_FOUND = 'cpe_not_found';

    public const REASON_ACS_UNREACHABLE = 'acs_unreachable';

    public const REASON_NO_SSID = 'no_ssid';

    public const REASON_RATE_LIMITED = 'rate_limited';

    public const REASON_HUAWEI_NEEDS_PASSWORD = 'huawei_needs_password';

    public const HINT_MANUAL = 'Entrá al router en 192.168.1.1 para cambiar la clave Wi‑Fi.';

    public const SOURCE_TR069 = 'tr069_acs';

    public const SOURCE_HUAWEI = 'huawei_onu';

    public function __construct(
        private readonly GenieAcsService $acs,
        private readonly HuaweiOnuService $huawei,
    ) {}

    /**
     * @return array{
     *   can_change: bool,
     *   can_rename: bool,
     *   source: string|null,
     *   servicio_id: int|null,
     *   password_readable: false,
     *   pending_inform: bool,
     *   ssids: list<array{id: string, ssid: string, enabled: bool, band: string|null}>,
     *   reason: string|null,
     *   hint: string|null
     * }
     */
    public function estado(Cliente $cliente, ?int $servicioId = null): array
    {
        try {
            @set_time_limit(45);

            $servicio = $this->resolverServicio($cliente, $servicioId);
            if (! $servicio) {
                return $this->noSoportado(self::REASON_NO_SERVICE, null);
            }

            // Preferir ACS cuando aplica: expone OperatingFrequencyBand real.
            if (CpeInventario::usaAcs($servicio) && $this->acs->configured()) {
                $gate = $this->motivoNoAcs($servicio);
                if ($gate === null) {
                    $resumen = $this->acs->resumen($servicio);
                    if ($resumen['success'] ?? false) {
                        $ssids = self::mapWifiForPortal($resumen['wifi'] ?? []);
                        $enabled = array_values(array_filter($ssids, fn (array $n) => (bool) ($n['enabled'] ?? false)));
                        if ($enabled !== []) {
                            $crOk = (bool) ($resumen['connection_request_ok'] ?? true);

                            return [
                                'can_change' => true,
                                'can_rename' => true,
                                'source' => self::SOURCE_TR069,
                                'servicio_id' => (int) $servicio->servicio_id,
                                'password_readable' => false,
                                'pending_inform' => ! $crOk,
                                'ssids' => $ssids,
                                'reason' => null,
                                'hint' => $crOk
                                    ? null
                                    : 'El cambio se aplica cuando el router se reporte al ACS (puede tardar unos minutos).',
                            ];
                        }
                    }
                }
            }

            if ($this->puedeHuawei($servicio)) {
                return $this->estadoHuawei($servicio);
            }

            $gate = $this->motivoNoAcs($servicio);
            if ($gate !== null) {
                return $this->noSoportado($gate, (int) $servicio->servicio_id);
            }

            return $this->noSoportado(self::REASON_CPE_NOT_FOUND, (int) $servicio->servicio_id);
        } catch (Throwable $e) {
            Log::warning('[Portal CPE WiFi] estado', [
                'cliente_id' => $cliente->cliente_id,
                'error' => $e->getMessage(),
            ]);

            return $this->noSoportado(self::REASON_ACS_UNREACHABLE, null);
        }
    }

    /**
     * @return array{success: bool, http: int, message: string, data: array<string, mixed>}
     */
    public function cambiar(
        Cliente $cliente,
        ?string $password = null,
        ?string $wifiId = null,
        ?int $servicioId = null,
        ?string $ssid = null
    ): array {
        $password = $password !== null && $password !== '' ? (string) $password : null;
        $ssid = $ssid !== null ? trim($ssid) : null;
        $ssid = ($ssid !== null && $ssid !== '') ? $ssid : null;

        if ($password === null && $ssid === null) {
            return [
                'success' => false,
                'http' => 422,
                'message' => 'Indicá una clave o un nombre de red Wi‑Fi.',
                'data' => ['reason' => 'invalid_input'],
            ];
        }

        if ($password !== null && (strlen($password) < 8 || strlen($password) > 63)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => 'La clave Wi‑Fi debe tener entre 8 y 63 caracteres.',
                'data' => ['reason' => 'invalid_password'],
            ];
        }

        if ($ssid !== null) {
            $ssidError = self::validarSsid($ssid);
            if ($ssidError !== null) {
                return [
                    'success' => false,
                    'http' => 422,
                    'message' => $ssidError,
                    'data' => ['reason' => 'invalid_ssid'],
                ];
            }
        }

        $rateKey = 'portal-cpe-wifi:'.$cliente->cliente_id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return [
                'success' => false,
                'http' => 429,
                'message' => 'Demasiados cambios de Wi‑Fi. Probá de nuevo en unos minutos.',
                'data' => [
                    'reason' => self::REASON_RATE_LIMITED,
                    'retry_after_seconds' => $seconds,
                ],
            ];
        }

        $estado = $this->estado($cliente, $servicioId);
        if (! ($estado['can_change'] ?? false)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => $estado['hint'] ?: 'No se puede cambiar el Wi‑Fi desde la app.',
                'data' => $estado,
            ];
        }

        $servicio = Servicio::query()->find($estado['servicio_id']);
        if (! $servicio || (int) $servicio->cliente_id !== (int) $cliente->cliente_id) {
            return [
                'success' => false,
                'http' => 422,
                'message' => 'Servicio no encontrado.',
                'data' => $this->noSoportado(self::REASON_NO_SERVICE, null),
            ];
        }

        if (($estado['source'] ?? null) === self::SOURCE_HUAWEI) {
            return $this->cambiarHuawei($cliente, $servicio, $estado, $password, $ssid, $rateKey);
        }

        $wifiId = ($wifiId !== null && $wifiId !== '') ? $wifiId : 'all';

        try {
            @set_time_limit(45);
            $result = $this->acs->setWifi($servicio, $password, $ssid, $wifiId);
        } catch (Throwable $e) {
            Log::warning('[Portal CPE WiFi] cambiar', [
                'cliente_id' => $cliente->cliente_id,
                'servicio_id' => $servicio->servicio_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'http' => 502,
                'message' => 'No se pudo contactar el ACS para cambiar el Wi‑Fi.',
                'data' => array_merge($estado, [
                    'can_change' => false,
                    'reason' => self::REASON_ACS_UNREACHABLE,
                ]),
            ];
        }

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => (string) ($result['message'] ?? 'No se pudo encolar el cambio de Wi‑Fi.'),
                'data' => array_merge($estado, [
                    'reason' => self::REASON_NO_SSID,
                ]),
            ];
        }

        RateLimiter::hit($rateKey, 3600);

        $ticket = $this->registrarAccionWifi($cliente, $servicio, $password, $ssid, $wifiId, self::SOURCE_TR069, $estado);

        Log::info('[Portal CPE WiFi] cambio encolado', [
            'cliente_id' => $cliente->cliente_id,
            'servicio_id' => $servicio->servicio_id,
            'wifi_id' => $wifiId,
            'cambia_clave' => $password !== null,
            'cambia_ssid' => $ssid !== null,
        ]);

        $ssidsOut = $estado['ssids'];
        if ($ssid !== null) {
            $ssidsOut = array_map(static function (array $n) use ($ssid, $wifiId): array {
                if ($wifiId === 'all' || ($n['id'] ?? '') === $wifiId) {
                    $n['ssid'] = $ssid;
                }

                return $n;
            }, $ssidsOut);
        }

        $data = [
            'queued' => true,
            'applied_now' => ! ($estado['pending_inform'] ?? false),
            'source' => self::SOURCE_TR069,
            'servicio_id' => (int) $servicio->servicio_id,
            'wifi_id' => $wifiId,
            'ssid' => $ssid,
            'ssids' => $ssidsOut,
            'pending_inform' => (bool) ($estado['pending_inform'] ?? false),
        ];
        if ($ticket) {
            $data['ticket'] = $ticket;
        }

        return [
            'success' => true,
            'http' => 200,
            'message' => (string) ($result['message'] ?? 'Cambio de Wi‑Fi encolado.'),
            'data' => $data,
        ];
    }

    public static function validarSsid(string $ssid): ?string
    {
        $len = mb_strlen($ssid);
        if ($len < 1 || $len > 32) {
            return 'El nombre Wi‑Fi debe tener entre 1 y 32 caracteres.';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $ssid) === 1) {
            return 'El nombre Wi‑Fi no puede tener caracteres de control.';
        }

        return null;
    }

    /**
     * Infere banda desde el nombre SSID (Huawei no manda band en display wifi).
     * Acepta variantes: "X-5G", "X_5G", "X-5G-", "X 5GHz", "X-2.4G".
     */
    public static function inferBandFromSsid(string $ssid): string
    {
        // 5G / 5GHz en cualquier posición (evita marcar 2.4 como 5 por un dígito suelto).
        if (preg_match('/(^|[^0-9])5\s*G(Hz)?([^a-z0-9]|$)/i', $ssid) === 1) {
            return '5GHz';
        }
        if (preg_match('/2\.?\s*4/i', $ssid) === 1) {
            return '2.4GHz';
        }

        return '2.4GHz';
    }

    /**
     * @param  list<array<string, mixed>>  $wifi
     * @return list<array{id: string, ssid: string, enabled: bool, band: string|null}>
     */
    public static function mapWifiForPortal(array $wifi): array
    {
        $out = [];
        foreach ($wifi as $net) {
            $id = trim((string) ($net['id'] ?? ''));
            $ssid = trim((string) ($net['ssid'] ?? ''));
            if ($id === '' || $ssid === '') {
                continue;
            }
            $band = $net['band'] ?? null;
            if (! is_string($band) || $band === '') {
                $band = self::inferBandFromPortalWifiId($id) ?? self::inferBandFromSsid($ssid);
            }
            $out[] = [
                'id' => $id,
                'ssid' => $ssid,
                'enabled' => (bool) ($net['enabled'] ?? false),
                'band' => is_string($band) && $band !== '' ? $band : null,
            ];
        }

        return $out;
    }

    /**
     * TR-098 / Huawei bajo ACS: WLANConfiguration.1 ≈ 2.4, .5+ ≈ 5 GHz.
     */
    public static function inferBandFromPortalWifiId(string $id): ?string
    {
        if (preg_match('/(?:wlan|ap|hw)-(?:\d+-)?(\d+)$/i', $id, $m) !== 1) {
            return null;
        }
        $idx = (int) $m[1];
        if ($idx >= 5) {
            return '5GHz';
        }
        if ($idx >= 1) {
            return '2.4GHz';
        }

        return null;
    }

    /**
     * @param  list<string|array{ssid?: string, band?: string|null, id?: string, enabled?: bool, index?: int}>  $ssids
     * @return list<array{id: string, ssid: string, enabled: bool, band: string|null}>
     */
    public static function mapHuaweiSsidsForPortal(array $ssids): array
    {
        $out = [];
        foreach (array_values($ssids) as $i => $row) {
            if (is_array($row)) {
                $name = trim((string) ($row['ssid'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $out[] = [
                    'id' => (string) ($row['id'] ?? ('hw-'.($row['index'] ?? $i))),
                    'ssid' => $name,
                    'enabled' => (bool) ($row['enabled'] ?? true),
                    'band' => isset($row['band']) && is_string($row['band']) && $row['band'] !== ''
                        ? $row['band']
                        : self::inferBandFromSsid($name),
                ];

                continue;
            }

            $name = trim((string) $row);
            if ($name === '') {
                continue;
            }
            $out[] = [
                'id' => 'hw-'.$i,
                'ssid' => $name,
                'enabled' => true,
                'band' => self::inferBandFromSsid($name),
            ];
        }

        return $out;
    }

    public static function hintPara(string $reason): string
    {
        return match ($reason) {
            self::REASON_NO_ACS, self::REASON_SSH_CPE, self::REASON_NO_SERVICE => self::HINT_MANUAL,
            self::REASON_NO_SSID => 'El router no reportó redes Wi‑Fi activas. Probá más tarde o cambiá la clave en 192.168.1.1.',
            self::REASON_CPE_NOT_FOUND => 'El router todavía no se reportó al sistema. Probá más tarde o usá 192.168.1.1.',
            self::REASON_ACS_UNREACHABLE, self::REASON_ACS_NOT_CONFIGURED => 'No se pudo consultar el router ahora. Probá más tarde o usá 192.168.1.1.',
            self::REASON_RATE_LIMITED => 'Demasiados cambios. Esperá un rato.',
            self::REASON_HUAWEI_NEEDS_PASSWORD => 'En esta ONU hay que enviar la nueva clave junto con el nombre Wi‑Fi.',
            default => self::HINT_MANUAL,
        };
    }

    /**
     * @return array{
     *   can_change: bool,
     *   can_rename: bool,
     *   source: string|null,
     *   servicio_id: int|null,
     *   password_readable: false,
     *   pending_inform: bool,
     *   ssids: list<array{id: string, ssid: string, enabled: bool, band: string|null}>,
     *   reason: string|null,
     *   hint: string|null
     * }
     */
    private function estadoHuawei(Servicio $servicio): array
    {
        $result = $this->huawei->leerWifi($servicio);
        if (! ($result['success'] ?? false)) {
            Log::info('[Portal CPE WiFi] soft-fail huawei leer', [
                'servicio_id' => $servicio->servicio_id,
                'message' => $result['message'] ?? null,
            ]);

            return $this->noSoportado(self::REASON_NO_SSID, (int) $servicio->servicio_id);
        }

        // Preferir radios con banda detectada por canal/estándar/índice (no por nombre).
        $ssids = self::mapHuaweiSsidsForPortal(
            ! empty($result['radios']) ? $result['radios'] : ($result['ssids'] ?? [])
        );
        if ($ssids === [] && filled($result['ssid'] ?? null)) {
            $ssids = self::mapHuaweiSsidsForPortal([(string) $result['ssid']]);
        }
        if ($ssids === []) {
            return $this->noSoportado(self::REASON_NO_SSID, (int) $servicio->servicio_id);
        }

        return [
            'can_change' => true,
            'can_rename' => true,
            'source' => self::SOURCE_HUAWEI,
            'servicio_id' => (int) $servicio->servicio_id,
            'password_readable' => false,
            'pending_inform' => false,
            'ssids' => $ssids,
            'reason' => null,
            'hint' => 'El cambio se aplica al instante en la ONU. Los celulares tendrán que reconectarse.',
        ];
    }

    /**
     * @param  array<string, mixed>  $estado
     * @return array{success: bool, http: int, message: string, data: array<string, mixed>}
     */
    private function cambiarHuawei(
        Cliente $cliente,
        Servicio $servicio,
        array $estado,
        ?string $password,
        ?string $ssid,
        string $rateKey,
    ): array {
        // Huawei CLI exige SSID + clave juntos.
        if ($password === null) {
            return [
                'success' => false,
                'http' => 422,
                'message' => self::hintPara(self::REASON_HUAWEI_NEEDS_PASSWORD),
                'data' => array_merge($estado, [
                    'reason' => self::REASON_HUAWEI_NEEDS_PASSWORD,
                ]),
            ];
        }

        if ($ssid === null) {
            $ssid = trim((string) ($estado['ssids'][0]['ssid'] ?? ''));
            if ($ssid === '') {
                return [
                    'success' => false,
                    'http' => 422,
                    'message' => 'No hay SSID actual para aplicar la clave. Indicá también el nombre de red.',
                    'data' => array_merge($estado, ['reason' => self::REASON_NO_SSID]),
                ];
            }
        }

        try {
            @set_time_limit(60);
            $result = $this->huawei->cambiarWifi($servicio, $ssid, $password);
        } catch (Throwable $e) {
            Log::warning('[Portal CPE WiFi] cambiar huawei', [
                'cliente_id' => $cliente->cliente_id,
                'servicio_id' => $servicio->servicio_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'http' => 502,
                'message' => 'No se pudo contactar la ONU para cambiar el Wi‑Fi.',
                'data' => array_merge($estado, [
                    'can_change' => false,
                    'reason' => self::REASON_ACS_UNREACHABLE,
                ]),
            ];
        }

        if (! ($result['success'] ?? false)) {
            return [
                'success' => false,
                'http' => 422,
                'message' => (string) ($result['message'] ?? 'No se pudo cambiar el Wi‑Fi en la ONU.'),
                'data' => array_merge($estado, [
                    'reason' => self::REASON_NO_SSID,
                ]),
            ];
        }

        RateLimiter::hit($rateKey, 3600);

        $ticket = $this->registrarAccionWifi($cliente, $servicio, $password, $ssid, 'all', self::SOURCE_HUAWEI, $estado);

        $ssidsOut = self::mapHuaweiSsidsForPortal(
            ! empty($result['radios']) ? $result['radios'] : ($result['ssids'] ?? [$ssid])
        );

        Log::info('[Portal CPE WiFi] cambio huawei aplicado', [
            'cliente_id' => $cliente->cliente_id,
            'servicio_id' => $servicio->servicio_id,
            'via' => $result['via'] ?? null,
        ]);

        $data = [
            'queued' => false,
            'applied_now' => true,
            'source' => self::SOURCE_HUAWEI,
            'servicio_id' => (int) $servicio->servicio_id,
            'wifi_id' => 'all',
            'ssid' => $ssid,
            'ssids' => $ssidsOut !== [] ? $ssidsOut : $estado['ssids'],
            'pending_inform' => false,
        ];
        if ($ticket) {
            $data['ticket'] = $ticket;
        }

        return [
            'success' => true,
            'http' => 200,
            'message' => (string) ($result['message'] ?? 'Wi‑Fi actualizado en la ONU.'),
            'data' => $data,
        ];
    }

    /**
     * Historial portal + ticket resuelto (tema Wi‑Fi).
     *
     * @param  array<string, mixed>  $estado
     * @return array{id: int, estado: string, fecha_cierre: string|null, asunto: string}|null
     */
    private function registrarAccionWifi(
        Cliente $cliente,
        Servicio $servicio,
        ?string $password,
        ?string $ssid,
        string $wifiId,
        string $source,
        array $estado
    ): ?array {
        $ssidGuardar = $ssid;
        if ($ssidGuardar === null) {
            $ssidGuardar = trim((string) ($estado['ssids'][0]['ssid'] ?? ''));
            $ssidGuardar = $ssidGuardar !== '' ? $ssidGuardar : null;
        }

        app(PortalClienteAccionService::class)->registrarWifi($cliente, $servicio, [
            'ssid' => $ssidGuardar,
            'wifi_id' => $wifiId,
            'password' => $password,
            'source' => $source,
        ]);

        return app(PortalAccionTicketService::class)->ticketCambioWifi(
            $cliente,
            (int) $servicio->servicio_id,
            $password !== null,
            $ssid !== null,
            $ssidGuardar,
            $wifiId,
            $source
        );
    }

    private function resolverServicio(Cliente $cliente, ?int $servicioId): ?Servicio
    {
        $base = Servicio::query()
            ->with(['pool.router.nodo', 'plan', 'cajaNapPuertoActivo'])
            ->where('cliente_id', $cliente->cliente_id)
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO);

        if ($servicioId !== null && $servicioId > 0) {
            return (clone $base)->where('servicio_id', $servicioId)->first();
        }

        $servicios = $base
            ->orderByRaw("CASE estado WHEN 'A' THEN 0 WHEN 'S' THEN 1 WHEN 'C' THEN 2 ELSE 3 END")
            ->orderBy('servicio_id')
            ->get();

        $acs = $servicios->first(fn (Servicio $s) => CpeInventario::usaAcs($s));
        if ($acs) {
            return $acs;
        }

        $huawei = $servicios->first(fn (Servicio $s) => $this->puedeHuawei($s));
        if ($huawei) {
            return $huawei;
        }

        return $servicios->first();
    }

    private function puedeHuawei(Servicio $servicio): bool
    {
        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (CpeInventario::esHuaweiOnu($servicio) || CpeInventario::usaSshCpe($servicio)) {
            return true;
        }

        if (! $this->servicioEsFibra($servicio)) {
            return false;
        }

        return HuaweiOnuWeb::detectarCacheado(
            $ip,
            (int) config('huawei.web_port', 80),
            (int) config('huawei.web_detect_timeout', 3)
        );
    }

    private function servicioEsFibra(Servicio $servicio): bool
    {
        if ($servicio->cajaNapPuertoActivo) {
            return true;
        }
        if ($servicio->pool?->olt_id) {
            return true;
        }
        if ($servicio->pool?->router?->nodo?->manejaGpon()) {
            return true;
        }
        $planNombre = strtolower((string) ($servicio->plan?->nombre ?? ''));

        return str_contains($planNombre, 'fibra')
            || str_contains($planNombre, 'gpon')
            || str_contains($planNombre, 'ftth');
    }

    private function motivoNoAcs(Servicio $servicio): ?string
    {
        if (CpeInventario::usaSshCpe($servicio) && ! $this->puedeHuawei($servicio)) {
            return self::REASON_SSH_CPE;
        }
        if (! $this->acs->configured()) {
            return self::REASON_ACS_NOT_CONFIGURED;
        }
        if (! CpeInventario::usaAcs($servicio)) {
            return self::REASON_NO_ACS;
        }

        return null;
    }

    private function reasonDesdeAcs(string $message): string
    {
        $m = strtolower($message);
        if (str_contains($m, 'ssh')) {
            return self::REASON_SSH_CPE;
        }
        if (str_contains($m, 'no está configurado') || str_contains($m, 'genieacs no')) {
            return self::REASON_ACS_NOT_CONFIGURED;
        }
        if (str_contains($m, 'contactar') || str_contains($m, 'nbi')) {
            return self::REASON_ACS_UNREACHABLE;
        }

        return self::REASON_CPE_NOT_FOUND;
    }

    /**
     * @return array{
     *   can_change: false,
     *   can_rename: false,
     *   source: null,
     *   servicio_id: int|null,
     *   password_readable: false,
     *   pending_inform: false,
     *   ssids: list<empty>,
     *   reason: string,
     *   hint: string
     * }
     */
    private function noSoportado(string $reason, ?int $servicioId): array
    {
        return [
            'can_change' => false,
            'can_rename' => false,
            'source' => null,
            'servicio_id' => $servicioId,
            'password_readable' => false,
            'pending_inform' => false,
            'ssids' => [],
            'reason' => $reason,
            'hint' => self::hintPara($reason),
        ];
    }
}
