<?php

namespace App\Support;

use App\Models\ServicioConexionEvento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PppoeTimeline12h
{
    public const HORAS = 12;

    public const ESTADO_UP = 'up';

    public const ESTADO_DOWN = 'down';

    public const ESTADO_UNKNOWN = 'unknown';

    /**
     * Construye segmentos conectado/desconectado/sin datos para las últimas 12 horas.
     *
     * @return array{
     *     inicio: CarbonInterface,
     *     fin: CarbonInterface,
     *     duracion_segundos: int,
     *     conectado_segundos: int,
     *     desconectado_segundos: int,
     *     sin_datos_segundos: int,
     *     conectado_pct: float,
     *     desconectado_pct: float,
     *     sin_datos_pct: float,
     *     estado_actual: string,
     *     segmentos: list<array{
     *         estado: string,
     *         inicio: CarbonInterface,
     *         fin: CarbonInterface,
     *         left_pct: float,
     *         width_pct: float,
     *         duracion_segundos: int
     *     }>,
     *     marcas: list<array{label: string, left_pct: float}>
     * }
     */
    public static function construir(int $servicioId, ?CarbonInterface $fin = null): array
    {
        $fin = ($fin ?? now())->copy();
        $inicio = $fin->copy()->subHours(self::HORAS);
        $inicioTs = $inicio->getTimestamp();
        $finTs = $fin->getTimestamp();
        $duracionSegundos = max(1, $finTs - $inicioTs);

        $prior = ServicioConexionEvento::query()
            ->where('servicio_id', $servicioId)
            ->whereIn('tipo', [ServicioConexionEvento::TIPO_PPPOE_UP, ServicioConexionEvento::TIPO_PPPOE_DOWN])
            ->where('ocurrio_at', '<', $inicio)
            ->orderByDesc('ocurrio_at')
            ->orderByDesc('servicio_conexion_evento_id')
            ->first();

        /** @var Collection<int, ServicioConexionEvento> $eventos */
        $eventos = ServicioConexionEvento::query()
            ->where('servicio_id', $servicioId)
            ->whereIn('tipo', [ServicioConexionEvento::TIPO_PPPOE_UP, ServicioConexionEvento::TIPO_PPPOE_DOWN])
            ->where('ocurrio_at', '>=', $inicio)
            ->where('ocurrio_at', '<=', $fin)
            ->orderBy('ocurrio_at')
            ->orderBy('servicio_conexion_evento_id')
            ->get();

        $segmentosRaw = [];

        if ($eventos->isEmpty()) {
            if ($prior) {
                $segmentosRaw[] = [
                    'estado' => $prior->tipo === ServicioConexionEvento::TIPO_PPPOE_UP
                        ? self::ESTADO_UP
                        : self::ESTADO_DOWN,
                    'inicio_ts' => $inicioTs,
                    'fin_ts' => $finTs,
                ];
            } else {
                $segmentosRaw[] = [
                    'estado' => self::ESTADO_UNKNOWN,
                    'inicio_ts' => $inicioTs,
                    'fin_ts' => $finTs,
                ];
            }
        } else {
            $cursorTs = $inicioTs;
            $conectado = false;
            $estadoConocido = false;

            foreach ($eventos as $evento) {
                $momentoTs = $evento->ocurrio_at->getTimestamp();
                if ($momentoTs < $inicioTs) {
                    $momentoTs = $inicioTs;
                }
                if ($momentoTs > $finTs) {
                    break;
                }

                if ($momentoTs > $cursorTs) {
                    $segmentosRaw[] = [
                        'estado' => $estadoConocido
                            ? ($conectado ? self::ESTADO_UP : self::ESTADO_DOWN)
                            : self::ESTADO_UNKNOWN,
                        'inicio_ts' => $cursorTs,
                        'fin_ts' => $momentoTs,
                    ];
                }

                $conectado = $evento->tipo === ServicioConexionEvento::TIPO_PPPOE_UP;
                $estadoConocido = true;
                $cursorTs = $momentoTs;
            }

            if ($cursorTs < $finTs) {
                $segmentosRaw[] = [
                    'estado' => $estadoConocido
                        ? ($conectado ? self::ESTADO_UP : self::ESTADO_DOWN)
                        : self::ESTADO_UNKNOWN,
                    'inicio_ts' => $cursorTs,
                    'fin_ts' => $finTs,
                ];
            }
        }

        $conectadoSegundos = 0;
        $desconectadoSegundos = 0;
        $sinDatosSegundos = 0;
        $segmentos = [];

        foreach ($segmentosRaw as $seg) {
            $segundos = max(0, $seg['fin_ts'] - $seg['inicio_ts']);
            if ($segundos <= 0) {
                continue;
            }

            match ($seg['estado']) {
                self::ESTADO_UP => $conectadoSegundos += $segundos,
                self::ESTADO_DOWN => $desconectadoSegundos += $segundos,
                default => $sinDatosSegundos += $segundos,
            };

            $leftPct = (($seg['inicio_ts'] - $inicioTs) / $duracionSegundos) * 100;
            $widthPct = ($segundos / $duracionSegundos) * 100;

            $segmentos[] = [
                'estado' => $seg['estado'],
                'inicio' => $inicio->copy()->setTimestamp($seg['inicio_ts']),
                'fin' => $inicio->copy()->setTimestamp($seg['fin_ts']),
                'left_pct' => round($leftPct, 4),
                'width_pct' => round($widthPct, 4),
                'duracion_segundos' => $segundos,
            ];
        }

        if ($segmentos === []) {
            $segmentos[] = [
                'estado' => self::ESTADO_UNKNOWN,
                'inicio' => $inicio->copy(),
                'fin' => $fin->copy(),
                'left_pct' => 0.0,
                'width_pct' => 100.0,
                'duracion_segundos' => $duracionSegundos,
            ];
            $sinDatosSegundos = $duracionSegundos;
        }

        $estadoActual = self::resolverEstadoActual($eventos, $prior);

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'duracion_segundos' => $duracionSegundos,
            'conectado_segundos' => $conectadoSegundos,
            'desconectado_segundos' => $desconectadoSegundos,
            'sin_datos_segundos' => $sinDatosSegundos,
            'conectado_pct' => round(($conectadoSegundos / $duracionSegundos) * 100, 1),
            'desconectado_pct' => round(($desconectadoSegundos / $duracionSegundos) * 100, 1),
            'sin_datos_pct' => round(($sinDatosSegundos / $duracionSegundos) * 100, 1),
            'estado_actual' => $estadoActual,
            'segmentos' => $segmentos,
            'marcas' => self::marcasTiempo($inicioTs, $finTs, $inicio, $fin),
        ];
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_UP => 'Conectado',
            self::ESTADO_DOWN => 'Desconectado',
            default => 'Sin datos',
        };
    }

    public static function formatearDuracion(int $segundos): string
    {
        if ($segundos <= 0) {
            return '0s';
        }

        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        $resto = $segundos % 60;

        if ($horas > 0) {
            return $minutos > 0 ? "{$horas}h {$minutos}m" : "{$horas}h";
        }
        if ($minutos > 0) {
            return $resto > 0 ? "{$minutos}m {$resto}s" : "{$minutos}m";
        }

        return "{$resto}s";
    }

    /**
     * @param  Collection<int, ServicioConexionEvento>  $eventos
     */
    private static function resolverEstadoActual(Collection $eventos, ?ServicioConexionEvento $prior): string
    {
        if ($eventos->isNotEmpty()) {
            $ultimo = $eventos->last();

            return $ultimo->tipo === ServicioConexionEvento::TIPO_PPPOE_UP
                ? self::ESTADO_UP
                : self::ESTADO_DOWN;
        }

        if ($prior) {
            return $prior->tipo === ServicioConexionEvento::TIPO_PPPOE_UP
                ? self::ESTADO_UP
                : self::ESTADO_DOWN;
        }

        return self::ESTADO_UNKNOWN;
    }

    /**
     * @return list<array{label: string, left_pct: float}>
     */
    private static function marcasTiempo(int $inicioTs, int $finTs, CarbonInterface $inicio, CarbonInterface $fin): array
    {
        $marcas = [];
        $duracionSegundos = max(1, $finTs - $inicioTs);

        foreach ([0, 6, 12] as $horas) {
            $momento = $inicio->copy()->addHours($horas);
            if ($momento->gt($fin)) {
                $momento = $fin->copy();
            }

            $momentoTs = min($finTs, max($inicioTs, $momento->getTimestamp()));
            $leftPct = (($momentoTs - $inicioTs) / $duracionSegundos) * 100;
            $marcas[] = [
                'label' => $momento->format('H:i'),
                'left_pct' => round($leftPct, 2),
            ];
        }

        return $marcas;
    }
}
