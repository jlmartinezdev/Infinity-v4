<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\RouterNetworkBackup;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Throwable;

class RouterNetworkBackupController extends Controller
{
    public function index(Request $request)
    {
        $query = RouterNetworkBackup::with('routerOrigen')->orderByDesc('leido_en')->orderByDesc('router_network_backup_id');

        if ($request->filled('router_origen_id') && $request->router_origen_id !== 'todos') {
            $query->where('router_origen_id', $request->router_origen_id);
        }

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('notas', 'like', "%{$q}%");
            });
        }

        $backups = $query->paginate(20)->withQueryString();
        $routers = Router::orderBy('nombre')->get(['router_id', 'nombre', 'ip']);

        return view('sistema.router-network-backups.index', compact('backups', 'routers'));
    }

    public function show(RouterNetworkBackup $router_network_backup)
    {
        $backup = $router_network_backup->load([
            'routerOrigen',
            'addresses' => fn ($q) => $q->orderBy('familia')->orderBy('interface')->orderBy('address'),
            'routes' => fn ($q) => $q->orderBy('familia')->orderBy('dst_address'),
        ]);
        $routers = Router::orderBy('nombre')->get(['router_id', 'nombre', 'ip']);

        return view('sistema.router-network-backups.show', compact('backup', 'routers'));
    }

    public function destroy(RouterNetworkBackup $router_network_backup)
    {
        $nombre = $router_network_backup->nombre ?: '#'.$router_network_backup->router_network_backup_id;
        $router_network_backup->delete();

        return redirect()
            ->route('sistema.router-network-backups.index')
            ->with('success', "Backup «{$nombre}» eliminado.");
    }

    /**
     * Vista previa remota (AJAX) sin guardar.
     */
    public function previewRemote(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $router = Router::where('router_id', $validated['router_id'])->firstOrFail();

        try {
            $ipv4 = $mikrotik->getStaticIpv4Addresses($router);
            $ipv6 = $mikrotik->getStaticIpv6Addresses($router);
            $rutasV4 = $mikrotik->getStaticIpv4Routes($router);
            $rutasV6 = $mikrotik->getStaticIpv6Routes($router);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $mapAddr = static function (array $rows): array {
            return collect($rows)->map(fn ($r) => [
                'address' => (string) ($r['address'] ?? ''),
                'network' => (string) ($r['network'] ?? ''),
                'interface' => (string) ($r['interface'] ?? ''),
                'disabled' => (string) ($r['disabled'] ?? 'no'),
                'comment' => (string) ($r['comment'] ?? ''),
            ])->values()->all();
        };

        $mapRoute = static function (array $rows): array {
            return collect($rows)->map(fn ($r) => [
                'dst_address' => (string) ($r['dst-address'] ?? ''),
                'gateway' => (string) ($r['gateway'] ?? ''),
                'distance' => (string) ($r['distance'] ?? ''),
                'routing_table' => (string) ($r['routing-table'] ?? 'main'),
                'disabled' => (string) ($r['disabled'] ?? 'no'),
                'comment' => (string) ($r['comment'] ?? ''),
            ])->values()->all();
        };

        return response()->json([
            'success' => true,
            'router' => [
                'id' => $router->router_id,
                'nombre' => $router->nombre,
                'ip' => $router->ip,
            ],
            'counts' => [
                'ipv4' => count($ipv4),
                'ipv6' => count($ipv6),
                'rutas_v4' => count($rutasV4),
                'rutas_v6' => count($rutasV6),
            ],
            'ipv4' => $mapAddr($ipv4),
            'ipv6' => $mapAddr($ipv6),
            'rutas_v4' => $mapRoute($rutasV4),
            'rutas_v6' => $mapRoute($rutasV6),
        ]);
    }

    public function importFromRouter(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
            'nombre' => ['nullable', 'string', 'max:128'],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);

        $router = Router::where('router_id', $validated['router_id'])->firstOrFail();
        $result = $mikrotik->importNetworkBackupFromRouter(
            $router,
            $validated['nombre'] ?? null,
            $validated['notas'] ?? null
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo leer la red del router.',
            ], 422);
        }

        /** @var RouterNetworkBackup $backup */
        $backup = $result['backup'];

        return response()->json([
            'success' => true,
            'message' => sprintf(
                'Backup guardado: %d IPv4, %d IPv6, %d rutas v4, %d rutas v6.',
                $backup->cant_ipv4,
                $backup->cant_ipv6,
                $backup->cant_rutas_v4,
                $backup->cant_rutas_v6
            ),
            'backup_id' => $backup->router_network_backup_id,
            'show_url' => route('sistema.router-network-backups.show', $backup),
        ]);
    }

    public function syncToRouter(Request $request, RouterNetworkBackup $router_network_backup, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $destino = Router::where('router_id', $validated['router_id'])->firstOrFail();

        try {
            $result = $mikrotik->syncNetworkBackupToRouter($router_network_backup, $destino);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $msg = sprintf(
            'Sincronizado a %s: %d direcciones, %d rutas.',
            $destino->nombre,
            $result['added_addresses'],
            $result['added_routes']
        );
        if (! empty($result['errors'])) {
            $msg .= "\nErrores:\n".implode("\n", array_slice($result['errors'], 0, 15));
        }

        return response()->json([
            'success' => ($result['success'] ?? false) || (($result['added_addresses'] + $result['added_routes']) > 0),
            'message' => $msg,
            'added_addresses' => $result['added_addresses'],
            'added_routes' => $result['added_routes'],
            'errors' => $result['errors'],
        ], (($result['success'] ?? false) || (($result['added_addresses'] + $result['added_routes']) > 0)) ? 200 : 422);
    }

    /**
     * Exporta el backup como texto tipo RouterOS (para archivo .rsc).
     */
    public function export(RouterNetworkBackup $router_network_backup)
    {
        $backup = $router_network_backup->load(['addresses', 'routes', 'routerOrigen']);
        $lines = [
            '# Infinity network backup',
            '# Origen: '.($backup->routerOrigen?->nombre ?? '-').' '.($backup->routerOrigen?->ip ?? ''),
            '# Fecha: '.($backup->leido_en?->format('Y-m-d H:i:s') ?? '-'),
            '# '.$backup->nombre,
            '',
        ];

        foreach ($backup->addresses->where('familia', 'ipv4') as $a) {
            $parts = [
                '/ip address add',
                'address='.$this->rscQuote($a->address),
                'interface='.$this->rscQuote((string) $a->interface),
            ];
            if ($a->network) {
                $parts[] = 'network='.$this->rscQuote($a->network);
            }
            if ($a->comment) {
                $parts[] = 'comment='.$this->rscQuote($a->comment);
            }
            if ($a->disabled) {
                $parts[] = 'disabled=yes';
            }
            $lines[] = implode(' ', $parts);
        }

        foreach ($backup->addresses->where('familia', 'ipv6') as $a) {
            $parts = [
                '/ipv6 address add',
                'address='.$this->rscQuote($a->address),
                'interface='.$this->rscQuote((string) $a->interface),
            ];
            if ($a->comment) {
                $parts[] = 'comment='.$this->rscQuote($a->comment);
            }
            if ($a->disabled) {
                $parts[] = 'disabled=yes';
            }
            $lines[] = implode(' ', $parts);
        }

        $lines[] = '';

        foreach ($backup->routes->where('familia', 'ipv4') as $r) {
            $parts = [
                '/ip route add',
                'dst-address='.$this->rscQuote($r->dst_address),
            ];
            if ($r->gateway) {
                $parts[] = 'gateway='.$this->rscQuote($r->gateway);
            }
            if ($r->distance !== null) {
                $parts[] = 'distance='.$r->distance;
            }
            if ($r->comment) {
                $parts[] = 'comment='.$this->rscQuote($r->comment);
            }
            if ($r->disabled) {
                $parts[] = 'disabled=yes';
            }
            $lines[] = implode(' ', $parts);
        }

        foreach ($backup->routes->where('familia', 'ipv6') as $r) {
            $parts = [
                '/ipv6 route add',
                'dst-address='.$this->rscQuote($r->dst_address),
            ];
            if ($r->gateway) {
                $parts[] = 'gateway='.$this->rscQuote($r->gateway);
            }
            if ($r->distance !== null) {
                $parts[] = 'distance='.$r->distance;
            }
            if ($r->comment) {
                $parts[] = 'comment='.$this->rscQuote($r->comment);
            }
            if ($r->disabled) {
                $parts[] = 'disabled=yes';
            }
            $lines[] = implode(' ', $parts);
        }

        $slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($backup->routerOrigen?->nombre ?? 'router'));
        $filename = 'network-backup-'.trim($slug, '-').'-'.$backup->router_network_backup_id.'.rsc';

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function rscQuote(string $value): string
    {
        if ($value === '' || preg_match('/[\s"\\\\=]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
