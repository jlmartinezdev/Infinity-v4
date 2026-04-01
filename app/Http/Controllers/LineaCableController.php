<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\FibraColor;
use App\Models\LineaCable;
use App\Models\Nodo;
use App\Models\SalidaPon;
use App\Models\SplitterPrimario;
use App\Models\SplitterSecundario;
use Illuminate\Http\Request;

class LineaCableController extends Controller
{
    public function index(Request $request)
    {
        $lineas = LineaCable::with('fibraColor')
            ->orderBy('linea_cable_id')
            ->paginate(20);

        return view('lineas-cable.index', compact('lineas'));
    }

    public function create(Request $request)
    {
        $fibraColores = FibraColor::activos()->orderBy('nombre')->get();
        $nodos = Nodo::orderBy('descripcion')->get();
        $cajas = CajaNap::with('nodo')->orderBy('codigo')->get();
        $splittersPrimarios = SplitterPrimario::with('cajaNap')->orderBy('codigo')->get();
        $splittersSecundarios = SplitterSecundario::with(['cajaNap', 'splitterPrimario'])->orderBy('codigo')->get();
        $salidaPons = SalidaPon::with(['nodo', 'cajaNap'])->orderBy('codigo')->get();

        return view('lineas-cable.create', compact(
            'fibraColores', 'nodos', 'cajas', 'splittersPrimarios', 'splittersSecundarios', 'salidaPons'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fibra_color_id' => ['required', 'exists:fibra_colores,fibra_color_id'],
            'origen_tipo' => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'origen_id' => ['required', 'integer', 'min:1'],
            'destino_tipo' => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'destino_id' => ['required', 'integer', 'min:1'],
            'longitud_metros' => ['nullable', 'numeric', 'min:0'],
            'coordenadas' => ['nullable', 'array'],
            'coordenadas.*' => ['array', 'size:2'],
            'coordenadas.*.0' => ['numeric'],
            'coordenadas.*.1' => ['numeric'],
            'notas' => ['nullable', 'string'],
        ]);

        $coords = $request->coordenadas;
        if (is_array($coords)) {
            $validated['coordenadas'] = array_values(array_map(fn ($c) => [(float) ($c[0] ?? 0), (float) ($c[1] ?? 0)], $coords));
        } else {
            $validated['coordenadas'] = null;
        }

        LineaCable::create($validated);

        return redirect()->route('lineas-cable.index')->with('success', 'Línea de cable creada correctamente.');
    }

    public function edit(LineaCable $lineaCable)
    {
        $lineaCable->load('fibraColor');
        $fibraColores = FibraColor::activos()->orderBy('nombre')->get();
        $nodos = Nodo::orderBy('descripcion')->get();
        $cajas = CajaNap::with('nodo')->orderBy('codigo')->get();
        $splittersPrimarios = SplitterPrimario::with('cajaNap')->orderBy('codigo')->get();
        $splittersSecundarios = SplitterSecundario::with(['cajaNap', 'splitterPrimario'])->orderBy('codigo')->get();
        $salidaPons = SalidaPon::with(['nodo', 'cajaNap'])->orderBy('codigo')->get();

        return view('lineas-cable.edit', compact(
            'lineaCable', 'fibraColores', 'nodos', 'cajas', 'splittersPrimarios', 'splittersSecundarios', 'salidaPons'
        ));
    }

    public function update(Request $request, LineaCable $lineaCable)
    {
        $validated = $request->validate([
            'fibra_color_id' => ['required', 'exists:fibra_colores,fibra_color_id'],
            'origen_tipo' => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'origen_id' => ['required', 'integer', 'min:1'],
            'destino_tipo' => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'destino_id' => ['required', 'integer', 'min:1'],
            'longitud_metros' => ['nullable', 'numeric', 'min:0'],
            'coordenadas' => ['nullable', 'array'],
            'coordenadas.*' => ['array', 'size:2'],
            'notas' => ['nullable', 'string'],
        ]);

        $coords = $request->coordenadas;
        if (is_array($coords)) {
            $validated['coordenadas'] = array_values(array_map(fn ($c) => [(float) ($c[0] ?? 0), (float) ($c[1] ?? 0)], $coords));
        } else {
            $validated['coordenadas'] = null;
        }

        $lineaCable->update($validated);

        return redirect()->route('lineas-cable.index')->with('success', 'Línea de cable actualizada correctamente.');
    }

    public function destroy(LineaCable $lineaCable)
    {
        $lineaCable->delete();
        return redirect()->route('lineas-cable.index')->with('success', 'Línea de cable eliminada correctamente.');
    }
}
