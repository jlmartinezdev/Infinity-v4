<?php

namespace App\Http\Controllers;

use App\Services\Finanzas\FinanzasLibroService;
use App\Services\Finanzas\PeriodoCerradoException;
use App\Support\FinanzasParametros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ConfiguracionFinanzasController extends Controller
{
    public function edit(): View
    {
        $cuentas = [];
        foreach (FinanzasParametros::formas() as $forma => $etiqueta) {
            $cuentas[$forma] = FinanzasParametros::cuentaDeForma($forma);
        }
        $saldos = FinanzasParametros::saldosIniciales();

        return view('configuracion.finanzas', [
            'cuentasOpciones' => FinanzasParametros::cuentas(),
            'formas' => FinanzasParametros::formas(),
            'cuentas' => $cuentas,
            'fechaCorte' => FinanzasParametros::fechaCorte()?->toDateString() ?? '',
            'comision' => FinanzasParametros::comisionBancard(),
            'saldos' => $saldos,
            'fechaSaldo' => $saldos['fecha']?->toDateString() ?? '',
        ]);
    }

    public function update(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $codigos = implode(',', array_keys(FinanzasParametros::cuentas()));
        $reglas = [
            'fin_fecha_corte' => ['nullable', 'date'],
            'fin_comision_bancard' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fin_saldo_inicial_fecha' => ['nullable', 'date'],
        ];
        foreach (array_keys(FinanzasParametros::formas()) as $forma) {
            $reglas['fin_cuenta_'.$forma] = ['required', 'in:'.$codigos];
        }
        foreach ([FinanzasParametros::CUENTA_EFECTIVO, FinanzasParametros::CUENTA_BANCO, FinanzasParametros::CUENTA_BANCARD] as $cuenta) {
            $reglas['fin_saldo_inicial_'.$cuenta] = ['nullable', 'string', 'max:30'];
        }

        $validated = $request->validate($reglas);
        $saldos = [];
        foreach ([FinanzasParametros::CUENTA_EFECTIVO, FinanzasParametros::CUENTA_BANCO, FinanzasParametros::CUENTA_BANCARD] as $cuenta) {
            $normalizado = FinanzasParametros::normalizarMonto($validated['fin_saldo_inicial_'.$cuenta] ?? '');
            if ($normalizado !== '' && ! is_numeric($normalizado)) {
                return back()->withInput()->withErrors([
                    'fin_saldo_inicial_'.$cuenta => 'El saldo inicial debe ser un importe.',
                ]);
            }
            if ($normalizado !== '' && (float) $normalizado < 0) {
                return back()->withInput()->withErrors([
                    'fin_saldo_inicial_'.$cuenta => 'El saldo inicial no puede ser negativo.',
                ]);
            }
            $saldos['fin_saldo_inicial_'.$cuenta] = $normalizado === '' ? '' : (string) round((float) $normalizado, 2);
        }

        $haySaldo = collect($saldos)->contains(fn (string $valor) => $valor !== '' && (float) $valor > 0);
        if ($haySaldo && empty($validated['fin_saldo_inicial_fecha'])) {
            return back()->withInput()->withErrors([
                'fin_saldo_inicial_fecha' => 'Indique la fecha del saldo inicial.',
            ]);
        }

        $comision = trim((string) ($validated['fin_comision_bancard'] ?? ''));
        FinanzasParametros::guardar([
            'fin_fecha_corte' => $validated['fin_fecha_corte'] ?? '',
            'fin_comision_bancard' => $comision === '' ? '' : (string) round((float) $comision, 2),
            'fin_saldo_inicial_fecha' => $validated['fin_saldo_inicial_fecha'] ?? '',
            ...$saldos,
            ...collect(FinanzasParametros::formas())->mapWithKeys(
                fn ($etiqueta, $forma) => ['fin_cuenta_'.$forma => $validated['fin_cuenta_'.$forma]]
            )->all(),
        ]);

        try {
            $libro->sincronizarSaldosIniciales(auth()->id());
        } catch (PeriodoCerradoException|InvalidArgumentException $e) {
            return redirect()->route('configuracion.finanzas')->with('success', 'Parámetros guardados.')->withErrors([
                'fin_saldo_inicial_fecha' => $e->getMessage(),
            ]);
        }

        return redirect()->route('configuracion.finanzas')->with('success', 'Parámetros financieros guardados.');
    }
}
