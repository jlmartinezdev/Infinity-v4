<?php

namespace App\Http\Controllers;

use App\Models\CotizacionDolar;
use Illuminate\Http\Request;

class CotizacionDolarController extends Controller
{
    public function index()
    {
        $actual = CotizacionDolar::actual();
        $cotizaciones = CotizacionDolar::with('usuario')
            ->orderByDesc('id')
            ->paginate(20);

        return view('cotizacion-dolar.index', compact('actual', 'cotizaciones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'valor' => ['required', 'numeric', 'min:1', 'max:999999'],
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:255'],
        ], [
            'valor.required' => 'Indicá cuántos guaraníes equivale 1 dólar.',
            'valor.min' => 'El tipo de cambio debe ser mayor a 0.',
        ]);

        CotizacionDolar::create([
            'valor' => $validated['valor'],
            'fecha' => $validated['fecha'],
            'notas' => $validated['notas'] ?? null,
            'usuario_id' => auth()->id(),
        ]);

        return redirect()
            ->route('cotizacion-dolar.index')
            ->with('success', 'Cotización registrada. Las próximas compras y ventas usarán este valor como referencia.');
    }
}
