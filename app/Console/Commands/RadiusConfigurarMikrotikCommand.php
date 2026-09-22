<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\MikroTikService;
use App\Services\RadiusHotspotSyncService;
use Illuminate\Console\Command;

class RadiusConfigurarMikrotikCommand extends Command
{
    protected $signature = 'radius:configurar-mikrotik
                            {nombres* : Nombres de router (ej. MK-N2-BORDE MK-N3-FTTH)}
                            {--address= : IP del FreeRADIUS vista por el MikroTik}';

    protected $description = 'Configura /radius y use-radius=yes en hotspot de los MikroTik indicados.';

    public function handle(MikroTikService $mikrotik, RadiusHotspotSyncService $radius): int
    {
        if (! $radius->enabled()) {
            $this->warn('RADIUS_ENABLED no está activo.');

            return self::FAILURE;
        }

        $address = (string) ($this->option('address') ?: config('radius.host'));
        $secret = (string) config('radius.secret');
        $authPort = (int) config('radius.auth_port', 1812);
        $acctPort = (int) config('radius.acct_port', 1813);

        if ($address === '' || $secret === '') {
            $this->error('Falta RADIUS_HOST o RADIUS_SECRET.');

            return self::FAILURE;
        }

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

        $this->info("FreeRADIUS: {$address}:{$authPort}/udp (secret desde .env)");

        $fail = 0;
        foreach ($routers as $router) {
            $radius->syncNas($router);
            $this->line("{$router->nombre} ({$router->ip}): escribiendo NAS y /radius…");
            $result = $mikrotik->configurarRadiusHotspot($router, $address, $secret, $authPort, $acctPort);
            if (! empty($result['success'])) {
                $modo = ! empty($result['actualizado']) ? 'actualizado' : 'creado';
                $this->info("  {$modo}; perfiles hotspot con RADIUS: ".($result['perfiles'] ?? 0));
            } else {
                $fail++;
                $this->error('  '.($result['error'] ?? 'error'));
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
