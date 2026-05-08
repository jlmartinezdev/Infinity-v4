<?php

namespace App\Console\Commands;

use App\Support\CobrosMesVentana;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditarDashboardInicioCobrosCommand extends Command
{
    protected $signature = 'cobros:auditar-dashboard-inicio
                            {--mes= : Mes de referencia YYYY-MM (por defecto mes actual)}
                            {--top=30 : Cantidad maxima de filas de detalle}
                            {--solo-diferencias : Mostrar solo filas con diferencia}';

    protected $description = 'Compara Dashboard Inicio (nuevo/anterior) y Cobros y Recibos sobre la misma ventana mensual.';

    public function handle(): int
    {
        $mesOpcion = (string) ($this->option('mes') ?? '');
        $top = max(1, (int) $this->option('top'));
        $soloDiferencias = (bool) $this->option('solo-diferencias');

        if ($mesOpcion !== '') {
            try {
                $mesReferencia = Carbon::createFromFormat('Y-m', $mesOpcion)->startOfMonth();
            } catch (\Throwable $e) {
                $this->error('Formato invalido en --mes. Usa YYYY-MM, por ejemplo 2026-05.');
                return self::FAILURE;
            }
        } else {
            $mesReferencia = now()->startOfMonth();
        }

        $rangos = CobrosMesVentana::rangosParaMesReferencia($mesReferencia);

        $subPivotRango = DB::table('cobro_factura_interna as cfi')
            ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
            ->whereBetween('fi.created_at', [$rangos['facturaDesde'], $rangos['facturaHasta']])
            ->groupBy('cfi.cobro_id')
            ->selectRaw('cfi.cobro_id, SUM(cfi.monto) as pivot_total_rango');

        $subExisteFacturaRango = DB::table('cobro_factura_interna as cfi2')
            ->join('factura_internas as fi2', 'fi2.id', '=', 'cfi2.factura_interna_id')
            ->whereBetween('fi2.created_at', [$rangos['facturaDesde'], $rangos['facturaHasta']])
            ->selectRaw('DISTINCT cfi2.cobro_id');

        $base = DB::table('cobros')
            ->leftJoinSub($subPivotRango, 'piv', function ($join) {
                $join->on('piv.cobro_id', '=', 'cobros.id');
            })
            ->leftJoinSub($subExisteFacturaRango, 'ex', function ($join) {
                $join->on('ex.cobro_id', '=', 'cobros.id');
            })
            ->whereBetween('cobros.fecha_pago', [$rangos['desdeVentana'], $rangos['hastaVentana']])
            ->selectRaw('
                cobros.id,
                cobros.numero_recibo,
                cobros.fecha_pago,
                cobros.monto,
                COALESCE(piv.pivot_total_rango, 0) as pivot_total_rango,
                CASE WHEN ex.cobro_id IS NULL THEN 0 ELSE 1 END as tiene_factura_rango,
                (cobros.monto - COALESCE(piv.pivot_total_rango, 0)) as diferencia
            ')
            ->orderBy('cobros.fecha_pago')
            ->orderBy('cobros.id');

        $rows = $base->get();

        if ($rows->isEmpty()) {
            $this->info('No hay cobros en la ventana del mes de referencia.');
            return self::SUCCESS;
        }

        // Como esta ahora HomeController: suma cobros.monto con fecha en ventana y con factura en rango.
        $totalNuevoCobrosMonto = (float) $rows
            ->filter(fn ($r) => (int) $r->tiene_factura_rango === 1)
            ->sum('monto');

        // Como estaba antes HomeController: suma pivote con fecha cobro en ventana + factura en rango.
        $totalAnteriorPivot = (float) $rows->sum('pivot_total_rango');

        // Como suma en Cobros y Recibos (tarjeta "Cobros del mes" sin filtros extra):
        // cobros.monto en ventana de pago, sin exigir rango de factura.
        $totalCobrosRecibos = (float) $rows->sum('monto');

        $diferenciaGlobal = $totalNuevoCobrosMonto - $totalAnteriorPivot;
        $difInicioVsCobrosRecibos = $totalNuevoCobrosMonto - $totalCobrosRecibos;

        $this->info('Auditoria Dashboard Inicio + Cobros y Recibos (Cobros del mes)');
        $this->line('Mes de referencia: '.$mesReferencia->format('Y-m'));
        $this->line('Ventana cobros: '.$rangos['desdeVentana']->format('Y-m-d').' a '.$rangos['hastaVentana']->format('Y-m-d'));
        $this->line('Rango facturas: '.$rangos['facturaDesde']->format('Y-m-d').' a '.$rangos['facturaHasta']->format('Y-m-d'));
        $this->newLine();
        $this->line('Total NUEVO (cobros.monto): '.number_format($totalNuevoCobrosMonto, 2, ',', '.'));
        $this->line('Total ANTERIOR (pivote):    '.number_format($totalAnteriorPivot, 2, ',', '.'));
        $this->line('Total Cobros y Recibos:     '.number_format($totalCobrosRecibos, 2, ',', '.'));
        $this->line('Dif NUEVO - ANTERIOR:       '.number_format($diferenciaGlobal, 2, ',', '.'));
        $this->line('Dif NUEVO - C&R:            '.number_format($difInicioVsCobrosRecibos, 2, ',', '.'));
        $this->line('Cobros en ventana:          '.$rows->count());
        $this->line('Cobros con factura en rango: '.$rows->where('tiene_factura_rango', 1)->count());

        $detalle = $rows
            ->when($soloDiferencias, fn ($c) => $c->filter(fn ($r) => abs((float) $r->diferencia) > 0.009))
            ->sortByDesc(fn ($r) => abs((float) $r->diferencia))
            ->take($top)
            ->map(fn ($r) => [
                'ID' => $r->id,
                'Recibo' => (string) $r->numero_recibo,
                'Fecha' => Carbon::parse($r->fecha_pago)->format('d/m/Y H:i'),
                'Monto cobro' => number_format((float) $r->monto, 2, ',', '.'),
                'Pivot rango' => number_format((float) $r->pivot_total_rango, 2, ',', '.'),
                'Dif' => number_format((float) $r->diferencia, 2, ',', '.'),
                'En Inicio nuevo?' => (int) $r->tiene_factura_rango === 1 ? 'Si' : 'No',
                'En C&R?' => 'Si',
            ])
            ->values()
            ->all();

        if (! empty($detalle)) {
            $this->newLine();
            $this->warn('Detalle (top '.$top.'):');
            $this->table(['ID', 'Recibo', 'Fecha', 'Monto cobro', 'Pivot rango', 'Dif', 'En Inicio nuevo?', 'En C&R?'], $detalle);
        }

        return self::SUCCESS;
    }
}

