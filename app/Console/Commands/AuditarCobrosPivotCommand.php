<?php

namespace App\Console\Commands;

use App\Models\Cobro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditarCobrosPivotCommand extends Command
{
    protected $signature = 'cobros:auditar-pivote
                            {--desde= : Fecha desde (YYYY-MM-DD) sobre fecha_pago}
                            {--hasta= : Fecha hasta (YYYY-MM-DD) sobre fecha_pago}
                            {--usuario_id= : Filtrar por usuario_id}
                            {--cliente_id= : Filtrar por cliente_id}
                            {--solo-diferencias : Mostrar solo cobros con diferencia}
                            {--top=30 : Cantidad maxima de filas a mostrar en detalle}
                            {--fail-on-diff : Retorna codigo 1 si hay diferencias}';

    protected $description = 'Audita diferencias entre cobros.monto y suma de cobro_factura_interna.monto por cobro.';

    public function handle(): int
    {
        $desde = $this->option('desde');
        $hasta = $this->option('hasta');
        $usuarioId = $this->option('usuario_id');
        $clienteId = $this->option('cliente_id');
        $soloDiferencias = (bool) $this->option('solo-diferencias');
        $top = max(1, (int) $this->option('top'));
        $failOnDiff = (bool) $this->option('fail-on-diff');

        $pivotSub = DB::table('cobro_factura_interna')
            ->selectRaw('cobro_id, SUM(monto) as pivot_total, COUNT(*) as pivot_filas, COUNT(DISTINCT factura_interna_id) as facturas_relacionadas')
            ->groupBy('cobro_id');

        $query = Cobro::query()
            ->leftJoinSub($pivotSub, 'piv', function ($join) {
                $join->on('piv.cobro_id', '=', 'cobros.id');
            })
            ->select([
                'cobros.id',
                'cobros.numero_recibo',
                'cobros.fecha_pago',
                'cobros.usuario_id',
                'cobros.cliente_id',
                'cobros.monto',
                DB::raw('COALESCE(piv.pivot_total, 0) as pivot_total'),
                DB::raw('COALESCE(piv.pivot_filas, 0) as pivot_filas'),
                DB::raw('COALESCE(piv.facturas_relacionadas, 0) as facturas_relacionadas'),
                DB::raw('(cobros.monto - COALESCE(piv.pivot_total, 0)) as diferencia'),
            ])
            ->orderBy('cobros.fecha_pago')
            ->orderBy('cobros.id');

        if (! empty($desde)) {
            $query->whereDate('cobros.fecha_pago', '>=', $desde);
        }
        if (! empty($hasta)) {
            $query->whereDate('cobros.fecha_pago', '<=', $hasta);
        }
        if (! empty($usuarioId)) {
            $query->where('cobros.usuario_id', (int) $usuarioId);
        }
        if (! empty($clienteId)) {
            $query->where('cobros.cliente_id', (int) $clienteId);
        }
        if ($soloDiferencias) {
            $query->whereRaw('ABS(cobros.monto - COALESCE(piv.pivot_total, 0)) > 0.009');
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('No hay cobros para auditar con los filtros indicados.');
            return self::SUCCESS;
        }

        $totalCobros = $rows->count();
        $totalMontoCobros = (float) $rows->sum('monto');
        $totalMontoPivot = (float) $rows->sum('pivot_total');
        $diferenciaGlobal = $totalMontoCobros - $totalMontoPivot;
        $conDiferencia = $rows->filter(fn ($r) => abs((float) $r->diferencia) > 0.009)->values();
        $sinPivot = $rows->filter(fn ($r) => (int) $r->pivot_filas === 0)->count();

        $this->info('Auditoria cobros vs pivote');
        $this->line('Registros auditados: '.$totalCobros);
        $this->line('Suma cobros.monto: '.number_format($totalMontoCobros, 2, ',', '.'));
        $this->line('Suma pivote.monto: '.number_format($totalMontoPivot, 2, ',', '.'));
        $this->line('Diferencia global: '.number_format($diferenciaGlobal, 2, ',', '.'));
        $this->line('Cobros con diferencia: '.$conDiferencia->count());
        $this->line('Cobros sin filas en pivote: '.$sinPivot);

        if ($conDiferencia->isNotEmpty()) {
            $this->newLine();
            $this->warn('Detalle de cobros con diferencia (top '.$top.'):');

            $detalle = $conDiferencia
                ->sortByDesc(fn ($r) => abs((float) $r->diferencia))
                ->take($top)
                ->map(fn ($r) => [
                    'ID' => $r->id,
                    'Recibo' => (string) $r->numero_recibo,
                    'Fecha' => optional($r->fecha_pago)->format('d/m/Y H:i'),
                    'Monto cobro' => number_format((float) $r->monto, 2, ',', '.'),
                    'Monto pivote' => number_format((float) $r->pivot_total, 2, ',', '.'),
                    'Diferencia' => number_format((float) $r->diferencia, 2, ',', '.'),
                    'Filas pivote' => (int) $r->pivot_filas,
                    'Facturas' => (int) $r->facturas_relacionadas,
                    'Usuario' => (string) $r->usuario_id,
                    'Cliente' => (string) $r->cliente_id,
                ])
                ->values()
                ->all();

            $this->table(
                ['ID', 'Recibo', 'Fecha', 'Monto cobro', 'Monto pivote', 'Diferencia', 'Filas pivote', 'Facturas', 'Usuario', 'Cliente'],
                $detalle
            );
        }

        if ($failOnDiff && $conDiferencia->isNotEmpty()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

