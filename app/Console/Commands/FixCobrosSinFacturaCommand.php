<?php

namespace App\Console\Commands;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Services\FacturacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCobrosSinFacturaCommand extends Command
{
    protected $signature = 'cobros:aplicar-sin-factura
                            {--dry-run : Solo simula, no escribe cambios}
                            {--cliente_id= : Procesa solo cobros de un cliente}
                            {--cobro_id= : Procesa un cobro específico}';

    protected $description = 'Aplica cobros sin factura asociada a facturas internas pendientes del mismo cliente.';

    public function handle(FacturacionService $facturacionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clienteId = $this->option('cliente_id');
        $cobroId = $this->option('cobro_id');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se guardarán cambios.');
        }

        $query = Cobro::query()
            ->whereNull('factura_interna_id')
            ->whereDoesntHave('facturaInternas')
            ->where('monto', '>', 0)
            ->orderBy('fecha_pago')
            ->orderBy('id');

        if (!empty($clienteId)) {
            $query->where('cliente_id', (int) $clienteId);
        }
        if (!empty($cobroId)) {
            $query->where('id', (int) $cobroId);
        }

        $cobros = $query->get();
        if ($cobros->isEmpty()) {
            $this->info('No se encontraron cobros sin factura para procesar.');
            return self::SUCCESS;
        }

        $procesados = 0;
        $aplicados = 0;
        $sinFacturasPendientes = 0;
        $montoTotalAplicado = 0.0;

        foreach ($cobros as $cobro) {
            $procesados++;
            $montoDisponible = round((float) $cobro->monto, 2);

            $facturas = FacturaInterna::query()
                ->where('cliente_id', $cobro->cliente_id)
                ->whereIn('estado', ['pendiente', 'emitida'])
                ->orderBy('fecha_vencimiento')
                ->orderBy('fecha_emision')
                ->orderBy('id')
                ->get()
                ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
                ->values();

            if ($facturas->isEmpty()) {
                $sinFacturasPendientes++;
                $this->line("Cobro #{$cobro->id} ({$cobro->numero_recibo}): sin facturas pendientes para cliente {$cobro->cliente_id}.");
                continue;
            }

            $distribucion = [];
            foreach ($facturas as $factura) {
                if ($montoDisponible <= 0) {
                    break;
                }

                $saldo = round((float) $factura->saldo_pendiente, 2);
                if ($saldo <= 0) {
                    continue;
                }

                $montoAplicar = min($montoDisponible, $saldo);
                $montoAplicar = round($montoAplicar, 2);
                if ($montoAplicar <= 0) {
                    continue;
                }

                $distribucion[] = [
                    'factura' => $factura,
                    'monto' => $montoAplicar,
                ];
                $montoDisponible = round($montoDisponible - $montoAplicar, 2);
            }

            if (empty($distribucion)) {
                $this->line("Cobro #{$cobro->id} ({$cobro->numero_recibo}): no se pudo distribuir.");
                continue;
            }

            if ($dryRun) {
                $detalles = collect($distribucion)->map(
                    fn (array $d) => "#{$d['factura']->id}: ".number_format($d['monto'], 2, ',', '.')
                )->join(' | ');
                $this->info("DRY Cobro #{$cobro->id} -> {$detalles}");
                $aplicados++;
                $montoTotalAplicado += collect($distribucion)->sum('monto');
                continue;
            }

            DB::transaction(function () use ($cobro, $distribucion, $facturacionService) {
                foreach ($distribucion as $index => $item) {
                    /** @var FacturaInterna $factura */
                    $factura = $item['factura'];
                    $monto = (float) $item['monto'];
                    $cobro->facturaInternas()->attach($factura->id, ['monto' => $monto]);

                    if ($index === 0) {
                        $cobro->factura_interna_id = $factura->id;
                    }
                }

                $cobro->save();

                foreach ($distribucion as $item) {
                    /** @var FacturaInterna $factura */
                    $factura = FacturaInterna::find($item['factura']->id);
                    if (!$factura) {
                        continue;
                    }
                    $factura->refresh();
                    $estadoPago = $factura->saldo_pendiente <= 0 ? 'pagado' : 'parcial';
                    $facturacionService->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, $estadoPago);
                    if ($factura->saldo_pendiente <= 0 && $factura->estado !== 'pagada') {
                        $factura->update(['estado' => 'pagada']);
                    }
                }
            });

            $detalles = collect($distribucion)->map(
                fn (array $d) => "#{$d['factura']->id}: ".number_format($d['monto'], 2, ',', '.')
            )->join(' | ');
            $this->info("Cobro #{$cobro->id} ({$cobro->numero_recibo}) aplicado -> {$detalles}");
            $aplicados++;
            $montoTotalAplicado += collect($distribucion)->sum('monto');
        }

        $this->newLine();
        $this->info("Cobros procesados: {$procesados}");
        $this->info("Cobros aplicados: {$aplicados}");
        $this->info("Sin facturas pendientes: {$sinFacturasPendientes}");
        $this->info('Monto total aplicado: '.number_format($montoTotalAplicado, 2, ',', '.'));

        return self::SUCCESS;
    }
}
