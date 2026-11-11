<?php

namespace App\Support;

use App\Models\ServicioConexionEvento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Números del dashboard de Eventos PPPoE, sobre una consulta de eventos ya filtrada
 * (período, fuente, router, búsqueda).
 */
class PppoeEventosResumen
{
    /**
     * @param  Builder<ServicioConexionEvento>  $eventos
     * @return array{total: int, conexiones: int, desconexiones: int, clientes_afectados: int}
     */
    public static function totales(Builder $eventos): array
    {
        $fila = (clone $eventos)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN servicio_conexion_eventos.tipo = ? THEN 1 ELSE 0 END) as desconexiones',
                [ServicioConexionEvento::TIPO_PPPOE_DOWN]
            )
            ->first();

        $total = (int) ($fila->total ?? 0);
        $desconexiones = (int) ($fila->desconexiones ?? 0);

        // Clientes con al menos una desconexión en el período.
        $clientesAfectados = (clone $eventos)
            ->where('servicio_conexion_eventos.tipo', ServicioConexionEvento::TIPO_PPPOE_DOWN)
            ->join('servicios', 'servicios.servicio_id', '=', 'servicio_conexion_eventos.servicio_id')
            ->distinct()
            ->count('servicios.cliente_id');

        return [
            'total' => $total,
            'conexiones' => $total - $desconexiones,
            'desconexiones' => $desconexiones,
            'clientes_afectados' => (int) $clientesAfectados,
        ];
    }

    /**
     * Eventos por router, de más a menos desconexiones.
     *
     * @param  Builder<ServicioConexionEvento>  $eventos
     * @param  array<int|string, string>  $nombres  router_id => nombre
     * @return list<array{router_id: ?int, nombre: string, total: int, conexiones: int, desconexiones: int, pct: float}>
     */
    public static function porRouter(Builder $eventos, array $nombres = []): array
    {
        $filas = (clone $eventos)->toBase()
            ->select('servicio_conexion_eventos.router_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN servicio_conexion_eventos.tipo = ? THEN 1 ELSE 0 END) as desconexiones',
                [ServicioConexionEvento::TIPO_PPPOE_DOWN]
            )
            ->groupBy('servicio_conexion_eventos.router_id')
            ->get();

        $maximo = max(1, (int) $filas->max('desconexiones'));

        return $filas
            ->map(function ($fila) use ($nombres, $maximo) {
                $routerId = $fila->router_id !== null ? (int) $fila->router_id : null;
                $total = (int) $fila->total;
                $desconexiones = (int) $fila->desconexiones;

                return [
                    'router_id' => $routerId,
                    'nombre' => $routerId === null
                        ? 'Sin router'
                        : ($nombres[$routerId] ?? 'Router #'.$routerId),
                    'total' => $total,
                    'conexiones' => $total - $desconexiones,
                    'desconexiones' => $desconexiones,
                    'pct' => round(($desconexiones / $maximo) * 100, 1),
                ];
            })
            ->sort(fn ($a, $b) => [$b['desconexiones'], $b['total'], $a['nombre']] <=> [$a['desconexiones'], $a['total'], $b['nombre']])
            ->values()
            ->all();
    }
}
