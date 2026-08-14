<?php

namespace App\Services\Monitoreo;

use App\Models\MonitoreoPingServicio;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServicioPingMonitoreoService
{
    public function __construct(
        private PingExecutor $pingExecutor
    ) {}

    /**
     * Ejecuta ping a servicios activos con IP válida.
     *
     * @return array{procesados: int, en_linea: int, sin_respuesta: int, omitidos: int}
     */
    public function ejecutarRonda(?int $limite = null, ?int $nodoId = null): array
    {
        if (! config('monitoreo.habilitado', true)) {
            return ['procesados' => 0, 'en_linea' => 0, 'sin_respuesta' => 0, 'omitidos' => 0];
        }

        $stats = ['procesados' => 0, 'en_linea' => 0, 'sin_respuesta' => 0, 'omitidos' => 0];
        $lote = (int) config('monitoreo.lote', 40);

        $query = Servicio::query()
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->select(['servicio_id', 'cliente_id', 'ip'])
            ->orderBy('servicio_id');

        $this->aplicarFiltroNodo($query, $nodoId);

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $query->chunkById($lote, function ($servicios) use (&$stats) {
            foreach ($servicios as $servicio) {
                $ip = trim((string) $servicio->ip);
                if (! $this->pingExecutor->ipEsPinguable($ip)) {
                    $stats['omitidos']++;

                    continue;
                }

                $resultado = $this->pingExecutor->ping($ip);
                $stats['procesados']++;
                if ($resultado['ok']) {
                    $stats['en_linea']++;
                } else {
                    $stats['sin_respuesta']++;
                }

                MonitoreoPingServicio::query()->updateOrCreate(
                    ['servicio_id' => $servicio->servicio_id],
                    [
                        'cliente_id' => $servicio->cliente_id,
                        'ip' => $ip,
                        'en_linea' => $resultado['ok'],
                        'latencia_ms' => $resultado['latency_ms'],
                        'verificado_at' => now(),
                        'error' => $resultado['error'],
                    ]
                );
            }
        }, 'servicio_id');

        return $stats;
    }

    /**
     * Estado agregado de ping por cliente (solo servicios activos monitoreados).
     *
     * @param  list<int>  $clienteIds
     * @return array<int, array{estado: string, en_linea: int, total: int, latencia_ms: int|null, verificado_at: string|null}>
     */
    public function estadosPorClientes(array $clienteIds, ?int $nodoId = null): array
    {
        if ($clienteIds === []) {
            return [];
        }

        $serviciosActivosQuery = Servicio::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', Servicio::ESTADO_ACTIVO);

        $this->aplicarFiltroNodo($serviciosActivosQuery, $nodoId);

        $servicioIds = $serviciosActivosQuery->pluck('servicio_id');

        if ($servicioIds->isEmpty()) {
            return [];
        }

        $pings = MonitoreoPingServicio::query()
            ->whereIn('servicio_id', $servicioIds)
            ->get()
            ->groupBy('cliente_id');

        $activosPorCliente = Servicio::query()
            ->whereIn('servicio_id', $servicioIds)
            ->get(['servicio_id', 'cliente_id', 'ip'])
            ->groupBy('cliente_id');

        $resultado = [];
        foreach ($clienteIds as $clienteId) {
            $clienteId = (int) $clienteId;
            $serviciosCliente = $activosPorCliente->get($clienteId, collect());
            $pingables = $serviciosCliente->filter(
                fn (Servicio $s) => $this->pingExecutor->ipEsPinguable($s->ip)
            );

            if ($pingables->isEmpty()) {
                continue;
            }

            $registros = $pings->get($clienteId, collect());
            $online = $registros->where('en_linea', true)->count();
            $totalPing = $registros->count();
            $totalEsperado = $pingables->count();

            if ($totalPing === 0) {
                $estado = 'unknown';
            } elseif ($online === $totalEsperado && $totalPing >= $totalEsperado) {
                $estado = 'online';
            } elseif ($online === 0) {
                $estado = 'offline';
            } else {
                $estado = 'mixed';
            }

            $ultimo = $registros->sortByDesc('verificado_at')->first();
            $latencias = $registros->where('en_linea', true)->pluck('latencia_ms')->filter();

            $resultado[$clienteId] = [
                'estado' => $estado,
                'en_linea' => $online,
                'total' => $totalEsperado,
                'latencia_ms' => $latencias->isNotEmpty() ? (int) round($latencias->avg()) : null,
                'verificado_at' => $ultimo?->verificado_at?->toIso8601String(),
            ];
        }

        return $resultado;
    }

    /**
     * @return array{online: int, offline: int, mixed: int, unknown: int}
     */
    public function resumenDesdeEstados(array $estadosPorCliente): array
    {
        $resumen = ['online' => 0, 'offline' => 0, 'mixed' => 0, 'unknown' => 0];
        foreach ($estadosPorCliente as $estado) {
            $clave = $estado['estado'] ?? 'unknown';
            if (isset($resumen[$clave])) {
                $resumen[$clave]++;
            }
        }

        return $resumen;
    }

    /**
     * Conteos de servicios activos y online (ping) agrupados por router_id del pool.
     *
     * @param  list<int>  $routerIds
     * @return array<int, array{activos: int, online: int, offline: int, sin_dato: int}>
     */
    public function conteosPorRouterIds(array $routerIds): array
    {
        $routerIds = array_values(array_unique(array_filter(array_map('intval', $routerIds))));
        if ($routerIds === []) {
            return [];
        }

        if (! Schema::hasTable('monitoreo_ping_servicios')) {
            $soloActivos = DB::table('servicios as s')
                ->join('router_ip_pools as p', 'p.pool_id', '=', 's.pool_id')
                ->where('s.estado', Servicio::ESTADO_ACTIVO)
                ->whereIn('p.router_id', $routerIds)
                ->groupBy('p.router_id')
                ->selectRaw('p.router_id, COUNT(*) as activos')
                ->get();

            $out = [];
            foreach ($soloActivos as $row) {
                $id = (int) $row->router_id;
                $activos = (int) $row->activos;
                $out[$id] = [
                    'activos' => $activos,
                    'online' => 0,
                    'offline' => 0,
                    'sin_dato' => $activos,
                ];
            }

            return $out;
        }

        $rows = DB::table('servicios as s')
            ->join('router_ip_pools as p', 'p.pool_id', '=', 's.pool_id')
            ->leftJoin('monitoreo_ping_servicios as m', 'm.servicio_id', '=', 's.servicio_id')
            ->where('s.estado', Servicio::ESTADO_ACTIVO)
            ->whereIn('p.router_id', $routerIds)
            ->groupBy('p.router_id')
            ->selectRaw('
                p.router_id,
                COUNT(*) as activos,
                SUM(CASE WHEN m.en_linea = 1 THEN 1 ELSE 0 END) as online,
                SUM(CASE WHEN m.en_linea = 0 THEN 1 ELSE 0 END) as offline,
                SUM(CASE WHEN m.servicio_id IS NULL THEN 1 ELSE 0 END) as sin_dato
            ')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->router_id;
            $out[$id] = [
                'activos' => (int) $row->activos,
                'online' => (int) $row->online,
                'offline' => (int) $row->offline,
                'sin_dato' => (int) $row->sin_dato,
            ];
        }

        return $out;
    }

    private function aplicarFiltroNodo(Builder $query, ?int $nodoId): void
    {
        if ($nodoId === null || $nodoId <= 0) {
            return;
        }

        $query->enNodo($nodoId);
    }
}
