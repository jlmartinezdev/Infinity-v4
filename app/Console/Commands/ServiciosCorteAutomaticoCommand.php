<?php

namespace App\Console\Commands;

use App\Models\FacturacionParametro;
use App\Models\Servicio;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use Illuminate\Console\Command;

class ServiciosCorteAutomaticoCommand extends Command
{
    protected $signature = 'servicios:corte-automatico
                            {--dry-run : Solo mostrar qué servicios se suspenderían, sin ejecutar}
                            {--force : Ejecutar aunque no sea el día de corte configurado}';

    protected $description = 'Suspende servicios por falta de pago (facturas vencidas). Se ejecuta el día y hora configurados. También deshabilita PPPoE en routers MikroTik.';

    public function handle(FacturacionService $facturacionService, MikroTikService $mikrotik): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $diaCorte = FacturacionParametro::diaCorte();
        $hoy = (int) now()->format('d');

        if (! $force && $hoy !== $diaCorte) {
            $this->info("Hoy es día {$hoy}, no es el día de corte configurado ({$diaCorte}). Ejecutar con --force para forzar.");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Modo dry-run: no se realizarán cambios.');
        }

        $suspendidos = $facturacionService->suspenderPorFaltaPago($dryRun);

        if (empty($suspendidos)) {
            $this->info('No hay servicios pendientes de suspender por falta de pago.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Servicios suspendidos en BD: %d', count($suspendidos)));

        foreach ($suspendidos as $item) {
            $servicio = Servicio::with('pool.router')->find($item['servicio_id']);
            if (!$servicio) {
                continue;
            }

            if ($dryRun) {
                $this->line("  - Cliente {$item['cliente_id']}, Servicio {$servicio->servicio_id} ({$servicio->usuario_pppoe})");
                continue;
            }

            // Deshabilitar PPPoE en el router MikroTik si aplica
            if ($servicio->usuario_pppoe && $servicio->pool?->router) {
                $result = $mikrotik->setPppoeDisabledEnRouter($servicio, true);
                if ($result['success']) {
                    $this->line("  ✓ MikroTik: PPPoE deshabilitado para {$servicio->usuario_pppoe}");
                } else {
                    $this->warn("  ✗ MikroTik: {$result['error']} para {$servicio->usuario_pppoe}");
                }
            }
        }

        $this->info('Corte automático finalizado.');

        return self::SUCCESS;
    }
}
