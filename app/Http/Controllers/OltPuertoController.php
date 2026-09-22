<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\OltPuerto;
use App\Models\SalidaPon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OltPuertoController extends Controller
{
    public function create(Olt $olt)
    {
        $olt->load(['nodo', 'oltPuertos']);
        $selector = $this->datosSelectorPuertos($olt);

        return view('olt-puertos.create', array_merge(compact('olt'), $selector));
    }

    public function store(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
            'registrar_salida' => ['nullable', 'in:0,1'],
            'codigo' => ['nullable', 'string', 'max:50', 'required_if:registrar_salida,1'],
            'tipo_modulo' => ['nullable', 'string', Rule::in(SalidaPon::TIPOS_MODULO)],
            'potencia_salida' => ['nullable', 'numeric'],
        ], [
            'codigo.required_if' => 'Indicá un código para la salida de fibra.',
        ]);

        if (OltPuerto::where('olt_id', $olt->olt_id)->where('numero', $validated['numero'])->exists()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Ya existe un puerto con ese número en este OLT.');
        }

        $registrarSalida = (string) ($validated['registrar_salida'] ?? '0') === '1';
        if ($registrarSalida && ! $olt->nodo_id) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['registrar_salida' => 'El OLT no tiene nodo. Asignalo antes de registrar la salida de fibra.']);
        }

        $puerto = DB::transaction(function () use ($olt, $validated, $registrarSalida) {
            $puerto = OltPuerto::create([
                'olt_id' => $olt->olt_id,
                'numero' => $validated['numero'],
                'tipo_pon' => $validated['tipo_pon'],
                'estado' => $validated['estado'] ?? 'activo',
                'notas' => $validated['notas'] ?? null,
            ]);

            if ($registrarSalida) {
                $codigo = trim((string) ($validated['codigo'] ?? ''));
                if ($codigo === '') {
                    $codigo = 'PON-'.$puerto->numero;
                }

                SalidaPon::create([
                    'olt_id' => $olt->olt_id,
                    'olt_puerto_id' => $puerto->olt_puerto_id,
                    'nodo_id' => $olt->nodo_id,
                    'tipo_modulo' => $validated['tipo_modulo'] ?? null,
                    'potencia_salida' => $validated['potencia_salida'] ?? null,
                    'codigo' => $codigo,
                    'puerto_olt' => (int) $puerto->numero,
                    'estado' => $validated['estado'] ?? 'activo',
                ]);
            }

            return $puerto;
        });

        $mensaje = $registrarSalida
            ? 'Puerto PON '.$puerto->numero.' y salida de fibra creados correctamente.'
            : 'Puerto PON creado correctamente.';

        return redirect()->route('sistema.olts.show', $olt)->with('success', $mensaje);
    }

    public function edit(OltPuerto $oltPuerto)
    {
        $oltPuerto->load(['olt.nodo', 'salidaPon']);
        $selector = $this->datosSelectorPuertos($oltPuerto->olt, (int) $oltPuerto->numero);

        return view('olt-puertos.edit', array_merge(compact('oltPuerto'), $selector));
    }

    public function update(Request $request, OltPuerto $oltPuerto)
    {
        $validated = $request->validate([
            'numero' => ['required', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ]);

        $oltPuerto->loadMissing('salidaPon');

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

        if ($oltPuerto->salidaPon && (int) $oltPuerto->salidaPon->puerto_olt !== (int) $oltPuerto->numero) {
            $oltPuerto->salidaPon->update([
                'puerto_olt' => (int) $oltPuerto->numero,
            ]);
        }

        return redirect()->route('sistema.olts.show', $oltPuerto->olt)->with('success', 'Puerto PON actualizado correctamente.');
    }

    public function destroy(OltPuerto $oltPuerto)
    {
        $olt = $oltPuerto->olt;
        $oltPuerto->delete();

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'Puerto PON eliminado correctamente.');
    }

    /**
     * @return array{ocupados: list<int>, maxGrid: int, siguiente: int}
     */
    private function datosSelectorPuertos(Olt $olt, ?int $exceptoNumero = null): array
    {
        $olt->loadMissing('oltPuertos');
        $ocupados = $olt->oltPuertos
            ->pluck('numero')
            ->map(fn ($n) => (int) $n)
            ->all();

        $capacidad = (int) ($olt->cantidad_puerto ?: 0);
        $maxOcupado = $ocupados === [] ? 0 : max($ocupados);
        $maxGrid = $capacidad > 0 ? $capacidad : SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT;
        if ($maxOcupado > $maxGrid) {
            $maxGrid = $maxOcupado;
        }

        $siguiente = $maxGrid + 1;
        for ($i = 1; $i <= $maxGrid; $i++) {
            if ($exceptoNumero !== null && $i === $exceptoNumero) {
                $siguiente = $i;
                break;
            }
            if (! in_array($i, $ocupados, true)) {
                $siguiente = $i;
                break;
            }
        }

        return [
            'ocupados' => $ocupados,
            'maxGrid' => $maxGrid,
            'siguiente' => $siguiente,
        ];
    }
}
