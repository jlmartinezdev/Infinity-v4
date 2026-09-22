<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\HotspotPortalDeployService;
use App\Services\MikroTikService;
use Illuminate\Console\Command;

class HotspotPortalMikrotikCommand extends Command
{
    protected $signature = 'hotspot:portal-mikrotik
                            {nombres* : Nombres de router (ej. MK-N2-BORDE)}
                            {--trial=30m : Duración del acceso de prueba en el perfil hotspot}';

    protected $description = 'Carga el portal cautivo de hotspot/ al MikroTik (HTML del login).';

    public function handle(HotspotPortalDeployService $portal, MikroTikService $mikrotik): int
    {
        $nombres = $this->argument('nombres');
        $routers = Router::query()
            ->where(function ($q) use ($nombres) {
                foreach ($nombres as $nombre) {
                    $q->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $nombre)]);
                }
            })
            ->get();

        if ($routers->isEmpty()) {
            $this->error('No se encontró ningún router con esos nombres.');

            return self::FAILURE;
        }

        $fail = 0;
        foreach ($routers as $router) {
            $this->line("{$router->nombre} ({$router->ip}): subiendo portal cautivo…");
            $result = $portal->desplegar($router, null, (string) $this->option('trial'));
            if (! empty($result['success'])) {
                $this->info('  '.$result['subidos'].' archivos en hotspot/');
                $dns = $mikrotik->configurarDnsPortalHotspot($router);
                if (! empty($dns['success'])) {
                    $this->info('  DNS '.$dns['url'].' → '.$dns['address']);
                } else {
                    $this->warn('  DNS: '.($dns['error'] ?? 'no se pudo publicar'));
                }
            } else {
                $fail++;
                $this->error('  '.($result['error'] ?? 'error'));
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
