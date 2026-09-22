<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Console\Command;

class HotspotDnsPortalCommand extends Command
{
    protected $signature = 'hotspot:dns-portal
                            {nombres* : Nombres de router (ej. MK-N2-BORDE)}
                            {--nombre= : Nombre DNS local (default: config hotspot.dns_name)}';

    protected $description = 'Publica wifi.interplus (DNS local) hacia el portal cautivo del hotspot.';

    public function handle(MikroTikService $mikrotik): int
    {
        $dnsName = $this->option('nombre');
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
            $this->line("{$router->nombre} ({$router->ip}): DNS del portal…");
            $result = $mikrotik->configurarDnsPortalHotspot(
                $router,
                is_string($dnsName) && $dnsName !== '' ? $dnsName : null
            );
            if (! empty($result['success'])) {
                $this->info('  '.$result['url'].' → '.$result['address']);
            } else {
                $fail++;
                $this->error('  '.($result['error'] ?? 'error'));
            }
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
