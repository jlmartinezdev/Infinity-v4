<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\Monitoreo\PingExecutor;
use App\Services\Monitoreo\RouterPingStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PingRoutersCommand extends Command
{
    protected $signature = 'mikrotik:ping-routers
                            {--router= : Solo un router_id}';

    protected $description = 'Hace ping ICMP a la IP de gestión de cada router y actualiza estado (conectado/desconectado).';

    public function handle(PingExecutor $ping, RouterPingStatusService $status): int
    {
        $query = Router::query()->orderBy('router_id');
        if ($this->option('router') !== null && $this->option('router') !== '') {
            $query->where('router_id', (int) $this->option('router'));
        }

        $routers = $query->get();
        if ($routers->isEmpty()) {
            $this->warn('No hay routers para pingear.');

            return self::SUCCESS;
        }

        $online = 0;
        $offline = 0;
        $omitidos = 0;
        $alertas = 0;

        foreach ($routers as $router) {
            if (! $ping->ipEsPinguable($router->ip)) {
                $omitidos++;
                $status->aplicarResultado($router, ['ok' => false, 'latency_ms' => null, 'error' => 'IP inválida'], true);
                $this->line("  #{$router->router_id} {$router->nombre}: IP inválida → desconocido");
                continue;
            }

            $result = $ping->ping($router->ip);
            $antesAlerta = (bool) ($router->ping_alerta_enviada ?? false);
            $status->aplicarResultado($router, $result, false);
            $router->refresh();

            if ($result['ok']) {
                $online++;
                $lat = $result['latency_ms'] !== null ? " {$result['latency_ms']}ms" : '';
                $this->info("  #{$router->router_id} {$router->nombre} ({$router->ip}): conectado{$lat}");
            } else {
                $offline++;
                $fallos = (int) ($router->ping_fallos_seguidos ?? 0);
                $this->warn("  #{$router->router_id} {$router->nombre} ({$router->ip}): desconectado — fallos={$fallos} — ".($result['error'] ?? 'sin respuesta'));
                if (! $antesAlerta && (bool) ($router->ping_alerta_enviada ?? false)) {
                    $alertas++;
                }
            }
        }

        Log::info('[mikrotik:ping-routers] ronda', [
            'online' => $online,
            'offline' => $offline,
            'omitidos' => $omitidos,
            'alertas_whatsapp' => $alertas,
            'total' => $routers->count(),
        ]);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total', $routers->count()],
                ['Conectados', $online],
                ['Desconectados', $offline],
                ['Omitidos', $omitidos],
                ['Alertas WhatsApp', $alertas],
            ]
        );

        return self::SUCCESS;
    }
}
