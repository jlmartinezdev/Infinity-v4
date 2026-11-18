<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\FacturacionParametro;
use App\Models\Servicio;
use App\Services\FacturacionService;
use App\Services\HotspotCuotaResetService;
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

    protected $description = 'Crea facturas internas el día configurado (el 1) por el período del mes anterior. Omite servicios que ya tienen factura de ese período (instalación o cambio de plan). Factura servicios activos, suspendidos o cortados.';

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

        $emision = Carbon::now()->startOfMonth();
        $periodoDesde = $emision->copy()->subMonthNoOverflow()->startOfMonth();
        $periodoHasta = $emision->copy()->subMonthNoOverflow()->endOfMonth();

        $diaVencimiento = FacturacionParametro::diaVencimiento();
        $fechaVencimiento = FacturacionParametro::fechaVencimientoMesSiguiente($periodoHasta);

        if ($dryRun) {
            $this->info('Modo dry-run: no se crearán facturas.');
            $this->info(sprintf(
                'Emisión: %s. Período a facturar: %s a %s',
                $emision->format('d/m/Y'),
                $periodoDesde->format('d/m/Y'),
                $periodoHasta->format('d/m/Y')
            ));
            $this->info(sprintf('Vencimiento: %s (día %d)', $fechaVencimiento, $diaVencimiento));
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
            $servicioIds = Servicio::query()
                ->where('cliente_id', $cliente->cliente_id)
                ->whereIn('estado', self::ESTADOS_SERVICIO_FACTURA_AUTOMATICA)
                ->pluck('servicio_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $yaFacturados = $facturacionService->servicioIdsConFacturaEnPeriodo($servicioIds, $periodoDesde, $periodoHasta);
            $pendientes = array_values(array_diff($servicioIds, $yaFacturados));

            if ($pendientes === []) {
                $omitidos++;
                if ($dryRun) {
                    $this->line("  - Omitido: Cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido}) ya tiene factura del período en sus servicios.");
                }

                continue;
            }

            try {
                if ($dryRun) {
                    $this->line("  - Crearía factura para: Cliente {$cliente->cliente_id} ({$cliente->nombre} {$cliente->apellido}) — ".count($pendientes).' servicio(s)');
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
                    $emision->toDateString(),
                    self::ESTADOS_SERVICIO_FACTURA_AUTOMATICA,
                    enviarWhatsApp: false,
                    soloServicioIds: $pendientes,
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

        if (! $dryRun) {
            $cuota = app(HotspotCuotaResetService::class)->resetear($emision->format('Y-m'));
            if ($cuota['skipped']) {
                $this->info('Cuota hotspot: '.($cuota['motivo'] ?? 'sin cambios.'));
            } else {
                $this->info(sprintf(
                    'Cuota hotspot %s: %d contadores en cero, %d ya estaban en cero, %d sesiones cerradas, %d errores.',
                    $emision->format('Y-m'),
                    $cuota['reseteados'],
                    $cuota['ya_reseteados'],
                    $cuota['sesiones'],
                    $cuota['errores']
                ));
            }
        }

        return self::SUCCESS;
    }
}
