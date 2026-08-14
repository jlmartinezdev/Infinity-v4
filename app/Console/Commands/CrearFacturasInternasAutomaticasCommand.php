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
    /** Estados de servicio que entran en la factura mensual automática (excluye cancelado). */
    private const ESTADOS_SERVICIO_FACTURA_AUTOMATICA = [
        Servicio::ESTADO_ACTIVO,
        Servicio::ESTADO_SUSPENDIDO,
        Servicio::ESTADO_CORTADO,
    ];

    protected $signature = 'facturas:crear-internas-automaticas
                            {--dry-run : Solo mostrar qué facturas se crearían, sin ejecutar}
                            {--force : Ejecutar aunque no sea el día configurado}';

    protected $description = 'Crea facturas internas automáticamente para clientes en estado activo con al menos un servicio asociado. Factura líneas de servicios activos, suspendidos o cortados (no cancelados). Se ejecuta el día configurado en dia_creacion_factura_automatica (Configuración > Facturación). Factura el mes actual.';

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
        $fechaVencimiento = FacturacionParametro::fechaVencimientoMesSiguiente();

        if ($dryRun) {
            $this->info('Modo dry-run: no se crearán facturas.');
            $this->info(sprintf('Período a facturar: %s a %s', $periodoDesde->format('d/m/Y'), $periodoHasta->format('d/m/Y')));
            $this->info(sprintf('Vencimiento: %s (día %d del mes siguiente)', $fechaVencimiento, $diaVencimiento));
        }

        $clientesConServicios = Cliente::query()
            ->where('estado', 'activo')
            ->whereHas('servicios')
            ->orderBy('nombre')
            ->get();

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

                $facturacionService->generarFacturaInterna(
                    $cliente,
                    $periodoDesde,
                    $periodoHasta,
                    null,
                    'pendiente',
                    $fechaVencimiento,
                    null,
                    self::ESTADOS_SERVICIO_FACTURA_AUTOMATICA
                );
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
