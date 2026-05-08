<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Models\Servicio;
use App\Services\MikroTikService;
use Illuminate\Console\Command;
use Throwable;

class VerificarServiciosSuspendidosMikrotikCommand extends Command
{
    protected $signature = 'mikrotik:verificar-servicios-suspendidos
                            {router_id? : ID del router (opcional; si no se pasa, verifica todos)}
                            {--solo-inconsistencias : Mostrar unicamente servicios con diferencia entre BD y MikroTik}';

    protected $description = 'Verifica que los servicios suspendidos en BD esten deshabilitados (disabled=yes) en MikroTik.';

    public function handle(MikroTikService $mikrotik): int
    {
        $routerId = $this->argument('router_id');
        $soloInconsistencias = (bool) $this->option('solo-inconsistencias');

        $routers = $routerId
            ? Router::where('router_id', (int) $routerId)->get()
            : Router::with('routerIpPools')->get();

        if ($routers->isEmpty()) {
            $this->error('No se encontro ningun router para verificar.');

            return self::FAILURE;
        }

        $totalVerificados = 0;
        $totalOk = 0;
        $totalInconsistencias = 0;
        $totalNoEncontrados = 0;
        $totalErrores = 0;

        foreach ($routers as $router) {
            $poolIds = $router->routerIpPools()->pluck('pool_id')->all();

            if (empty($poolIds)) {
                $this->line("Router {$router->router_id} ({$router->nombre}): sin pools asociadas, se omite.");
                continue;
            }

            $servicios = Servicio::query()
                ->whereIn('pool_id', $poolIds)
                ->where('estado', Servicio::ESTADO_SUSPENDIDO)
                ->whereNotNull('usuario_pppoe')
                ->where('usuario_pppoe', '!=', '')
                ->orderBy('servicio_id')
                ->get(['servicio_id', 'cliente_id', 'usuario_pppoe', 'estado']);

            $this->newLine();
            $this->info("Router {$router->router_id}: {$router->nombre} ({$router->ip})");
            $this->line('Servicios suspendidos a verificar: '.$servicios->count());

            if ($servicios->isEmpty()) {
                continue;
            }

            foreach ($servicios as $servicio) {
                $totalVerificados++;

                try {
                    $secret = $mikrotik->getPppoeSecretByName($router, (string) $servicio->usuario_pppoe);
                } catch (Throwable $e) {
                    $totalErrores++;
                    $totalInconsistencias++;
                    $this->error("  [ERROR] Servicio {$servicio->servicio_id} ({$servicio->usuario_pppoe}): {$e->getMessage()}");
                    continue;
                }

                if (! $secret) {
                    $totalNoEncontrados++;
                    $totalInconsistencias++;
                    $this->warn("  [NO-ENCONTRADO] Servicio {$servicio->servicio_id} ({$servicio->usuario_pppoe}) no existe en MikroTik.");
                    continue;
                }

                $disabled = strtolower((string) ($secret['disabled'] ?? 'no'));
                $estaSuspendidoEnMikrotik = $disabled === 'yes' || $disabled === 'true';

                if ($estaSuspendidoEnMikrotik) {
                    $totalOk++;
                    if (! $soloInconsistencias) {
                        $this->line("  [OK] Servicio {$servicio->servicio_id} ({$servicio->usuario_pppoe}) -> disabled=yes");
                    }
                } else {
                    $totalInconsistencias++;
                    $this->warn("  [DIF] Servicio {$servicio->servicio_id} ({$servicio->usuario_pppoe}) esta suspendido en BD pero en MikroTik disabled={$disabled}.");
                }
            }
        }

        $this->newLine();
        $this->info('Resumen verificacion suspendidos MikroTik');
        $this->line("Total verificados: {$totalVerificados}");
        $this->line("OK (disabled=yes): {$totalOk}");
        $this->line("Inconsistencias: {$totalInconsistencias}");
        $this->line("No encontrados en router: {$totalNoEncontrados}");
        $this->line("Errores de conexion/API: {$totalErrores}");

        if ($totalInconsistencias > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
