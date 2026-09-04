<?php

namespace App\Services\Monitoreo;

use App\Models\MonitoreoPingServicio;
use App\Models\Router;
use App\Models\Servicio;
use App\Services\MikroTikService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServicioPingMonitoreoService
{
    public function __construct(
        private MikroTikService $mikroTik
    ) {}

    /**
     * Consulta sesiones PPPoE activas en cada MikroTik y actualiza el estado de los servicios.
     *
     * @return array{procesados: int, en_linea: int, sin_respuesta: int, omitidos: int, errores: int}
     */
    public function ejecutarRonda(?int $limite = null, ?int $nodoId = null, ?int $routerId = null): array
    {
        if (! config('monitoreo.habilitado', true)) {
            return ['procesados' => 0, 'en_linea' => 0, 'sin_respuesta' => 0, 'omitidos' => 0, 'errores' => 0];
        }

        $stats = ['procesados' => 0, 'en_linea' => 0, 'sin_respuesta' => 0, 'omitidos' => 0, 'errores' => 0];

        $query = Servicio::query()
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('usuario_pppoe')->where('usuario_pppoe', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('ip')->where('ip', '!=', '');
                });
            })
            ->with(['pool.router'])
            ->orderBy('servicio_id')
            ->select(['servicio_id', 'cliente_id', 'ip', 'usuario_pppoe', 'pool_id']);

        $this->aplicarFiltroNodo($query, $nodoId);
        if ($routerId !== null && $routerId > 0) {
            $query->whereHas('pool', fn ($q) => $q->where('router_id', $routerId));
        }

        if ($limite !== null && $limite > 0) {
            $query->limit($limite);
        }

        $servicios = $query->get();
        $porRouter = $servicios->groupBy(fn (Servicio $s) => (int) ($s->pool?->router_id ?? 0));

        foreach ($porRouter as $routerId => $grupo) {
            $router = $this->routerDelGrupo($grupo);
            if ((int) $routerId <= 0 || $router === null) {
                $stats['omitidos'] += $grupo->count();

                continue;
            }

            $sesiones = $this->mikroTik->listarSesionesPppoeActivas($router);
            if (! ($sesiones['success'] ?? false)) {
                $stats['errores'] += $grupo->count();

                continue;
            }

            $byName = $sesiones['by_name'] ?? [];
            $byAddress = $sesiones['by_address'] ?? [];

            foreach ($grupo as $servicio) {
                $usuario = strtolower(trim((string) $servicio->usuario_pppoe));
                $ip = trim((string) $servicio->ip);
                if (str_contains($ip, '/')) {
                    $ip = explode('/', $ip, 2)[0];
                }

                if ($usuario === '' && $ip === '') {
                    $stats['omitidos']++;

                    continue;
                }

                $sesion = null;
                if ($usuario !== '') {
                    $sesion = $byName[$usuario]
                        ?? $byName[str_replace(' ', '_', $usuario)]
                        ?? $byName[str_replace('_', ' ', $usuario)]
                        ?? null;
                }
                if ($sesion === null && $ip !== '' && isset($byAddress[$ip])) {
                    $sesion = $byAddress[$ip];
                }

                $enLinea = $sesion !== null;
                $stats['procesados']++;
                if ($enLinea) {
                    $stats['en_linea']++;
                } else {
                    $stats['sin_respuesta']++;
                }

                $ipGuardar = $ip !== '' ? $ip : (string) ($sesion['address'] ?? '');

                MonitoreoPingServicio::query()->updateOrCreate(
                    ['servicio_id' => $servicio->servicio_id],
                    [
                        'cliente_id' => $servicio->cliente_id,
                        'ip' => $ipGuardar,
                        'en_linea' => $enLinea,
                        'latencia_ms' => null,
                        'verificado_at' => now(),
                        'error' => $enLinea ? null : 'PPPoE no activo en MikroTik',
                    ]
                );
            }
        }

        return $stats;
    }

    /**
     * Estado agregado de PPPoE por cliente (solo servicios activos monitoreados).
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
            ->get(['servicio_id', 'cliente_id', 'ip', 'usuario_pppoe'])
            ->groupBy('cliente_id');

        $resultado = [];
        foreach ($clienteIds as $clienteId) {
            $clienteId = (int) $clienteId;
            $serviciosCliente = $activosPorCliente->get($clienteId, collect());
            $monitoreables = $serviciosCliente->filter(
                fn (Servicio $s) => $this->servicioEsMonitoreable($s)
            );

            if ($monitoreables->isEmpty()) {
                continue;
            }

            $registros = $pings->get($clienteId, collect());
            $online = $registros->where('en_linea', true)->count();
            $totalPing = $registros->count();
            $totalEsperado = $monitoreables->count();

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
     * Conteos de servicios activos y online (PPPoE) agrupados por router_id del pool.
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

    private function servicioEsMonitoreable(Servicio $servicio): bool
    {
        return trim((string) $servicio->usuario_pppoe) !== ''
            || trim((string) $servicio->ip) !== '';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Servicio>  $grupo
     */
    private function routerDelGrupo($grupo): ?Router
    {
        $router = $grupo->first()?->pool?->router;

        return $router instanceof Router ? $router : null;
    }
}
