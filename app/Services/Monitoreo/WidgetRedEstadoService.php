<?php

namespace App\Services\Monitoreo;

use App\Models\Nodo;
use App\Models\NodoApWireless;
use App\Models\Olt;
use App\Models\Router;
use Illuminate\Support\Facades\Schema;

/**
 * Estado compacto para el widget de Windows: conteos y caídas actuales.
 * Lee el ping ya guardado. No dispara ping ni avisos.
 */
class WidgetRedEstadoService
{
    /**
     * @return array{
     *     generated_at: string,
     *     counts: array{ap: array{online: int, offline: int, unknown: int}, router: array{online: int, offline: int, unknown: int}, olt: array{online: int, offline: int, unknown: int}},
     *     caidas: list<array{type: string, name: string, nodo: ?string, since: ?string}>,
     *     latencia: array{ap: array{promedio: ?int, max: ?int}, router: array{promedio: ?int, max: ?int}, lentos: list<array{type: string, name: string, ms: int}>}
     * }
     */
    public function payload(): array
    {
        $ap = $this->resumenAps();
        $router = $this->resumenRouters();
        $olt = $this->resumenOlts();

        $caidas = array_merge($ap['caidas'], $router['caidas'], $olt['caidas']);
        usort($caidas, function (array $a, array $b): int {
            $as = $a['since'];
            $bs = $b['since'];
            if ($as === $bs) {
                return strcmp($a['type'].$a['name'], $b['type'].$b['name']);
            }
            if ($as === null) {
                return 1;
            }
            if ($bs === null) {
                return -1;
            }

            return strcmp($as, $bs);
        });

        return [
            'generated_at' => now()->toIso8601String(),
            'counts' => [
                'ap' => $ap['counts'],
                'router' => $router['counts'],
                'olt' => $olt['counts'],
            ],
            'caidas' => array_values($caidas),
            'latencia' => $this->latencia($ap['latencias'], $router['latencias']),
        ];
    }

    /**
     * Última latencia de ping guardada de los equipos en línea. Sin IPs.
     *
     * @param  list<array{type: string, name: string, ms: int}>  $aps
     * @param  list<array{type: string, name: string, ms: int}>  $routers
     * @return array{ap: array{promedio: ?int, max: ?int}, router: array{promedio: ?int, max: ?int}, lentos: list<array{type: string, name: string, ms: int}>}
     */
    private function latencia(array $aps, array $routers): array
    {
        $resumen = function (array $items): array {
            $valores = array_column($items, 'ms');
            if ($valores === []) {
                return ['promedio' => null, 'max' => null];
            }

            return [
                'promedio' => (int) round(array_sum($valores) / count($valores)),
                'max' => max($valores),
            ];
        };

        $todos = array_merge($aps, $routers);
        usort($todos, fn (array $a, array $b): int => $b['ms'] <=> $a['ms'] ?: strcmp($a['name'], $b['name']));

        return [
            'ap' => $resumen($aps),
            'router' => $resumen($routers),
            'lentos' => array_slice($todos, 0, 5),
        ];
    }

    /**
     * @return array{counts: array{online: int, offline: int, unknown: int}, caidas: list<array{type: string, name: string, nodo: ?string, since: ?string}>, latencias: list<array{type: string, name: string, ms: int}>}
     */
    private function resumenAps(): array
    {
        $counts = $this->countsVacios();
        $caidas = [];
        $latencias = [];
        if (! Schema::hasTable('nodo_aps_wireless')) {
            return ['counts' => $counts, 'caidas' => $caidas, 'latencias' => $latencias];
        }

        $tieneDesde = Schema::hasColumn('nodo_aps_wireless', 'ping_caido_desde');
        $tieneLatencia = Schema::hasColumn('nodo_aps_wireless', 'ping_latencia_ms');
        $query = NodoApWireless::query()->with('nodo')->orderBy('nombre');
        if (Schema::hasColumn('nodo_aps_wireless', 'activo')) {
            $query->where('activo', true);
        }

        foreach ($query->get() as $ap) {
            if ($ap->ping_ok === true) {
                $counts['online']++;
                if ($tieneLatencia && $ap->ping_latencia_ms !== null) {
                    $latencias[] = $this->medida('ap', (string) $ap->nombre, (int) $ap->ping_latencia_ms);
                }

                continue;
            }
            if ($ap->ping_ok === false) {
                $counts['offline']++;
                $caidas[] = $this->caida(
                    'ap',
                    (string) $ap->nombre,
                    $ap->nodo,
                    $tieneDesde ? $ap->ping_caido_desde?->toIso8601String() : null,
                );

                continue;
            }
            $counts['unknown']++;
        }

        return ['counts' => $counts, 'caidas' => $caidas, 'latencias' => $latencias];
    }

    /**
     * @return array{counts: array{online: int, offline: int, unknown: int}, caidas: list<array{type: string, name: string, nodo: ?string, since: ?string}>, latencias: list<array{type: string, name: string, ms: int}>}
     */
    private function resumenRouters(): array
    {
        $counts = $this->countsVacios();
        $caidas = [];
        $latencias = [];
        if (! Schema::hasTable('routers')) {
            return ['counts' => $counts, 'caidas' => $caidas, 'latencias' => $latencias];
        }

        $tieneDesde = Schema::hasColumn('routers', 'ping_caido_desde');
        $tieneLatencia = Schema::hasColumn('routers', 'ping_latencia_ms');
        $routers = Router::query()->with('nodo')->orderBy('nombre')->get();

        foreach ($routers as $router) {
            $estado = strtolower(trim((string) $router->estado));
            if ($estado === Router::ESTADO_CONECTADO) {
                $counts['online']++;
                if ($tieneLatencia && $router->ping_latencia_ms !== null) {
                    $latencias[] = $this->medida('router', (string) $router->nombre, (int) $router->ping_latencia_ms);
                }

                continue;
            }
            if ($estado === Router::ESTADO_DESCONECTADO) {
                $counts['offline']++;
                $caidas[] = $this->caida(
                    'router',
                    (string) $router->nombre,
                    $router->nodo,
                    $tieneDesde ? $router->ping_caido_desde?->toIso8601String() : null,
                );

                continue;
            }
            $counts['unknown']++;
        }

        return ['counts' => $counts, 'caidas' => $caidas, 'latencias' => $latencias];
    }

    /**
     * No hay ping ni reachability guardada del OLT.
     * `estado` es administrativo (activo/inactivo). `onus_synced_at` es el último
     * import de ONUs, no un up/down actual. Un error de sync tampoco distingue
     * caída de un fallo de parseo. Sin esa señal, todos quedan unknown.
     *
     * @return array{counts: array{online: int, offline: int, unknown: int}, caidas: list<array{type: string, name: string, nodo: ?string, since: ?string}>}
     */
    private function resumenOlts(): array
    {
        $counts = $this->countsVacios();
        if (! Schema::hasTable('olts')) {
            return ['counts' => $counts, 'caidas' => []];
        }

        $counts['unknown'] = Olt::query()->count();

        return ['counts' => $counts, 'caidas' => []];
    }

    /**
     * @return array{online: int, offline: int, unknown: int}
     */
    private function countsVacios(): array
    {
        return ['online' => 0, 'offline' => 0, 'unknown' => 0];
    }

    /**
     * @return array{type: string, name: string, ms: int}
     */
    private function medida(string $type, string $name, int $ms): array
    {
        $nombre = trim($name);

        return [
            'type' => $type,
            'name' => $nombre !== '' ? $nombre : strtoupper($type),
            'ms' => max(0, $ms),
        ];
    }

    /**
     * @return array{type: string, name: string, nodo: ?string, since: ?string}
     */
    private function caida(string $type, string $name, ?Nodo $nodo, ?string $since): array
    {
        $nombre = trim($name);
        $nodoNombre = trim((string) ($nodo?->descripcion ?? ''));

        return [
            'type' => $type,
            'name' => $nombre !== '' ? $nombre : strtoupper($type),
            'nodo' => $nodoNombre !== '' ? $nodoNombre : null,
            'since' => $since,
        ];
    }
}
