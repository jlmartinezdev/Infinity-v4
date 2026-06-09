<?php

namespace App\Console\Commands;

use App\Models\FacturaInterna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditarMontoPagadoFacturasCommand extends Command
{
    protected $signature = 'cobros:auditar-monto-pagado-facturas
                            {--cliente_id= : Filtrar por cliente}
                            {--solo-diferencias : Solo facturas con diferencia}
                            {--top=50 : Máximo de filas}
                            {--fail-on-diff : Código 1 si hay diferencias}';

    protected $description = 'Audita que el monto pagado de cada factura interna coincida con la suma del pivote cobro_factura_interna.';

    public function handle(): int
    {
        $soloDiferencias = (bool) $this->option('solo-diferencias');
        $failOnDiff = (bool) $this->option('fail-on-diff');
        $top = max(1, (int) $this->option('top'));

        $sumCobros = FacturaInterna::sqlSumCobros();

        $query = DB::table('factura_internas as fi')
            ->selectRaw("
                fi.id,
                fi.cliente_id,
                fi.estado,
                fi.total,
                {$sumCobros} as sum_pivot,
                LEAST(fi.total, {$sumCobros}) as monto_pagado_calc,
                ({$sumCobros} - LEAST(fi.total, {$sumCobros})) as exceso_pivot
            ")
            ->whereNotIn('fi.estado', ['anulada', 'cancelada'])
            ->orderByDesc('fi.id');

        if ($this->option('cliente_id')) {
            $query->where('fi.cliente_id', (int) $this->option('cliente_id'));
        }

        if ($soloDiferencias) {
            $query->whereRaw("({$sumCobros} - LEAST(fi.total, {$sumCobros})) > 0.009");
        }

        $rows = $query->get();

        $conExceso = $rows->filter(fn ($r) => (float) $r->exceso_pivot > 0.009);
        $conCobros = $rows->filter(fn ($r) => (float) $r->sum_pivot > 0.009);

        $this->info('Auditoría monto pagado facturas internas');
        $this->line('Facturas auditadas: '.$rows->count());
        $this->line('Con cobros aplicados: '.$conCobros->count());
        $this->line('Con exceso en pivote (> total factura): '.$conExceso->count());
        $this->line('Suma monto pagado (calc): '.number_format(
            (float) $rows->sum(fn ($r) => (float) $r->monto_pagado_calc),
            0,
            ',',
            '.'
        ).' PYG');

        if ($rows->isNotEmpty()) {
            $this->newLine();
            $detalle = $rows
                ->sortByDesc(fn ($r) => (float) $r->exceso_pivot)
                ->take($top)
                ->map(fn ($r) => [
                    $r->id,
                    $r->cliente_id,
                    $r->estado,
                    number_format((float) $r->total, 0, ',', '.'),
                    number_format((float) $r->monto_pagado_calc, 0, ',', '.'),
                    number_format((float) $r->sum_pivot, 0, ',', '.'),
                    number_format((float) $r->exceso_pivot, 0, ',', '.'),
                ])
                ->values()
                ->all();

            $this->table(
                ['Factura', 'Cliente', 'Estado', 'Total', 'Pagado', 'Suma pivote', 'Exceso pivote'],
                $detalle
            );
        }

        if ($conExceso->isEmpty()) {
            $this->newLine();
            $this->info('OK: no hay facturas con exceso de cobro en el pivote.');
        } elseif ($failOnDiff) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
