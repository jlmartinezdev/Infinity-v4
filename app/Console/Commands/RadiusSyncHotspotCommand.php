<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\ServicioHotspot;
use App\Services\RadiusHotspotSyncService;
use Illuminate\Console\Command;

class RadiusSyncHotspotCommand extends Command
{
    protected $signature = 'radius:sync-hotspot
                            {--router_id= : Solo NAS y usuarios de este router}';

    protected $description = 'Escribe usuarios hotspot y NAS (routers) en las tablas FreeRADIUS.';

    public function handle(RadiusHotspotSyncService $radius): int
    {
        if (! $radius->enabled()) {
            $this->warn('RADIUS_ENABLED no está activo. Definí RADIUS_ENABLED=true en .env.');

            return self::FAILURE;
        }

        $routerId = $this->option('router_id');
        $routers = $routerId
            ? Router::where('router_id', $routerId)->get()
            : Router::orderBy('nombre')->get();

        foreach ($routers as $router) {
            $radius->syncNas($router);
            $this->line("NAS: {$router->nombre} ({$router->ip})");
        }

        $query = ServicioHotspot::with(['servicio', 'hotspotPerfil', 'router']);
        if ($routerId) {
            $query->where('router_id', $routerId);
        }

        $ok = 0;
        $fail = 0;
        foreach ($query->get() as $hotspot) {
            $result = $radius->sync($hotspot);
            if (! empty($result['success'])) {
                $ok++;
            } else {
                $fail++;
                $this->warn("  {$hotspot->username}: ".($result['error'] ?? 'error'));
            }
        }

        $this->info("Sincronización RADIUS: {$ok} ok, {$fail} con error.");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
