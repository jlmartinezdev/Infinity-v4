<?php

namespace App\Services\Olt;

use App\Models\Olt;
use App\Models\OltOnu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OltOnuSyncService
{
    public function __construct(
        private readonly VsolGponClient $client,
        private readonly VsolOnuOutputParser $parser,
    ) {}

    /**
     * @return array{success: bool, imported: int, online: int, offline: int, message: string}
     */
    public function importarDesdeOlt(Olt $olt, bool $conDetalleIndividual = true): array
    {
        if (! $olt->tieneCredencialesGestion()) {
            throw new RuntimeException('Configure IP y contraseña de gestión en el OLT antes de importar ONUs.');
        }

        $syncStarted = now();

        try {
            $raw = $this->client->fetchOnuList($olt);
            $cliBlob = trim(($raw['onu_info'] ?? '')."\n".($raw['onu_state'] ?? ''));

            try {
                $opticalBlob = $this->client->fetchOnuOpticalDiag($olt);
            } catch (Throwable $e) {
                Log::warning('OLT opm-diag omitido en import', ['olt_id' => $olt->olt_id, 'error' => $e->getMessage()]);
                $opticalBlob = '';
            }

            $parsed = $this->filtrarOnusRegistradas($this->mergeFilasOnuParsed(
                $this->parser->parse((string) ($raw['onu_info'] ?? ''), (string) ($raw['onu_state'] ?? '')),
                $this->parser->parseOptical($opticalBlob),
            ));

            if ($parsed === []) {
                $preview = trim(mb_substr($cliBlob, 0, 1500));
                Log::warning('OLT import ONUs: parser sin resultados', [
                    'olt_id' => $olt->olt_id,
                    'preview' => $preview,
                ]);

                throw new RuntimeException($this->mensajeConSalidaCli(
                    'No se pudieron interpretar las ONUs del OLT. El formato CLI puede no coincidir con este firmware VSOL.',
                    $preview
                ));
            }

            $imported = DB::transaction(function () use ($olt, $parsed, $syncStarted) {
                $count = 0;
                foreach ($parsed as $row) {
                    OltOnu::updateOrCreate(
                        [
                            'olt_id' => $olt->olt_id,
                            'pon_key' => $row['pon_key'],
                            'onu_index' => $row['onu_index'],
                        ],
                        [
                            'pon_slot' => $row['pon_slot'],
                            'pon_port' => $row['pon_port'],
                            'serial' => $row['serial'] ?? null,
                            'vendor_id' => $row['vendor_id'] ?? null,
                            'modelo' => $row['modelo'] ?? null,
                            'descripcion' => $this->descripcionValida($row['descripcion'] ?? null, $row['modelo'] ?? null),
                            'estado' => $row['estado'] ?? 'unknown',
                            'rx_power_dbm' => $row['rx_power_dbm'] ?? null,
                            'tx_power_dbm' => $row['tx_power_dbm'] ?? null,
                            'synced_at' => $syncStarted,
                        ],
                    );
                    $count++;
                }

                OltOnu::where('olt_id', $olt->olt_id)
                    ->where(function ($q) use ($syncStarted) {
                        $q->whereNull('synced_at')
                            ->orWhere('synced_at', '<', $syncStarted);
                    })
                    ->delete();

                $this->eliminarOnusNoRegistradas($olt);

                return $count;
            });

            $online = OltOnu::where('olt_id', $olt->olt_id)->get()->filter->estadoEsOnline()->count();
            $offline = OltOnu::where('olt_id', $olt->olt_id)->get()->filter->estadoEsOffline()->count();
            $desconocido = OltOnu::where('olt_id', $olt->olt_id)->count() - $online - $offline;

            $olt->update([
                'onus_synced_at' => now(),
                'onus_sync_error' => null,
            ]);

            $detalle = 0;
            if ($conDetalleIndividual) {
                $detalle = $this->actualizarDetalleOnusIndividuales($olt);
            }
            $mensajeDetalle = $detalle > 0 ? " Detalle (desc/RX) en {$detalle} ONU(s)." : '';

            return [
                'success' => true,
                'imported' => $imported,
                'online' => $online,
                'offline' => $offline,
                'message' => "Se importaron {$imported} ONU(s). Online: {$online}, offline/alarma: {$offline}, sin estado: {$desconocido}.{$mensajeDetalle}",
            ];
        } catch (Throwable $e) {
            Log::warning('OLT import ONUs failed', [
                'olt_id' => $olt->olt_id,
                'ip' => $olt->ip,
                'error' => $e->getMessage(),
            ]);

            $olt->update([
                'onus_sync_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Consulta el OLT (estados si ya hay ONUs, importación completa si no).
     *
     * @return array{success: bool, imported?: int, online?: int, offline?: int, updated?: int, message: string}|null
     */
    public function sincronizarAlVisualizar(Olt $olt, bool $forzar = false): ?array
    {
        if (! $olt->tieneCredencialesGestion()) {
            return null;
        }

        if (! $forzar && request()->boolean('sin_sync')) {
            return null;
        }

        $intervalo = max(0, (int) config('olt.vsol.show_sync_min_interval', 0));
        if ($intervalo > 0 && $olt->onus_synced_at?->gt(now()->subSeconds($intervalo))) {
            return null;
        }

        try {
            if (! $olt->onus()->exists()) {
                return $this->importarDesdeOlt($olt, conDetalleIndividual: false);
            }

            return $this->actualizarEstadosDesdeOlt($olt, conDetalleIndividual: false);
        } catch (Throwable $e) {
            Log::warning('OLT sync on show failed', [
                'olt_id' => $olt->olt_id,
                'ip' => $olt->ip,
                'error' => $e->getMessage(),
            ]);

            $olt->update([
                'onus_sync_error' => $e->getMessage(),
            ]);

            $message = $e->getMessage();
            $preview = $this->previewDesdeMensaje($message);

            return [
                'success' => false,
                'message' => $preview ? $this->mensajeSinDump($message) : $message,
                'preview' => $preview,
            ];
        }
    }

    /**
     * @return array{success: bool, updated: int, online: int, offline: int, message: string}
     */
    public function actualizarEstadosDesdeOlt(Olt $olt, bool $conDetalleIndividual = true): array
    {
        if (! $olt->tieneCredencialesGestion()) {
            throw new RuntimeException('Configure IP y contraseña de gestión en el OLT antes de consultar ONUs.');
        }

        $syncStarted = now();
        // Una sola sesión: evita abrir Telnet dos veces seguidas (corta el OLT en Windows).
        $bundle = $this->client->fetchOnuList($olt);
        $stateBlob = (string) ($bundle['onu_state'] ?? '');
        $infoBlob = (string) ($bundle['onu_info'] ?? '');

        $parsed = $this->filtrarOnusRegistradas($this->mergeFilasOnuParsed(
            $this->parser->parse('', $stateBlob),
            $this->parser->parse($infoBlob, ''),
        ));

        if ($parsed === []) {
            $preview = trim(mb_substr($stateBlob."\n".$infoBlob, 0, 1500));
            Log::warning('OLT update ONUs state: parser sin resultados', [
                'olt_id' => $olt->olt_id,
                'preview' => $preview,
            ]);

            throw new RuntimeException($this->mensajeConSalidaCli(
                'No se pudieron interpretar los estados de las ONUs del OLT.',
                $preview
            ));
        }

        $updated = DB::transaction(function () use ($olt, $parsed, $syncStarted) {
            $count = 0;

            foreach ($parsed as $row) {
                $onu = OltOnu::query()
                    ->where('olt_id', $olt->olt_id)
                    ->where('pon_key', $row['pon_key'])
                    ->where('onu_index', $row['onu_index'])
                    ->first();

                if ($onu) {
                    $this->aplicarFilaOnu($onu, $row, $syncStarted);
                    $count++;

                    continue;
                }

                if (! $this->parser->filaEsOnuRegistrada($row)) {
                    continue;
                }

                OltOnu::create([
                    'olt_id' => $olt->olt_id,
                    'pon_slot' => $row['pon_slot'],
                    'pon_port' => $row['pon_port'],
                    'pon_key' => $row['pon_key'],
                    'onu_index' => $row['onu_index'],
                    'serial' => $row['serial'] ?? null,
                    'vendor_id' => $row['vendor_id'] ?? null,
                    'modelo' => $row['modelo'] ?? null,
                    'descripcion' => $this->descripcionValida($row['descripcion'] ?? null, $row['modelo'] ?? null),
                    'estado' => $row['estado'] ?? 'unknown',
                    'rx_power_dbm' => $row['rx_power_dbm'] ?? null,
                    'tx_power_dbm' => $row['tx_power_dbm'] ?? null,
                    'synced_at' => $syncStarted,
                ]);
                $count++;
            }

            return $count;
        });

        $this->eliminarOnusNoRegistradas($olt);

        $onus = OltOnu::where('olt_id', $olt->olt_id)->get();
        $online = $onus->filter->estadoEsOnline()->count();
        $offline = $onus->filter->estadoEsOffline()->count();
        $desconocido = $onus->count() - $online - $offline;

        $olt->update([
            'onus_synced_at' => now(),
            'onus_sync_error' => null,
        ]);

        $detalle = 0;
        if ($conDetalleIndividual) {
            $detalle = $this->actualizarDetalleOnusIndividuales($olt);
        }
        $mensajeDetalle = $detalle > 0 ? " Desc/RX consultados en {$detalle} ONU(s)." : '';

        return [
            'success' => true,
            'updated' => $updated,
            'online' => $online,
            'offline' => $offline,
            'message' => "Estados actualizados ({$updated} ONU(s)). Online: {$online}, offline/alarma: {$offline}, sin estado: {$desconocido}.{$mensajeDetalle}",
        ];
    }

    /**
     * Consulta show onu N desc / show onu desc N y optical_info por cada ONU.
     *
     * @return int Cantidad de ONUs actualizadas
     */
    public function actualizarDetalleOnusIndividuales(Olt $olt, ?int $limite = null, ?\Illuminate\Support\Collection $soloOnus = null): int
    {
        if (! $olt->tieneCredencialesGestion()) {
            return 0;
        }

        if ($soloOnus !== null) {
            $onus = $soloOnus->sortBy('onu_index')->values();
        } else {
            if ($limite === null) {
                $limite = (int) config('olt.vsol.onu_detail_max_per_sync', 48);
            }

            if ($limite === 0) {
                return 0;
            }

            $onus = $olt->onus()->get()->sortBy(function (OltOnu $onu) {
                $faltaRx = $onu->rx_power_dbm === null ? 0 : 1;
                $faltaDesc = trim((string) ($onu->descripcion ?? '')) === ''
                    || $this->descripcionEsModelo((string) $onu->descripcion, $onu->modelo)
                    ? 0
                    : 1;
                $online = $onu->estadoEsOnline() ? 0 : 1;

                return [$online, $faltaRx + $faltaDesc, $onu->pon_port, $onu->onu_index];
            })->take($limite);
        }

        if ($onus->isEmpty()) {
            return 0;
        }

        $items = $onus->map(fn (OltOnu $onu) => [
            'pon_slot' => (int) $onu->pon_slot,
            'pon_port' => (int) $onu->pon_port,
            'onu_index' => (int) $onu->onu_index,
        ])->values()->all();

        $raw = $this->client->fetchDetallesOnus($olt, $items);
        $syncStarted = now();
        $updated = 0;

        foreach ($onus as $onu) {
            $key = $onu->pon_key.':'.$onu->onu_index;
            if (! isset($raw[$key])) {
                continue;
            }

            $bloque = $raw[$key];
            $row = [];

            if (! $this->client->salidaEsInvalida($bloque['desc'] ?? '')) {
                $desc = $this->parser->parseOnuDescOutput($bloque['desc']);
                if ($desc !== null && $desc !== '' && ! $this->descripcionEsModelo($desc, $onu->modelo)) {
                    $row['descripcion'] = $desc;
                }
            }

            // Respaldo descripción desde show onu info del puerto (útil si falló show onu 1 desc)
            if (! isset($row['descripcion']) && ! empty($bloque['onu_info'])) {
                foreach ($this->parser->parse($bloque['onu_info'], '') as $infoRow) {
                    if ((int) ($infoRow['onu_index'] ?? 0) !== (int) $onu->onu_index) {
                        continue;
                    }
                    if ((int) ($infoRow['pon_port'] ?? $onu->pon_port) !== (int) $onu->pon_port) {
                        continue;
                    }
                    $desc = trim((string) ($infoRow['descripcion'] ?? ''));
                    if ($desc !== '' && ! $this->descripcionEsModelo($desc, $onu->modelo)) {
                        $row['descripcion'] = $desc;
                    }
                    break;
                }
            }

            if (! $this->client->salidaEsInvalida($bloque['optical'] ?? '')) {
                $optical = $this->parser->parseOnuOpticalInfoOutput($bloque['optical']);
                if (isset($optical['rx_power_dbm'])) {
                    $row['rx_power_dbm'] = $optical['rx_power_dbm'];
                }
                if (isset($optical['tx_power_dbm'])) {
                    $row['tx_power_dbm'] = $optical['tx_power_dbm'];
                }
            }

            // Respaldo RX desde opm-diag del puerto si optical_info individual falló
            if (! isset($row['rx_power_dbm']) && ! empty($bloque['opm_diag'])) {
                foreach ($this->parser->parseOptical($bloque['opm_diag']) as $opmRow) {
                    if ((int) ($opmRow['onu_index'] ?? 0) !== (int) $onu->onu_index) {
                        continue;
                    }
                    if ((int) ($opmRow['pon_port'] ?? $onu->pon_port) !== (int) $onu->pon_port) {
                        continue;
                    }
                    if (isset($opmRow['rx_power_dbm'])) {
                        $row['rx_power_dbm'] = $opmRow['rx_power_dbm'];
                    }
                    if (isset($opmRow['tx_power_dbm'])) {
                        $row['tx_power_dbm'] = $opmRow['tx_power_dbm'];
                    }
                    break;
                }
            }

            if ($row === []) {
                continue;
            }

            $this->aplicarFilaOnu($onu, $row, $syncStarted);
            $updated++;
        }

        return $updated;
    }

    /**
     * Consulta desc/RX de todas las ONUs del OLT (puede tardar varios minutos).
     *
     * @return array{success: bool, updated: int, message: string}
     */
    public function refrescarDetalleTodasLasOnus(Olt $olt): array
    {
        if (! $olt->tieneCredencialesGestion()) {
            throw new RuntimeException('Configure IP y contraseña de gestión en el OLT.');
        }

        $total = $olt->onus()->count();
        if ($total === 0) {
            return [
                'success' => true,
                'updated' => 0,
                'message' => 'No hay ONUs importadas para consultar.',
            ];
        }

        @set_time_limit(max(300, $total * 4));

        $updated = $this->actualizarDetalleOnusIndividuales($olt, $total);

        $olt->update(['onus_synced_at' => now(), 'onus_sync_error' => null]);

        return [
            'success' => true,
            'updated' => $updated,
            'message' => "Detalle consultado en {$updated} de {$total} ONU(s) (desc + potencia RX).",
        ];
    }

    /**
     * @return array{success: bool, updated: int, message: string}
     */
    public function refrescarDetalleOnusPorPon(Olt $olt, int $ponPort): array
    {
        if (! $olt->tieneCredencialesGestion()) {
            throw new RuntimeException('Configure IP y contraseña de gestión en el OLT.');
        }

        @set_time_limit(180);

        $this->sincronizarRegistradasPorPon($olt, $ponPort);

        $onus = $olt->onus()
            ->registradas()
            ->where('pon_port', $ponPort)
            ->orderBy('onu_index')
            ->get();

        if ($onus->isEmpty()) {
            return [
                'success' => false,
                'updated' => 0,
                'message' => "No hay ONUs registradas en PON 0/{$ponPort}. Importá la lista desde el OLT.",
            ];
        }

        $ponKey = ($onus->first()->pon_key ?? '0/'.$ponPort);

        @set_time_limit(max(120, $onus->count() * 4));

        $updated = $this->actualizarDetalleOnusIndividuales($olt, null, $onus);

        $this->corregirRxInvalidos($olt, $ponPort);
        $this->corregirEstadoLosConRx($olt, $ponPort);
        $this->limpiarDescripcionesQueSonModelo($olt, $ponPort);

        $olt->update(['onus_synced_at' => now(), 'onus_sync_error' => null]);

        return [
            'success' => true,
            'updated' => $updated,
            'message' => "PON {$ponKey}: {$onus->count()} ONU(s) registradas, desc/RX actualizados en {$updated}.",
        ];
    }

    /**
     * Relee show onu info/state de un PON y deja solo ONUs registradas (con serial o modelo).
     */
    public function sincronizarRegistradasPorPon(Olt $olt, int $ponPort): int
    {
        $raw = $this->client->fetchOnuDataForPort($olt, $ponPort);
        $syncStarted = now();

        // Estado de show onu info (Status/Online) debe prevalecer sobre Phase state (a veces LOS obsoleto).
        $parsed = $this->filtrarOnusRegistradas($this->mergeFilasOnuParsed(
            $this->parser->parse('', $raw['onu_state'] ?? ''),
            $this->parser->parse($raw['onu_info'] ?? '', ''),
        ));

        $clavesValidas = [];

        foreach ($parsed as $row) {
            if ((int) ($row['pon_port'] ?? 0) !== $ponPort) {
                continue;
            }

            $clavesValidas[] = ($row['pon_key'] ?? '0/'.$ponPort).':'.($row['onu_index'] ?? 0);

            $onu = OltOnu::query()
                ->where('olt_id', $olt->olt_id)
                ->where('pon_key', $row['pon_key'])
                ->where('onu_index', $row['onu_index'])
                ->first();

            if ($onu) {
                $this->aplicarFilaOnu($onu, $row, $syncStarted);

                continue;
            }

            OltOnu::create([
                'olt_id' => $olt->olt_id,
                'pon_slot' => $row['pon_slot'],
                'pon_port' => $row['pon_port'],
                'pon_key' => $row['pon_key'],
                'onu_index' => $row['onu_index'],
                'serial' => $row['serial'] ?? null,
                'vendor_id' => $row['vendor_id'] ?? null,
                'modelo' => $row['modelo'] ?? null,
                'descripcion' => $this->descripcionValida($row['descripcion'] ?? null, $row['modelo'] ?? null),
                'estado' => $row['estado'] ?? 'unknown',
                'rx_power_dbm' => $row['rx_power_dbm'] ?? null,
                'tx_power_dbm' => $row['tx_power_dbm'] ?? null,
                'synced_at' => $syncStarted,
            ]);
        }

        OltOnu::query()
            ->where('olt_id', $olt->olt_id)
            ->where('pon_port', $ponPort)
            ->where(function ($q) use ($syncStarted) {
                $q->whereNull('synced_at')->orWhere('synced_at', '<', $syncStarted);
            })
            ->delete();

        $this->eliminarOnusNoRegistradas($olt, $ponPort);

        return count($clavesValidas);
    }

    /**
     * @param  array<int, array<string, mixed>>  ...$grupos
     * @return array<int, array<string, mixed>>
     */
    private function mergeFilasOnuParsed(array ...$grupos): array
    {
        $merged = [];

        foreach ($grupos as $grupo) {
            foreach ($grupo as $row) {
                $row = $this->normalizarSlotVsol($row);
                $key = ($row['pon_key'] ?? '').':'.($row['onu_index'] ?? '');
                if ($key === ':' || $key === '0:') {
                    continue;
                }

                $existente = $merged[$key] ?? [];
                foreach ($row as $campo => $valor) {
                    if ($valor === null || $valor === '') {
                        continue;
                    }
                    if ($campo === 'estado' && $valor === 'unknown' && ($existente['estado'] ?? 'unknown') !== 'unknown') {
                        continue;
                    }
                    $existente[$campo] = $valor;
                }
                $merged[$key] = $existente;
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizarSlotVsol(array $row): array
    {
        $slot = (int) ($row['pon_slot'] ?? 0);
        $port = (int) ($row['pon_port'] ?? 0);
        if ($slot === 1 && $port > 0) {
            $row['pon_slot'] = 0;
            $row['pon_key'] = '0/'.$port;
        }

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    private function filtrarOnusRegistradas(array $filas): array
    {
        return array_values(array_filter($filas, fn (array $row) => $this->parser->filaEsOnuRegistrada($row)));
    }

    private function eliminarOnusNoRegistradas(Olt $olt, ?int $ponPort = null): void
    {
        $query = OltOnu::query()
            ->where('olt_id', $olt->olt_id)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('serial')->orWhere('serial', '=', '');
                })->where(function ($q2) {
                    $q2->whereNull('modelo')->orWhere('modelo', '=', '');
                });
            });

        if ($ponPort !== null) {
            $query->where('pon_port', $ponPort);
        }

        $query->delete();
    }

    private function corregirRxInvalidos(Olt $olt, ?int $ponPort = null): void
    {
        $query = OltOnu::query()
            ->where('olt_id', $olt->olt_id)
            ->where('rx_power_dbm', '>', 0);

        if ($ponPort !== null) {
            $query->where('pon_port', $ponPort);
        }

        $query->update(['rx_power_dbm' => null]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function aplicarFilaOnu(OltOnu $onu, array $row, \Illuminate\Support\Carbon $syncStarted): void
    {
        $updates = ['synced_at' => $syncStarted];

        if (isset($row['estado']) && ($row['estado'] !== 'unknown' || $onu->estado === null || $onu->estado === 'unknown')) {
            $updates['estado'] = $row['estado'];
        }

        if (! empty($row['serial'])) {
            $updates['serial'] = $row['serial'];
        }
        if (! empty($row['modelo'])) {
            $updates['modelo'] = $row['modelo'];
        }
        if (! empty($row['descripcion']) && ! $this->descripcionEsModelo((string) $row['descripcion'], $row['modelo'] ?? $onu->modelo)) {
            $updates['descripcion'] = $row['descripcion'];
        }
        if (array_key_exists('rx_power_dbm', $row) && $row['rx_power_dbm'] !== null) {
            $rx = (float) $row['rx_power_dbm'];
            if ($rx <= 0 && $rx >= -50) {
                $updates['rx_power_dbm'] = $rx;
            }
        }
        if (array_key_exists('tx_power_dbm', $row) && $row['tx_power_dbm'] !== null) {
            $updates['tx_power_dbm'] = $row['tx_power_dbm'];
        }

        // LOS con potencia RX válida suele ser Phase state obsoleto; el Status real es Online.
        $estadoFinal = $updates['estado'] ?? $onu->estado;
        $rxFinal = array_key_exists('rx_power_dbm', $updates) ? $updates['rx_power_dbm'] : $onu->rx_power_dbm;
        if (strtolower((string) $estadoFinal) === 'los' && $rxFinal !== null && (float) $rxFinal <= 0 && (float) $rxFinal >= -40) {
            $updates['estado'] = 'working';
        }

        $onu->update($updates);
    }

    private function descripcionValida(mixed $descripcion, mixed $modelo): ?string
    {
        $descripcion = trim((string) ($descripcion ?? ''));
        if ($descripcion === '' || $this->descripcionEsModelo($descripcion, $modelo)) {
            return null;
        }

        return $descripcion;
    }

    private function descripcionEsModelo(string $descripcion, mixed $modelo): bool
    {
        $descripcion = trim($descripcion);
        $modelo = trim((string) ($modelo ?? ''));

        if ($descripcion === '' || $modelo === '') {
            return false;
        }

        return strcasecmp($descripcion, $modelo) === 0;
    }

    private function corregirEstadoLosConRx(Olt $olt, ?int $ponPort = null): void
    {
        $query = OltOnu::query()
            ->where('olt_id', $olt->olt_id)
            ->whereRaw('LOWER(estado) = ?', ['los'])
            ->whereNotNull('rx_power_dbm')
            ->where('rx_power_dbm', '<=', 0)
            ->where('rx_power_dbm', '>=', -40);

        if ($ponPort !== null) {
            $query->where('pon_port', $ponPort);
        }

        $query->update(['estado' => 'working']);
    }

    private function limpiarDescripcionesQueSonModelo(Olt $olt, ?int $ponPort = null): void
    {
        $query = OltOnu::query()->where('olt_id', $olt->olt_id);
        if ($ponPort !== null) {
            $query->where('pon_port', $ponPort);
        }

        $query->whereNotNull('descripcion')
            ->whereNotNull('modelo')
            ->whereColumn('descripcion', 'modelo')
            ->update(['descripcion' => null]);
    }

    /**
     * @return array{success: bool, message: string, preview?: string}
     */
    public function probarConexion(Olt $olt): array
    {
        if (! $olt->tieneCredencialesGestion()) {
            return [
                'success' => false,
                'message' => 'Configure IP y contraseña de gestión.',
            ];
        }

        try {
            $preview = $this->client->testConnection($olt);

            return [
                'success' => true,
                'message' => 'Conexión exitosa con el OLT.',
                'preview' => $preview,
            ];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $preview = $this->previewDesdeMensaje($message);

            return [
                'success' => false,
                'message' => $preview ? $this->mensajeSinDump($message) : $message,
                'preview' => $preview,
            ];
        }
    }

    private function mensajeConSalidaCli(string $mensaje, string $cli, int $max = 1500): string
    {
        $cli = trim($cli);
        if ($cli === '') {
            return $mensaje;
        }
        if (mb_strlen($cli) > $max) {
            $cli = mb_substr($cli, 0, $max)."\n…";
        }

        return $mensaje."\n\nSalida CLI:\n".$cli;
    }

    private function previewDesdeMensaje(string $message): ?string
    {
        foreach (['Respuesta del equipo:', 'Salida CLI:', 'Vista previa:'] as $marker) {
            $pos = mb_stripos($message, $marker);
            if ($pos !== false) {
                $preview = trim(mb_substr($message, $pos + mb_strlen($marker)));

                return $preview !== '' ? $preview : null;
            }
        }

        return null;
    }

    private function mensajeSinDump(string $message): string
    {
        foreach (['Respuesta del equipo:', 'Salida CLI:', 'Vista previa:'] as $marker) {
            $pos = mb_stripos($message, $marker);
            if ($pos !== false) {
                $corto = trim(mb_substr($message, 0, $pos));

                return $corto !== '' ? $corto : $message;
            }
        }

        return $message;
    }
}
