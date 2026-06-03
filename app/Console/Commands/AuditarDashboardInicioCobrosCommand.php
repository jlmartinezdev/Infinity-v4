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

        $diaCorte = CobrosMesVentana::diaCorteMesReferencia($mesReferencia)->toDateString();

        $subMinFactura = DB::table('cobro_factura_interna as cfi_min')
            ->join('factura_internas as fi_min', 'fi_min.id', '=', 'cfi_min.factura_interna_id')
            ->groupBy('cfi_min.cobro_id')
            ->selectRaw('cfi_min.cobro_id, MIN(fi_min.created_at) as factura_created_at');

        $facturaPorCobro = DB::table('cobros')
            ->leftJoinSub($subMinFactura, 'fmin', fn ($j) => $j->on('fmin.cobro_id', '=', 'cobros.id'))
            ->leftJoin('factura_internas as fi_leg', 'fi_leg.id', '=', 'cobros.factura_interna_id')
            ->whereIn('cobros.id', $rows->pluck('id'))
            ->selectRaw('cobros.id, COALESCE(fmin.factura_created_at, fi_leg.created_at) as factura_created_at')
            ->pluck('factura_created_at', 'id');

        $rowsConAtribucion = $rows->map(function ($r) use ($facturaPorCobro, $mesReferencia) {
            $fechaPago = Carbon::parse($r->fecha_pago);
            $facturaCreated = $facturaPorCobro->get($r->id);
            $facturaCarbon = $facturaCreated ? Carbon::parse($facturaCreated) : null;
            $r->cuenta_en_mes = CobrosMesVentana::cobroCuentaEnMesReferencia($fechaPago, $facturaCarbon, $mesReferencia) ? 1 : 0;

            return $r;
        });

        // Total con atribucion (Home + Cobros del mes): ventana + regla dia 20.
        $totalConAtribucion = (float) $rowsConAtribucion
            ->filter(fn ($r) => (int) $r->cuenta_en_mes === 1)
            ->sum('monto');

        // Home / Cobros del mes: ventana + atribucion dia 20 (sin exigir rango de factura).
        $totalHome = $totalConAtribucion;

        // Dashboard facturacion (cobrado del ciclo): ventana + facturas del mes anterior + atribucion.
        $totalDashboardCiclo = (float) $rowsConAtribucion
            ->filter(fn ($r) => (int) $r->tiene_factura_rango === 1 && (int) $r->cuenta_en_mes === 1)
            ->sum('monto');

        // Como estaba antes HomeController: suma pivote con fecha cobro en ventana + factura en rango.
        $totalAnteriorPivot = (float) $rowsConAtribucion->sum('pivot_total_rango');

        $totalCobrosRecibos = $totalConAtribucion;

        $diferenciaGlobal = $totalDashboardCiclo - $totalAnteriorPivot;
        $difHomeVsCobrosRecibos = $totalHome - $totalCobrosRecibos;

        $this->info('Auditoria Dashboard Inicio + Cobros y Recibos (Cobros del mes)');
        $this->line('Mes de referencia: '.$mesReferencia->format('Y-m'));
        $this->line('Ventana cobros: '.$rangos['desdeVentana']->format('Y-m-d').' a '.$rangos['hastaVentana']->format('Y-m-d'));
        $this->line('Corte atribucion (dia 20 mes ref.): '.$diaCorte);
        $this->line('Rango facturas: '.$rangos['facturaDesde']->format('Y-m-d').' a '.$rangos['facturaHasta']->format('Y-m-d'));
        $this->newLine();
        $this->line('Total COBROS DEL MES (Home / C&R): '.number_format($totalConAtribucion, 2, ',', '.'));
        $this->line('Total Dashboard ciclo:            '.number_format($totalDashboardCiclo, 2, ',', '.'));
        $this->line('Total ANTERIOR (pivote):          '.number_format($totalAnteriorPivot, 2, ',', '.'));
        $this->line('Dif Dashboard - pivote:           '.number_format($diferenciaGlobal, 2, ',', '.'));
        $this->line('Dif Home - C&R:                   '.number_format($difHomeVsCobrosRecibos, 2, ',', '.'));
        $this->line('Cobros en ventana:          '.$rowsConAtribucion->count());
        $this->line('Cobros atribuidos al mes:   '.$rowsConAtribucion->where('cuenta_en_mes', 1)->count());
        $this->line('Cobros con factura en rango: '.$rowsConAtribucion->where('tiene_factura_rango', 1)->count());

        $detalle = $rowsConAtribucion
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
                'En C&M?' => (int) $r->cuenta_en_mes === 1 ? 'Si' : 'No',
            ])
            ->values()
            ->all();

        if (! empty($detalle)) {
            $this->newLine();
            $this->warn('Detalle (top '.$top.'):');
            $this->table(['ID', 'Recibo', 'Fecha', 'Monto cobro', 'Pivot rango', 'Dif', 'En Inicio nuevo?', 'En C&M?'], $detalle);
        }

        return self::SUCCESS;
    }
}

