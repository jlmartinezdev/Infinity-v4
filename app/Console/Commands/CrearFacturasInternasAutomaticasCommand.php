<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\FacturacionParametro;
use App\Models\Servicio;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CrearFacturasInternasAutomaticasCommand extends Command
{
    protected $signature = 'facturas:crear-internas-automaticas
                            {--dry-run : Solo mostrar qué facturas se crearían, sin ejecutar}
                            {--force : Ejecutar aunque no sea el día configurado}';

    protected $description = 'Crea facturas internas automáticamente para todos los clientes con servicios activos. Se ejecuta el día configurado en dia_creacion_factura_automatica (Configuración > Facturación). Factura el mes actual.';

    public function handle(FacturacionService $facturacionService): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $diaConfig = FacturacionParametro::diaCreacionFacturaAutomatica();
        $hoy = (int) now()->format('d');

        if (! $force && $hoy !== $diaConfig) {
            $this->info("Hoy es día {$hoy}, no es el día de creación configurado ({$diaConfig}). Ejecutar con --force para forzar.");

            return self::SUCCESS;
        }

        $periodoDesde = Carbon::now()->startOfMonth();
        $periodoHasta = Carbon::now()->endOfMonth();

        $diaVencimiento = FacturacionParametro::diaVencimiento();
        $proximoMes = Carbon::now()->addMonth();
        $fechaVencimiento = Carbon::createFromDate(
            $proximoMes->year,
            $proximoMes->month,
            min($diaVencimiento, $proximoMes->daysInMonth)
        )->toDateString();

        if ($dryRun) {
            $this->info('Modo dry-run: no se crearán facturas.');
            $this->info(sprintf('Período a facturar: %s a %s', $periodoDesde->format('d/m/Y'), $periodoHasta->format('d/m/Y')));
            $this->info(sprintf('Vencimiento: %s (día %d del mes siguiente)', $fechaVencimiento, $diaVencimiento));
        }

        $clientesConServicios = Cliente::whereHas('servicios', function ($q) {
            $q->where('estado', Servicio::ESTADO_ACTIVO);
        })->get();

        $creadas = 0;
        $omitidos = 0;
        $errores = 0;

        foreach ($clientesConServicios as $cliente) {
            $yaTiene = FacturaInterna::where('cliente_id', $cliente->cliente_id)
                ->where('periodo_hasta', $periodoHasta->toDateString())
                ->exists();

            if ($yaTiene) {
                $omitidos++;
                if ($dryRun) {
                    $this->line("  - Omitido: Cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido}) ya tiene factura para el período.");
                }
                continue;
            }

            try {
                if ($dryRun) {
                    $this->line("  - Crearía factura para: Cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido})");
                    $creadas++;
                    continue;
                }

                $facturacionService->generarFacturaInterna($cliente, $periodoDesde, $periodoHasta, null, 'pendiente', $fechaVencimiento);
                $creadas++;
                $this->line("  ✓ Factura creada para Cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido})");
            } catch (\Throwable $e) {
                $errores++;
                $this->error("  ✗ Error Cliente {$cliente->cliente_id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Finalizado: %d facturas creadas, %d omitidas (ya existían), %d errores.',
            $creadas,
            $omitidos,
            $errores
        ));

        return self::SUCCESS;
    }
}
