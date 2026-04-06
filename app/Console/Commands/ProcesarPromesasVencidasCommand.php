<?php

namespace App\Console\Commands;

use App\Models\MikrotikOperacionPendiente;
use App\Models\Servicio;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use Illuminate\Console\Command;

class ProcesarPromesasVencidasCommand extends Command
{
    protected $signature = 'promesas:procesar-vencidas
                            {--dry-run : Solo listar, sin suspender ni borrar promesas}';

    protected $description = 'Procesa promesas de pago vencidas: suspende servicios si sigue el saldo y elimina la promesa. Deshabilita PPPoE en MikroTik al suspender.';

    public function handle(FacturacionService $facturacionService, MikroTikService $mikrotik): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->info('Modo dry-run: no se modificará la base de datos.');
        }

        $suspendidos = $facturacionService->procesarPromesasVencidas($dryRun);

        if (empty($suspendidos)) {
            $this->info('No hay promesas vencidas que requieran suspensión, o las facturas ya están pagadas.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Servicios suspendidos por promesa vencida: %d', count($suspendidos)));

        foreach ($suspendidos as $item) {
            $servicio = Servicio::with('pool.router')->find($item['servicio_id']);
            if (! $servicio) {
                continue;
            }
            if ($dryRun) {
                $this->line("  - Servicio {$servicio->servicio_id} (cliente {$item['cliente_id']})");

                continue;
            }
            if ($servicio->usuario_pppoe && $servicio->pool?->router) {
                $result = $mikrotik->setPppoeDisabledEnRouter($servicio, true);
                if ($result['success']) {
                    $this->line("  ✓ MikroTik: PPPoE deshabilitado para {$servicio->usuario_pppoe}");
                } else {
                    $this->warn("  ✗ MikroTik: {$result['error']} para {$servicio->usuario_pppoe}");
                    MikrotikOperacionPendiente::registrarSiFallo(
                        MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED,
                        ['servicio_id' => $servicio->servicio_id, 'disabled' => true],
                        $result['error'] ?? 'Error',
                        'promesas:procesar-vencidas'
                    );
                }
            }
        }

        $this->info('Promesas vencidas procesadas.');

        return self::SUCCESS;
    }
}
