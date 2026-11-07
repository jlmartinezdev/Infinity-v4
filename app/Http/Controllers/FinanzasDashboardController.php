<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\FinCuenta;
use App\Models\FinMovimiento;
use App\Services\Finanzas\FinanzasCobroPuente;
use App\Services\Finanzas\FinanzasLibroService;
use App\Support\FinanzasParametros;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanzasDashboardController extends Controller
{
    public function index(FinanzasLibroService $libro): View
    {
        $cuentas = FinCuenta::query()->where('activa', true)->orderBy('orden')->get();
        $saldos = $cuentas->map(fn (FinCuenta $cuenta) => [
            'cuenta' => $cuenta,
            'saldo' => $libro->saldoActual($cuenta->id),
        ]);
        $consolidado = round((float) $saldos->sum('saldo'), 2);
        $desde = now()->startOfMonth();
        $hasta = now()->endOfMonth();
        $ingresos = round((float) FinMovimiento::query()
            ->where('tipo', 'ingreso')
            ->whereDate('fecha_efectiva', '>=', $desde)
            ->whereDate('fecha_efectiva', '<=', $hasta)
            ->sum('importe'), 2);
        $opex = $libro->sumaClase('opex', $desde, $hasta);
        $capex = $libro->sumaClase('capex', $desde, $hasta);
        $comisiones = $libro->sumaClase('comision', $desde, $hasta);
        $retiros = $libro->sumaClase('retiro', $desde, $hasta);

        $meses = collect(range(5, 0))->map(function (int $atras) use ($libro) {
            $mes = now()->startOfMonth()->subMonths($atras);
            $fin = $mes->copy()->endOfMonth();
            $ingresosMes = (float) FinMovimiento::query()
                ->where('tipo', 'ingreso')
                ->whereDate('fecha_efectiva', '>=', $mes)
                ->whereDate('fecha_efectiva', '<=', $fin)
                ->sum('importe');
            $egresosMes = (float) FinMovimiento::query()
                ->where('tipo', 'egreso')
                ->whereDate('fecha_efectiva', '>=', $mes)
                ->whereDate('fecha_efectiva', '<=', $fin)
                ->sum('importe');
            $saldo = 0.0;
            foreach (FinCuenta::query()->where('activa', true)->pluck('id') as $cuentaId) {
                $saldo += $libro->saldoHasta((int) $cuentaId, $fin);
            }

            return [
                'mes' => $mes->translatedFormat('M Y'),
                'ingresos' => round($ingresosMes, 2),
                'egresos' => round($egresosMes, 2),
                'saldo' => round($saldo, 2),
            ];
        })->values();

        $corte = FinanzasParametros::fechaCorte();
        $fueraDelLibro = 0;
        if ($corte) {
            $fueraDelLibro = Cobro::query()
                ->whereDate('fecha_pago', '>=', $corte->toDateString())
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('fin_movimientos')
                        ->whereColumn('fin_movimientos.referencia_id', 'cobros.id')
                        ->where('fin_movimientos.referencia_tipo', 'cobro')
                        ->where('fin_movimientos.tipo', 'ingreso')
                        ->whereNull('fin_movimientos.revertido_por_id');
                })
                ->count();
        }
        $efectivoSinRendir = (float) Cobro::query()
            ->where('forma_pago', 'efectivo')
            ->whereNull('cobro_rendicion_id')
            ->sum('monto');

        return view('finanzas.dashboard', [
            'saldos' => $saldos,
            'consolidado' => $consolidado,
            'ingresos' => $ingresos,
            'opex' => $opex,
            'capex' => $capex,
            'comisiones' => $comisiones,
            'retiros' => $retiros,
            'resultado' => round($ingresos - $opex - $capex - $comisiones - $retiros, 2),
            'meses' => $meses,
            'corte' => $corte,
            'fueraDelLibro' => $fueraDelLibro,
            'efectivoSinRendir' => $efectivoSinRendir,
            'porCuenta' => $saldos->map(fn ($fila) => [
                'nombre' => $fila['cuenta']->nombre,
                'saldo' => $fila['saldo'],
            ])->values(),
        ]);
    }

    public function cobrosMes(Request $request, FinanzasCobroPuente $puente): View
    {
        $mes = $this->mesPedido($request);

        return view('finanzas.cargar-cobros', [
            'mes' => $mes->format('Y-m'),
            'previa' => $puente->previaMes($mes),
            'corte' => FinanzasParametros::fechaCorte(),
            'cuentas' => FinCuenta::query()->pluck('nombre', 'codigo'),
            'puedeCargar' => auth()->user()?->tienePermiso('finanzas.crear') ?? false,
        ]);
    }

    public function cargarCobrosMes(Request $request, FinanzasCobroPuente $puente): RedirectResponse
    {
        $datos = $request->validate([
            'mes' => ['required', 'date_format:Y-m'],
        ]);
        set_time_limit(180);
        $mes = Carbon::createFromFormat('Y-m-d', $datos['mes'].'-01')->startOfMonth();
        $resultado = $puente->cargarMes($mes);
        $texto = 'Se cargaron '.number_format($resultado['cargados'], 0, ',', '.')
            .' recibos por '.number_format($resultado['monto_cargado'], 0, ',', '.')
            .' Gs. '.$resultado['ya_estaban'].' ya estaban en el libro.';
        if ($resultado['omitidos'] > 0) {
            $texto .= ' '.$resultado['omitidos'].' quedaron afuera.';
        }

        return redirect()
            ->route('finanzas.cobros-mes', ['mes' => $mes->format('Y-m')])
            ->with('success', $texto)
            ->with('errores_carga', $resultado['errores']);
    }

    private function mesPedido(Request $request): Carbon
    {
        $valor = (string) $request->input('mes', now()->format('Y-m'));
        try {
            return Carbon::createFromFormat('Y-m-d', $valor.'-01')->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
