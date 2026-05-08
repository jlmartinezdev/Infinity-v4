<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CrearFacturasInternasActivosServicioSuspendidoCommand extends Command
{
    protected $signature = 'facturas:crear-internas-servicio-suspendido-cliente-activo
                            {--dry-run : Solo listar clientes, sin crear facturas}
                            {--fecha-emision=2026-04-20 : Fecha de emisión de la factura (Y-m-d)}
                            {--periodo-desde= : Inicio del período facturado (default: primer día del mes de fecha-emisión)}
                            {--periodo-hasta= : Fin del período facturado (default: último día del mes de fecha-emisión)}
                            {--fecha-vencimiento= : Vencimiento (Y-m-d); si se omite: emisión + dias_vencimiento_factura}';

    protected $description = 'Genera facturas internas para clientes en estado «activo» con al menos un servicio «suspendido», respetando acuerdos de no facturación. Por defecto emisión 2026-04-20 y período calendario de ese mes.';

    public function handle(FacturacionService $facturacionService): int
    {
        $dryRun = $this->option('dry-run');
        $fechaEmisionStr = (string) $this->option('fecha-emision');
        $fechaEmision = Carbon::parse($fechaEmisionStr)->startOfDay();

        $periodoDesdeOpt = $this->option('periodo-desde');
        $periodoHastaOpt = $this->option('periodo-hasta');
        $periodoDesde = $periodoDesdeOpt
            ? Carbon::parse($periodoDesdeOpt)->startOfDay()
            : $fechaEmision->copy()->startOfMonth();
        $periodoHasta = $periodoHastaOpt
            ? Carbon::parse($periodoHastaOpt)->endOfDay()
            : $fechaEmision->copy()->endOfMonth();

        $fechaVencimiento = $this->option('fecha-vencimiento');
        $fechaVencimiento = $fechaVencimiento !== null && $fechaVencimiento !== ''
            ? (string) $fechaVencimiento
            : null;

        if ($dryRun) {
            $this->info('Modo dry-run: no se crearán facturas.');
        }
        $this->info(sprintf(
            'Criterio: cliente estado «activo», servicio «suspendido». Emisión %s, período %s – %s.',
            $fechaEmision->toDateString(),
            $periodoDesde->toDateString(),
            $periodoHasta->toDateString()
        ));

        $clientes = Cliente::query()
            ->where('estado', 'activo')
            ->whereHas('servicios', function ($q) {
                $q->where('estado', Servicio::ESTADO_SUSPENDIDO);
            })
            ->orderBy('cliente_id')
            ->get();

        $creadas = 0;
        $omitidos = 0;
        $errores = 0;

        foreach ($clientes as $cliente) {
            $yaTiene = FacturaInterna::where('cliente_id', $cliente->cliente_id)
                ->where('periodo_hasta', $periodoHasta->toDateString())
                ->exists();

            if ($yaTiene) {
                $omitidos++;
                if ($dryRun) {
                    $this->line("  - Omitido: cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido}) ya tiene factura con periodo_hasta {$periodoHasta->toDateString()}.");
                }
                continue;
            }

            try {
                if ($dryRun) {
                    $this->line("  - Crearía factura: cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido})");
                    $creadas++;
                    continue;
                }

                $facturacionService->generarFacturaInterna(
                    $cliente,
                    $periodoDesde,
                    $periodoHasta,
                    null,
                    'pendiente',
                    $fechaVencimiento,
                    $fechaEmision->toDateString(),
                    [Servicio::ESTADO_SUSPENDIDO]
                );
                $creadas++;
                $this->line("  ✓ Factura creada: cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido})");
            } catch (\Throwable $e) {
                $errores++;
                $this->error("  ✗ Error cliente {$cliente->cliente_id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info(sprintf(
                'Finalizado (dry-run): %d clientes con factura posible, %d omitidos, %d errores.',
                $creadas,
                $omitidos,
                $errores
            ));
        } else {
            $this->info(sprintf(
                'Finalizado: %d facturas creadas, %d omitidas (ya existían para el período), %d errores.',
                $creadas,
                $omitidos,
                $errores
            ));
        }

        return self::SUCCESS;
    }
}
