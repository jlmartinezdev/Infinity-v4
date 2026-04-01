<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\Nodo;
use App\Models\OltPuerto;
use App\Models\SalidaPon;
use Illuminate\Http\Request;

class SalidaPonController extends Controller
{
    public function index(Request $request)
    {
        $query = SalidaPon::with(['nodo', 'cajaNap', 'oltPuerto.olt'])->orderBy('codigo');

        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', $request->nodo_id);
        }

        $salidas = $query->paginate(15)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('salida-pons.index', compact('salidas', 'nodos'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $cajas = CajaNap::with('nodo')->orderBy('codigo')->get();
        $oltPuertos = OltPuerto::with(['olt.nodo', 'olt.oltMarca'])->orderBy('olt_id')->orderBy('numero')->get();
        return view('salida-pons.create', compact('nodos', 'cajas', 'oltPuertos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'caja_nap_id' => ['nullable', 'exists:caja_naps,caja_nap_id'],
            'olt_puerto_id' => ['nullable', 'exists:olt_puertos,olt_puerto_id'],
            'codigo' => ['required', 'string', 'max:50'],
            'puerto' => ['nullable', 'integer', 'min:1'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['puerto'] = $validated['puerto'] ?? 1;
        $validated['estado'] = $validated['estado'] ?? 'activo';

        SalidaPon::create($validated);

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON creada correctamente.');
    }

    public function edit(SalidaPon $salidaPon)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $cajas = CajaNap::with('nodo')->orderBy('codigo')->get();
        $oltPuertos = OltPuerto::with(['olt.nodo', 'olt.oltMarca'])->orderBy('olt_id')->orderBy('numero')->get();
        return view('salida-pons.edit', compact('salidaPon', 'nodos', 'cajas', 'oltPuertos'));
    }

    public function update(Request $request, SalidaPon $salidaPon)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'caja_nap_id' => ['nullable', 'exists:caja_naps,caja_nap_id'],
            'olt_puerto_id' => ['nullable', 'exists:olt_puertos,olt_puerto_id'],
            'codigo' => ['required', 'string', 'max:50'],
            'puerto' => ['nullable', 'integer', 'min:1'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $salidaPon->update($validated);

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON actualizada correctamente.');
    }

    public function destroy(SalidaPon $salidaPon)
    {
        $salidaPon->delete();
        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON eliminada correctamente.');
    }
}
