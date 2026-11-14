<?php

namespace App\Services\Senal;

use App\Models\OltOnu;
use App\Models\SenalLectura;
use App\Models\SenalLecturaFallo;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Support\ClaveOnuDescripcion;
use Illuminate\Support\Facades\DB;

class InformeAsociacionOptica
{
    /**
     * @return array{
     *     sin_asociar: list<array<string, mixed>>,
     *     sin_rx: list<array<string, mixed>>,
     *     asociacion_ambigua: list<array<string, mixed>>
     * }
     */
    public function armar(SenalLectura $lectura): array
    {
        $fallos = $lectura->fallos()
            ->where('tipo', SenalLecturaFallo::TIPO_OPTICA)
            ->whereIn('motivo', ['sin_asociar', 'sin_rx', 'asociacion_ambigua'])
            ->with(['servicio.cliente:cliente_id,nombre,apellido'])
            ->orderBy('id')
            ->get();

        $grupos = [
            'sin_asociar' => [],
            'sin_rx' => [],
            'asociacion_ambigua' => [],
        ];
        if ($fallos->isEmpty()) {
            return $grupos;
        }

        $servicioIds = $fallos->pluck('servicio_id')->map(fn ($id) => (int) $id)->all();
        $posiciones = $this->posiciones($servicioIds);
        [$catalogo, $porDescripcion] = $this->catalogo($lectura);

        foreach ($fallos as $fallo) {
            $servicio = $fallo->servicio;
            $pppoe = trim((string) ($servicio?->usuario_pppoe ?? ''));
            $fila = [
                'servicio_id' => (int) $fallo->servicio_id,
                'cliente_id' => $servicio?->cliente?->cliente_id,
                'cliente' => $this->nombre($servicio),
                'pppoe' => $pppoe !== '' ? $pppoe : '—',
                'onus' => [],
            ];

            $porNombre = $this->onusDelServicio($servicio, $porDescripcion);

            if ($fallo->motivo === 'sin_asociar' && $porNombre !== []) {
                if (count($porNombre) === 1) {
                    continue;
                }
                $fila['onus'] = $porNombre;
                $grupos['asociacion_ambigua'][] = $fila;

                continue;
            }

            if ($fallo->motivo === 'asociacion_ambigua') {
                $leidas = array_values(array_filter($porNombre, fn (array $onu) => $onu['leida']));
                $fila['onus'] = $leidas !== [] ? $leidas : $porNombre;
            } elseif ($fallo->motivo === 'sin_rx') {
                $pos = $posiciones[(int) $fallo->servicio_id] ?? null;
                if ($pos && isset($catalogo[$pos])) {
                    $fila['onus'] = [$catalogo[$pos]];
                } elseif ($porNombre !== []) {
                    $fila['onus'] = [reset($porNombre)];
                }
            }

            $grupos[$fallo->motivo][] = $fila;
        }

        foreach ($grupos as &$filas) {
            usort($filas, fn (array $a, array $b) => strnatcasecmp($a['cliente'], $b['cliente']));
        }

        return $grupos;
    }

    private function nombre(?Servicio $servicio): string
    {
        $cliente = $servicio?->cliente;
        $nombre = trim(((string) ($cliente->nombre ?? '')).' '.((string) ($cliente->apellido ?? '')));
        if ($nombre !== '') {
            return $nombre;
        }
        if ($cliente) {
            return 'Cliente #'.$cliente->cliente_id;
        }

        return 'Servicio #'.(int) ($servicio?->servicio_id ?? 0);
    }

    /**
     * @param  list<int>  $servicioIds
     * @return array<int, string>
     */
    private function posiciones(array $servicioIds): array
    {
        if ($servicioIds === []) {
            return [];
        }

        $ultimos = DB::table('servicio_conexion_eventos')
            ->selectRaw('servicio_id, MAX(servicio_conexion_evento_id) as id')
            ->where('tipo', ServicioConexionEvento::TIPO_SENAL_OPTICA)
            ->whereNotNull('olt_id')
            ->whereNotNull('pon_port')
            ->whereNotNull('onu_index')
            ->whereIn('servicio_id', $servicioIds)
            ->groupBy('servicio_id');

        $filas = DB::table('servicio_conexion_eventos as e')
            ->joinSub($ultimos, 'u', 'u.id', '=', 'e.servicio_conexion_evento_id')
            ->get(['e.servicio_id', 'e.olt_id', 'e.pon_port', 'e.onu_index']);

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[(int) $fila->servicio_id] = (int) $fila->olt_id.':'.(int) $fila->pon_port.':'.(int) $fila->onu_index;
        }

        return $mapa;
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, list<array<string, mixed>>>}
     */
    private function catalogo(SenalLectura $lectura): array
    {
        $desde = $lectura->inicio_at;
        $hasta = ($lectura->fin_at ?? now())->copy()->addMinutes(2);
        $catalogo = [];
        $porDescripcion = [];

        $onus = OltOnu::query()->with('olt:olt_id,codigo')->get();
        foreach ($onus as $onu) {
            $descripcion = trim((string) $onu->descripcion);
            $fila = [
                'olt' => $onu->olt?->codigo ?: ('OLT '.$onu->olt_id),
                'pon' => (int) $onu->pon_port,
                'indice' => (int) $onu->onu_index,
                'descripcion' => $descripcion !== '' ? $descripcion : '—',
                'estado' => $onu->estadoEtiqueta(),
                'rx' => $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2, '.', '') : null,
                'leida' => $onu->synced_at !== null && $desde !== null && $onu->synced_at->between($desde, $hasta),
            ];
            $clave = (int) $onu->olt_id.':'.$fila['pon'].':'.$fila['indice'];
            $catalogo[$clave] = $fila;

            $normal = $this->normalizar($descripcion);
            if ($normal === '' || preg_match('/^PON\d+$/', $normal)) {
                continue;
            }
            $porDescripcion[$normal][] = $fila;
        }

        return [$catalogo, $porDescripcion];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $porDescripcion
     * @return list<array<string, mixed>>
     */
    private function onusDelServicio(?Servicio $servicio, array $porDescripcion): array
    {
        if ($servicio === null) {
            return [];
        }

        $porPppoe = $this->onusDeClaves($porDescripcion, ClaveOnuDescripcion::claves((string) $servicio->usuario_pppoe));
        if ($porPppoe !== []) {
            return $porPppoe;
        }

        $cliente = $servicio->cliente;
        $nombre = trim(((string) ($cliente->nombre ?? '')).' '.((string) ($cliente->apellido ?? '')));

        return $this->onusDeClaves($porDescripcion, ClaveOnuDescripcion::claves($nombre));
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $porDescripcion
     * @param  list<string>  $claves
     * @return list<array<string, mixed>>
     */
    private function onusDeClaves(array $porDescripcion, array $claves): array
    {
        $mapa = [];
        foreach ($claves as $clave) {
            foreach ($porDescripcion[$clave] ?? [] as $onu) {
                $mapa[$onu['olt'].'|'.$onu['pon'].'|'.$onu['indice']] = $onu;
            }
        }

        return array_values($mapa);
    }

    private function normalizar(string $valor): string
    {
        return ClaveOnuDescripcion::de($valor);
    }
}
