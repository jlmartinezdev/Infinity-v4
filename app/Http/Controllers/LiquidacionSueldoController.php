<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LiquidacionSueldoController extends Controller
{
    public function create(Request $request)
    {
        $staff = User::query()
            ->staff()
            ->activos()
            ->with('rol')
            ->orderBy('name')
            ->get(['usuario_id', 'name', 'cedula', 'cargo', 'salario_basico', 'banco', 'cuenta_bancaria']);

        $ipsPct = (float) config('liquidacion.ips_porcentaje', 9);
        $salarioMinimo = (int) config('liquidacion.salario_minimo', 2899048);

        return view('liquidacion.create', [
            'staff' => $staff,
            'ipsPct' => $ipsPct,
            'salarioMinimo' => $salarioMinimo,
            'ciudad' => config('liquidacion.ciudad'),
            'empleadorNombre' => config('liquidacion.empleador_nombre'),
            'empleadorCi' => config('liquidacion.empleador_ci'),
            'formaPago' => config('liquidacion.forma_pago'),
            'bancoDefault' => config('liquidacion.banco_default'),
            'jornadasDefault' => config('liquidacion.jornadas_default', 24),
            'selectedId' => $request->integer('usuario_id') ?: null,
        ]);
    }

    public function pdf(Request $request)
    {
        $staffIds = User::query()->staff()->pluck('usuario_id')->all();

        $validated = $request->validate([
            'usuario_id' => ['required', 'integer', Rule::in($staffIds)],
            'fecha' => ['required', 'date'],
            'periodo' => ['required', 'string', 'max:40'],
            'jornadas' => ['nullable', 'numeric', 'min:0'],
            'horas_extras_diurnas' => ['nullable', 'numeric', 'min:0'],
            'monto_extras_diurnas' => ['nullable', 'integer', 'min:0'],
            'horas_extras_nocturnas' => ['nullable', 'numeric', 'min:0'],
            'monto_extras_nocturnas' => ['nullable', 'integer', 'min:0'],
            'horas_feriados' => ['nullable', 'numeric', 'min:0'],
            'monto_feriados' => ['nullable', 'integer', 'min:0'],
            'bonificaciones' => ['nullable', 'integer', 'min:0'],
            'ips' => ['nullable', 'integer', 'min:0'],
            'anticipo' => ['nullable', 'integer', 'min:0'],
            'otros_descuentos' => ['nullable', 'integer', 'min:0'],
            'salario_basico' => ['nullable', 'integer', 'min:0'],
            'forma_pago' => ['required', 'string', Rule::in(['Transferencia bancaria', 'Efectivo'])],
            'banco' => ['nullable', 'string', 'max:80'],
            'cuenta_bancaria' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::query()->staff()->findOrFail($validated['usuario_id']);

        $salario = (int) ($validated['salario_basico']
            ?? $user->salario_basico
            ?? config('liquidacion.salario_minimo', 2899048));

        $extrasDiurnas = (int) ($validated['monto_extras_diurnas'] ?? 0);
        $extrasNocturnas = (int) ($validated['monto_extras_nocturnas'] ?? 0);
        $feriados = (int) ($validated['monto_feriados'] ?? 0);
        $bonificaciones = (int) ($validated['bonificaciones'] ?? 0);

        $totalHaberes = $salario + $extrasDiurnas + $extrasNocturnas + $feriados + $bonificaciones;

        $ipsPct = (float) config('liquidacion.ips_porcentaje', 9);
        $ips = isset($validated['ips']) && $request->filled('ips')
            ? (int) $validated['ips']
            : (int) round($salario * ($ipsPct / 100));

        $anticipo = (int) ($validated['anticipo'] ?? 0);
        $otros = (int) ($validated['otros_descuentos'] ?? 0);
        $totalDescuentos = $ips + $anticipo + $otros;
        $neto = max(0, $totalHaberes - $totalDescuentos);

        $formaPago = $validated['forma_pago'];
        $esTransferencia = $formaPago === 'Transferencia bancaria';
        $banco = $esTransferencia
            ? ($validated['banco'] ?? $user->banco ?? config('liquidacion.banco_default'))
            : null;
        $cuenta = $esTransferencia
            ? ($validated['cuenta_bancaria'] ?? $user->cuenta_bancaria)
            : null;

        $data = [
            'ciudad' => config('liquidacion.ciudad'),
            'fecha' => $validated['fecha'],
            'periodo' => $validated['periodo'],
            'empleador_nombre' => config('liquidacion.empleador_nombre'),
            'empleador_ci' => config('liquidacion.empleador_ci'),
            'trabajador' => $user->name,
            'trabajador_ci' => $user->cedula,
            'cargo' => $user->cargo,
            'salario' => $salario,
            'jornadas' => $validated['jornadas'] ?? config('liquidacion.jornadas_default', 24),
            'horas_extras_diurnas' => $validated['horas_extras_diurnas'] ?? null,
            'monto_extras_diurnas' => $extrasDiurnas,
            'horas_extras_nocturnas' => $validated['horas_extras_nocturnas'] ?? null,
            'monto_extras_nocturnas' => $extrasNocturnas,
            'horas_feriados' => $validated['horas_feriados'] ?? null,
            'monto_feriados' => $feriados,
            'bonificaciones' => $bonificaciones,
            'total_haberes' => $totalHaberes,
            'ips' => $ips,
            'anticipo' => $anticipo,
            'otros_descuentos' => $otros,
            'total_descuentos' => $totalDescuentos,
            'neto' => $neto,
            'forma_pago' => $formaPago,
            'banco' => $banco,
            'cuenta_bancaria' => $cuenta,
            'observacion' => $validated['observacion']
                ?? 'Se deja constancia de que el trabajador recibió/percibió el neto indicado.',
        ];

        $pdf = Pdf::loadView('liquidacion.pdf', $data)
            ->setPaper('a4', 'portrait');

        $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user->name) ?: 'staff';
        $filename = 'liquidacion_'.$slug.'_'.now()->format('Ymd').'.pdf';

        return $pdf->stream($filename);
    }
}
