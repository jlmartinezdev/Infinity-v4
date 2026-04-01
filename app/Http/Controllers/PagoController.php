<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Compra;
use App\Models\Gasto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    /**
     * Registrar pago para compra o gasto.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:compra,gasto'],
            'referencia_id' => ['required', 'integer'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['required', 'string', 'in:efectivo,transferencia,cheque,tarjeta'],
            'referencia_pago' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string'],
        ]);

        $tipo = $validated['tipo'];
        $referenciaId = $validated['referencia_id'];

        if ($tipo === 'compra') {
            $compra = Compra::findOrFail($referenciaId);
            if ($compra->estado === 'anulado') {
                return redirect()->back()->with('error', 'No se puede registrar pago en una compra anulada.');
            }
        }

        DB::transaction(function () use ($validated, $tipo, $referenciaId) {
            Pago::create([
                'tipo' => $tipo,
                'referencia_id' => $referenciaId,
                'fecha' => $validated['fecha'],
                'monto' => $validated['monto'],
                'metodo_pago' => $validated['metodo_pago'],
                'referencia_pago' => $validated['referencia_pago'] ?? null,
                'notas' => $validated['notas'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            if ($tipo === 'compra') {
                $compra = Compra::findOrFail($referenciaId);
                $nuevoPagado = (float) $compra->pagado + (float) $validated['monto'];
                $compra->update(['pagado' => $nuevoPagado]);

                $estado = $nuevoPagado >= (float) $compra->total ? 'pagado' : 'parcial';
                $compra->update(['estado' => $estado]);
            } else {
                $gasto = Gasto::findOrFail($referenciaId);
                $gasto->update(['pagado' => true]);
            }
        });

        $mensaje = $tipo === 'compra' ? 'Pago registrado correctamente.' : 'Pago del gasto registrado correctamente.';

        if ($tipo === 'compra') {
            return redirect()->route('compras.show', $referenciaId)->with('success', $mensaje);
        }

        return redirect()->route('gastos.index')->with('success', $mensaje);
    }
}
