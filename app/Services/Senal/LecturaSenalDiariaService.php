<?php

namespace App\Services\Senal;

use App\Models\Olt;
use App\Models\OltOnu;
use App\Models\SenalLectura;
use App\Models\SenalLecturaFallo;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Models\TipoTecnologia;
use App\Services\Olt\VsolGponClient;
use App\Services\Olt\VsolOnuOutputParser;
use App\Services\PedidoNodoOpcionesService;
use App\Services\Ubnt\UbntAntenaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LecturaSenalDiariaService
{
    public function __construct(
        private readonly VsolGponClient $vsol,
        private readonly VsolOnuOutputParser $parser,
        private readonly UbntAntenaService $ubnt,
    ) {}

    /**
     * @return array{lectura_id: int, fibra_leidas: int, fibra_fallidas: int, antena_leidas: int, antena_fallidas: int}
     */
    public function ejecutar(?string $solo = null, ?int $limiteAntena = null): array
    {
        @set_time_limit(0);

        $solo = in_array($solo, ['fibra', 'antena'], true) ? $solo : null;
        $parcial = $solo !== null || ($limiteAntena !== null && $limiteAntena > 0);

        SenalLectura::query()
            ->where('estado', 'corriendo')
            ->where('inicio_at', '<', now()->subHours(3))
            ->update(['estado' => 'error', 'fin_at' => now()]);

        $lectura = SenalLectura::query()->create([
            'inicio_at' => now(),
            'estado' => 'corriendo',
            'parcial' => $parcial,
        ]);

        $notas = [];
        $fibra = ['objetivo' => 0, 'leidas' => 0, 'fallidas' => 0];
        $antena = ['objetivo' => 0, 'leidas' => 0, 'fallidas' => 0];

        try {
            if ($solo !== 'antena') {
                $fibra = $this->leerFibra($lectura);
                foreach ($fibra['notas'] as $nota) {
                    $notas[] = $nota;
                }
                $lectura->update([
                    'fibra_objetivo' => $fibra['objetivo'],
                    'fibra_leidas' => $fibra['leidas'],
                    'fibra_fallidas' => $fibra['fallidas'],
                    'nota' => $notas === [] ? null : implode("\n", $notas),
                ]);
            }
            if ($solo !== 'fibra') {
                $antena = $this->leerAntenas($lectura, $limiteAntena);
                $lectura->update([
                    'antena_objetivo' => $antena['objetivo'],
                    'antena_leidas' => $antena['leidas'],
                    'antena_fallidas' => $antena['fallidas'],
                ]);
            }

            $lectura->update([
                'fin_at' => now(),
                'estado' => 'lista',
                'fibra_objetivo' => $fibra['objetivo'],
                'fibra_leidas' => $fibra['leidas'],
                'fibra_fallidas' => $fibra['fallidas'],
                'antena_objetivo' => $antena['objetivo'],
                'antena_leidas' => $antena['leidas'],
                'antena_fallidas' => $antena['fallidas'],
                'nota' => $notas === [] ? null : implode("\n", $notas),
            ]);
        } catch (Throwable $e) {
            $lectura->update([
                'fin_at' => now(),
                'estado' => 'error',
                'nota' => $e->getMessage(),
            ]);
            Log::error('Lectura diaria de señal interrumpida', [
                'lectura_id' => $lectura->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('Lectura diaria de señal lista', [
            'lectura_id' => $lectura->id,
            'fibra' => $fibra['leidas'].'/'.$fibra['objetivo'],
            'antena' => $antena['leidas'].'/'.$antena['objetivo'],
        ]);

        return [
            'lectura_id' => $lectura->id,
            'parcial' => $parcial,
            'fibra_leidas' => $fibra['leidas'],
            'fibra_fallidas' => $fibra['fallidas'],
            'antena_leidas' => $antena['leidas'],
            'antena_fallidas' => $antena['fallidas'],
        ];
    }

    /**
     * @return array{objetivo: int, leidas: int, fallidas: int, notas: list<string>}
     */
    private function leerFibra(SenalLectura $lectura): array
    {
        $servicios = $this->serviciosActivos(true);
        $ya = $this->yaLeidosHoy(ServicioConexionEvento::TIPO_SENAL_OPTICA);
        $posiciones = $this->ultimaPosicionOptica();
        $catalogo = $this->catalogoOnu();
        $oltCaidas = [];
        $notas = [];

        $olts = Olt::query()->where('estado', 'activo')->get()->filter(fn (Olt $olt) => $olt->tieneCredencialesGestion());
        foreach ($olts as $olt) {
            $etiqueta = $olt->codigo ?: $olt->ip;
            try {
                $blob = $this->vsol->fetchOnuOpticalDiag($olt);
                $filas = $this->parser->parseOptical($blob);
                $puestas = $this->aplicarPotenciaDelDia($olt, $filas, $catalogo);
                $notas[] = "{$etiqueta}: {$puestas} potencias.";
            } catch (Throwable $e) {
                $oltCaidas[(int) $olt->olt_id] = $e->getMessage();
                $notas[] = "{$etiqueta}: no respondió. ".$e->getMessage();
                Log::warning('Lectura óptica diaria: OLT sin respuesta', [
                    'olt_id' => $olt->olt_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $porDescripcion = [];
        foreach ($catalogo as $onu) {
            $clave = $this->normalizar($onu['descripcion']);
            if ($clave === '' || preg_match('/^PON\d+$/', $clave)) {
                continue;
            }
            $porDescripcion[$clave][] = $onu;
        }

        $leidas = 0;
        $fallos = [];
        foreach ($servicios as $servicio) {
            if (isset($ya[$servicio->servicio_id])) {
                $leidas++;

                continue;
            }

            $resultado = $this->resolverOnu($servicio, $posiciones, $catalogo, $porDescripcion, $oltCaidas);
            if ($resultado['ok']) {
                $onu = $resultado['onu'];
                ServicioConexionEvento::registrarSenalOptica($servicio, [
                    'olt_id' => $onu['olt_id'],
                    'pon_port' => $onu['pon_port'],
                    'onu_index' => $onu['onu_index'],
                    'rx_power_dbm' => $onu['rx'],
                    'tx_power_dbm' => $onu['tx'],
                    'estado' => $onu['estado'],
                    'descripcion' => $onu['descripcion'] ?: null,
                    'payload' => ['origen' => 'lectura_diaria', 'via' => 'olt_opm'],
                ], ServicioConexionEvento::FUENTE_CRON);
                if ($servicio->cajaNapPuertoActivo) {
                    $servicio->cajaNapPuertoActivo->update(['potencia_cliente' => $onu['rx']]);
                }
                $leidas++;

                continue;
            }

            $fallos[] = [
                'senal_lectura_id' => $lectura->id,
                'servicio_id' => $servicio->servicio_id,
                'tipo' => SenalLecturaFallo::TIPO_OPTICA,
                'motivo' => $resultado['motivo'],
                'detalle' => mb_substr($resultado['detalle'], 0, 500),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->guardarFallos($fallos);

        return [
            'objetivo' => $servicios->count(),
            'leidas' => $leidas,
            'fallidas' => count($fallos),
            'notas' => $notas,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogo
     * @param  list<array<string, mixed>>  $filas
     */
    private function aplicarPotenciaDelDia(Olt $olt, array $filas, array &$catalogo): int
    {
        $puestas = 0;
        foreach ($filas as $fila) {
            $rx = $fila['rx_power_dbm'] ?? null;
            if (! is_numeric($rx)) {
                continue;
            }
            $pon = (int) ($fila['pon_port'] ?? 0);
            $indice = (int) ($fila['onu_index'] ?? 0);
            if ($pon < 1 || $indice < 1) {
                continue;
            }

            $clave = $this->claveOnu((int) $olt->olt_id, $pon, $indice);
            $tx = isset($fila['tx_power_dbm']) && is_numeric($fila['tx_power_dbm']) ? (float) $fila['tx_power_dbm'] : null;
            $base = $catalogo[$clave] ?? [
                'olt_id' => (int) $olt->olt_id,
                'pon_port' => $pon,
                'onu_index' => $indice,
                'descripcion' => '',
                'estado' => null,
                'tx' => null,
            ];
            $base['rx'] = (float) $rx;
            $base['tx'] = $tx ?? ($base['tx'] ?? null);
            $base['leida_hoy'] = true;
            if ($base['descripcion'] === '' && ! empty($fila['descripcion'])) {
                $base['descripcion'] = (string) $fila['descripcion'];
            }
            $catalogo[$clave] = $base;

            if ($base['rx'] <= -1 && $base['rx'] >= -40) {
                OltOnu::query()
                    ->where('olt_id', $olt->olt_id)
                    ->where('pon_port', $pon)
                    ->where('onu_index', $indice)
                    ->update([
                        'rx_power_dbm' => $base['rx'],
                        'tx_power_dbm' => $base['tx'],
                        'synced_at' => now(),
                    ]);
                $puestas++;
            }
        }

        return $puestas;
    }

    /**
     * @param  array<int, array{olt_id: int, pon_port: int, onu_index: int}>  $posiciones
     * @param  array<string, array<string, mixed>>  $catalogo
     * @param  array<string, list<array<string, mixed>>>  $porDescripcion
     * @param  array<int, string>  $oltCaidas
     * @return array{ok: bool, onu?: array<string, mixed>, motivo?: string, detalle?: string}
     */
    private function resolverOnu(Servicio $servicio, array $posiciones, array $catalogo, array $porDescripcion, array $oltCaidas): array
    {
        $pos = $posiciones[$servicio->servicio_id] ?? null;
        if ($pos) {
            $clave = $this->claveOnu($pos['olt_id'], $pos['pon_port'], $pos['onu_index']);
            $onu = $catalogo[$clave] ?? null;
            if ($onu && ! empty($onu['leida_hoy'])) {
                return $this->aceptarOptica($onu);
            }
            if (isset($oltCaidas[$pos['olt_id']])) {
                return [
                    'ok' => false,
                    'motivo' => 'olt_sin_respuesta',
                    'detalle' => 'OLT '.$pos['olt_id'].' · PON '.$pos['pon_port'].':'.$pos['onu_index'].'. '.$oltCaidas[$pos['olt_id']],
                ];
            }
            if ($onu) {
                return [
                    'ok' => false,
                    'motivo' => 'sin_rx',
                    'detalle' => 'ONU '.$pos['pon_port'].':'.$pos['onu_index'].' sin potencia en la lectura de hoy.',
                ];
            }
        }

        $claveDesc = $this->normalizar((string) $servicio->usuario_pppoe);
        $candidatas = $porDescripcion[$claveDesc] ?? [];
        $conPotencia = array_values(array_filter($candidatas, fn (array $onu) => ! empty($onu['leida_hoy'])));
        if (count($conPotencia) === 1) {
            return $this->aceptarOptica($conPotencia[0]);
        }
        if (count($conPotencia) > 1) {
            return [
                'ok' => false,
                'motivo' => 'asociacion_ambigua',
                'detalle' => 'El usuario PPPoE coincide con '.count($conPotencia).' ONU.',
            ];
        }
        if ($candidatas !== []) {
            $onu = $candidatas[0];
            if (isset($oltCaidas[$onu['olt_id']])) {
                return [
                    'ok' => false,
                    'motivo' => 'olt_sin_respuesta',
                    'detalle' => $oltCaidas[$onu['olt_id']],
                ];
            }

            return [
                'ok' => false,
                'motivo' => 'sin_rx',
                'detalle' => 'La ONU está en el inventario, pero la OLT no devolvió su potencia.',
            ];
        }

        return [
            'ok' => false,
            'motivo' => 'sin_asociar',
            'detalle' => 'No coincide el usuario PPPoE con la descripción de una ONU ni hay un puerto guardado de una lectura anterior.',
        ];
    }

    /**
     * @param  array<string, mixed>  $onu
     * @return array{ok: bool, onu?: array<string, mixed>, motivo?: string, detalle?: string}
     */
    private function aceptarOptica(array $onu): array
    {
        $rx = (float) $onu['rx'];
        if ($rx > -1 || $rx < -40) {
            return [
                'ok' => false,
                'motivo' => 'lectura_invalida',
                'detalle' => 'RX '.$rx.' dBm fuera del rango óptico.',
            ];
        }

        return ['ok' => true, 'onu' => $onu];
    }

    /**
     * @return array{objetivo: int, leidas: int, fallidas: int}
     */
    private function leerAntenas(SenalLectura $lectura, ?int $limite): array
    {
        $servicios = $this->serviciosActivos(false);
        if ($limite !== null && $limite > 0) {
            $servicios = $servicios->take($limite);
        }
        $ya = $this->yaLeidosHoy(ServicioConexionEvento::TIPO_SENAL_ANTENA);
        $leidas = 0;
        $fallos = [];

        foreach ($servicios as $servicio) {
            if (isset($ya[$servicio->servicio_id])) {
                $leidas++;

                continue;
            }

            $ip = trim((string) $servicio->ip);
            if ($ip === '') {
                $fallos[] = $this->falloAntena($lectura, $servicio, 'sin_ip', 'El servicio no tiene IP.');

                continue;
            }

            $mac = filled($servicio->mac_address)
                ? strtoupper(str_replace('-', ':', (string) $servicio->mac_address))
                : null;
            $resultado = $this->ubnt->consultarWstalist($ip, $mac, 8);
            $signal = $resultado['signal_dbm'] ?? null;
            if (! ($resultado['success'] ?? false) || ! is_numeric($signal)) {
                $mensaje = (string) ($resultado['message'] ?? 'Sin respuesta.');
                $motivo = ($resultado['success'] ?? false) ? 'sin_senal' : 'sin_respuesta';
                $fallos[] = $this->falloAntena($lectura, $servicio, $motivo, $ip.' · '.$mensaje);

                continue;
            }

            $rssi = (float) $signal;
            if ($rssi > -15 || $rssi < -100) {
                $fallos[] = $this->falloAntena($lectura, $servicio, 'lectura_invalida', $ip.' · RSSI '.$rssi.' dBm fuera de rango.');

                continue;
            }

            ServicioConexionEvento::registrarSenalAntena($servicio, [
                'ip' => $ip,
                'mac' => $resultado['mac_remota'] ?? $mac,
                'antena_signal_dbm' => $rssi,
                'antena_snr_db' => $resultado['snr_db'] ?? null,
                'antena_radio_iface' => 'wstalist',
                'payload' => [
                    'origen' => 'lectura_diaria',
                    'ccq' => $resultado['ccq'] ?? null,
                    'ap' => $resultado['ap_name'] ?? null,
                ],
            ], ServicioConexionEvento::FUENTE_CRON);
            $leidas++;
        }

        $this->guardarFallos($fallos);

        return [
            'objetivo' => $servicios->count(),
            'leidas' => $leidas,
            'fallidas' => count($fallos),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function falloAntena(SenalLectura $lectura, Servicio $servicio, string $motivo, string $detalle): array
    {
        return [
            'senal_lectura_id' => $lectura->id,
            'servicio_id' => $servicio->servicio_id,
            'tipo' => SenalLecturaFallo::TIPO_ANTENA,
            'motivo' => $motivo,
            'detalle' => mb_substr($detalle, 0, 500),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fallos
     */
    private function guardarFallos(array $fallos): void
    {
        foreach (array_chunk($fallos, 200) as $lote) {
            DB::table('senal_lectura_fallos')->insert($lote);
        }
    }

    private function serviciosActivos(bool $fibra)
    {
        $ids = TipoTecnologia::query()->get()
            ->filter(fn (TipoTecnologia $tipo) => $fibra
                ? PedidoNodoOpcionesService::descripcionEsGpon($tipo->descripcion)
                : PedidoNodoOpcionesService::descripcionEsWireless($tipo->descripcion))
            ->pluck('tecnologia_id')
            ->all();

        return Servicio::query()
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->whereHas('plan', fn ($q) => $q->whereIn('tecnologia_id', $ids === [] ? [0] : $ids))
            ->with(['cajaNapPuertoActivo', 'cliente:cliente_id,nombre,apellido'])
            ->orderBy('servicio_id')
            ->get(['servicio_id', 'cliente_id', 'usuario_pppoe', 'mac_address', 'ip', 'plan_id']);
    }

    /**
     * @return array<int, true>
     */
    private function yaLeidosHoy(string $tipo): array
    {
        return ServicioConexionEvento::query()
            ->where('tipo', $tipo)
            ->where('fuente', ServicioConexionEvento::FUENTE_CRON)
            ->where('ocurrio_at', '>=', now()->startOfDay())
            ->pluck('servicio_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @return array<int, array{olt_id: int, pon_port: int, onu_index: int}>
     */
    private function ultimaPosicionOptica(): array
    {
        $ultimos = DB::table('servicio_conexion_eventos')
            ->selectRaw('servicio_id, MAX(servicio_conexion_evento_id) as id')
            ->where('tipo', ServicioConexionEvento::TIPO_SENAL_OPTICA)
            ->whereNotNull('olt_id')
            ->whereNotNull('pon_port')
            ->whereNotNull('onu_index')
            ->groupBy('servicio_id');

        $filas = DB::table('servicio_conexion_eventos as e')
            ->joinSub($ultimos, 'u', 'u.id', '=', 'e.servicio_conexion_evento_id')
            ->get(['e.servicio_id', 'e.olt_id', 'e.pon_port', 'e.onu_index']);

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->servicio_id] = [
                'olt_id' => (int) $fila->olt_id,
                'pon_port' => (int) $fila->pon_port,
                'onu_index' => (int) $fila->onu_index,
            ];
        }

        return $mapa;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalogoOnu(): array
    {
        $catalogo = [];
        foreach (OltOnu::query()->get(['olt_id', 'pon_port', 'onu_index', 'descripcion', 'estado', 'tx_power_dbm']) as $onu) {
            $clave = $this->claveOnu((int) $onu->olt_id, (int) $onu->pon_port, (int) $onu->onu_index);
            $catalogo[$clave] = [
                'olt_id' => (int) $onu->olt_id,
                'pon_port' => (int) $onu->pon_port,
                'onu_index' => (int) $onu->onu_index,
                'descripcion' => (string) $onu->descripcion,
                'estado' => $onu->estado,
                'tx' => $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null,
                'rx' => null,
                'leida_hoy' => false,
            ];
        }

        return $catalogo;
    }

    private function claveOnu(int $oltId, int $pon, int $indice): string
    {
        return $oltId.':'.$pon.':'.$indice;
    }

    private function normalizar(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));

        return preg_replace('/[^A-Z0-9]+/u', '', $valor) ?? '';
    }
}
