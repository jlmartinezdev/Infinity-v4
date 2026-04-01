<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\OltPuerto;
use Illuminate\Http\Request;

class OltPuertoController extends Controller
{
    public function create(Olt $olt)
    {
        return view('olt-puertos.create', compact('olt'));
    }

    public function store(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $validated['olt_id'] = $olt->olt_id;
        $validated['estado'] = $validated['estado'] ?? 'activo';

        if (OltPuerto::where('olt_id', $olt->olt_id)->where('numero', $validated['numero'])->exists()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Ya existe un puerto con ese número en este OLT.');
        }

        OltPuerto::create($validated);

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'Puerto PON creado correctamente.');
    }

    public function edit(OltPuerto $oltPuerto)
    {
        $oltPuerto->load('olt');

        return view('olt-puertos.edit', compact('oltPuerto'));
    }

    public function update(Request $request, OltPuerto $oltPuerto)
    {
        $validated = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $exists = OltPuerto::where('olt_id', $oltPuerto->olt_id)
            ->where('numero', $validated['numero'])
            ->where('olt_puerto_id', '!=', $oltPuerto->olt_puerto_id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Ya existe un puerto con ese número en este OLT.');
        }

        $oltPuerto->update($validated);

        return redirect()->route('sistema.olts.show', $oltPuerto->olt)->with('success', 'Puerto PON actualizado correctamente.');
    }

    public function destroy(OltPuerto $oltPuerto)
    {
        $olt = $oltPuerto->olt;
        $oltPuerto->delete();

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'Puerto PON eliminado correctamente.');
    }
}
