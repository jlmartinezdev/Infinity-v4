<?php

namespace App\Console\Commands;

use App\Models\Cobro;
use App\Models\Servicio;
use App\Services\FacturacionService;
use App\Support\CobrosMesVentana;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditarSaldoFavorRecibosCommand extends Command
{
    protected $signature = 'cobros:auditar-saldo-favor-recibos
                            {--desde= : Fecha desde YYYY-MM-DD (fecha_pago)}
                            {--hasta= : Fecha hasta YYYY-MM-DD (fecha_pago)}
                            {--cliente_id= : Filtrar por cliente}
                            {--top=50 : Máximo de filas en detalle}
                            {--solo-con-saldo : Solo recibos con saldo a favor generado}
                            {--fail-on-diff : Código 1 si hay recibos con saldo a favor}';

    protected $description = 'Lista recibos que generaron saldo a favor (cobro adelantado o exceso) y compara con cobros_resumen del mes.';

    public function handle(FacturacionService $facturacionService): int
    {
        $soloConSaldo = (bool) $this->option('solo-con-saldo');
        $failOnDiff = (bool) $this->option('fail-on-diff');
        $top = max(1, (int) $this->option('top'));
        $clienteId = $this->option('cliente_id');

        $query = Cobro::query()
            ->with(['cliente', 'facturaInternas'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id');

        if ($this->option('desde')) {
            $query->whereDate('fecha_pago', '>=', $this->option('desde'));
        }
        if ($this->option('hasta')) {
            $query->whereDate('fecha_pago', '<=', $this->option('hasta'));
        }
        if ($clienteId) {
            $query->where('cliente_id', (int) $clienteId);
        }

        $cobros = $query->get();
        if ($cobros->isEmpty()) {
            $this->info('No hay cobros en el rango indicado.');

            return self::SUCCESS;
        }

        $sqlSaldoFavor = CobrosMesVentana::sqlMontoSaldoFavorRegistrado();

        $filas = [];
        $totalSaldoFavor = 0.0;
        $totalMontoCobros = 0.0;

        foreach ($cobros as $cobro) {
            $saldoFavor = $facturacionService->montoSaldoFavorGeneradoPorCobro($cobro);
            $saldoDashboard = (float) DB::table('cobros')
                ->where('id', $cobro->id)
                ->selectRaw($sqlSaldoFavor.' as monto')
                ->value('monto');

            if ($soloConSaldo && $saldoFavor <= 0.009) {
                continue;
            }

            $tipo = '—';
            if ($cobro->facturaInternas->isEmpty() && ! $cobro->factura_interna_id) {
                $tipo = 'Sin factura';
            } elseif ($saldoFavor > 0.009) {
                $tipo = 'Exceso';
            }

            $totalSaldoFavor += $saldoFavor;
            $totalMontoCobros += (float) $cobro->monto;

            $filas[] = [
                'id' => $cobro->id,
                'recibo' => (string) $cobro->numero_recibo,
                'fecha' => $cobro->fecha_pago?->format('d/m/Y') ?? '—',
                'cliente' => $cobro->cliente
                    ? trim(($cobro->cliente->nombre ?? '').' '.($cobro->cliente->apellido ?? ''))
                    : '—',
                'monto' => (float) $cobro->monto,
                'saldo_favor' => $saldoFavor,
                'tipo' => $tipo,
                'saldo_servicio' => $cobro->cliente
                    ? (float) Servicio::where('cliente_id', $cobro->cliente_id)->sum('saldo_a_favor')
                    : 0.0,
                'diff_dashboard' => round($saldoFavor - $saldoDashboard, 2),
            ];
        }

        $conSaldo = collect($filas)->where('saldo_favor', '>', 0.009)->count();

        $this->info('Auditoría recibos con saldo a favor');
        $this->line('Cobros analizados: '.$cobros->count());
        $this->line('Recibos con saldo a favor: '.$conSaldo);
        $this->line('Suma saldo a favor (recibos): '.number_format($totalSaldoFavor, 0, ',', '.').' PYG');
        $this->line('Suma monto cobros listados: '.number_format(
            $soloConSaldo ? collect($filas)->sum('monto') : $totalMontoCobros,
            0,
            ',',
            '.'
        ).' PYG');

        if ($filas !== []) {
            $this->newLine();
            $detalle = collect($filas)
                ->sortByDesc('saldo_favor')
                ->take($top)
                ->map(fn (array $r) => [
                    $r['id'],
                    $r['recibo'],
                    $r['fecha'],
                    $r['cliente'],
                    number_format($r['monto'], 0, ',', '.'),
                    number_format($r['saldo_favor'], 0, ',', '.'),
                    $r['tipo'],
                    number_format($r['saldo_servicio'], 0, ',', '.'),
                ])
                ->values()
                ->all();

            $this->table(
                ['ID', 'Recibo', 'Fecha', 'Cliente', 'Monto', 'Saldo a favor', 'Tipo', 'Saldo servicio'],
                $detalle
            );
        }

        if ($conSaldo === 0) {
            $this->newLine();
            $this->info('OK: no hay recibos con saldo a favor en el rango.');

            return self::SUCCESS;
        }

        if ($failOnDiff) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
