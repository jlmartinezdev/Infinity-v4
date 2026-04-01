<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Console\Command;

class SyncMikroTikPppoeCommand extends Command
{
    protected $signature = 'mikrotik:sync-pppoe
                            {router_id? : ID del router (opcional; si no se pasa, sincroniza todos)}
                            {--remove-orphans : Eliminar en el router usuarios PPPoE que ya no están en la BD}';

    protected $description = 'Sincroniza usuarios PPPoE desde la base de datos a los routers MikroTik.';

    public function handle(MikroTikService $mikrotik): int
    {
        $routerId = $this->argument('router_id');
        $removeOrphans = $this->option('remove-orphans');

        $routers = $routerId
            ? Router::where('router_id', $routerId)->get()
            : Router::with('routerIpPools')->get();

        if ($routers->isEmpty()) {
            $this->error('No se encontró ningún router.');

            return self::FAILURE;
        }

        foreach ($routers as $router) {
            $this->info("Sincronizando router: {$router->nombre} ({$router->ip})...");

            $result = $mikrotik->syncPppoeFromDatabase($router, $removeOrphans);

            if ($result['success']) {
                $this->line("  Añadidos: {$result['added']}, Actualizados: {$result['updated']}, Eliminados: {$result['removed']}");
            } else {
                $this->warn('  Errores:');
                foreach ($result['errors'] as $err) {
                    $this->warn("    - {$err}");
                }
            }
        }

        $this->info('Sincronización finalizada.');

        return self::SUCCESS;
    }
}
