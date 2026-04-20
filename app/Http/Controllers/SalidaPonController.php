<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Olt;
use App\Models\SalidaPon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalidaPonController extends Controller
{
    public function index(Request $request)
    {
        $query = SalidaPon::with(['nodo', 'olt.nodo', 'cajaNaps'])->orderBy('codigo');

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
        $olts = Olt::with('nodo')->orderBy('codigo')->orderBy('ip')->get();

        return view('salida-pons.create', compact('nodos', 'olts'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'tipo_modulo' => $request->filled('tipo_modulo') ? $request->input('tipo_modulo') : null,
        ]);

        $maxPuerto = $this->maxPuertosPermitidos($request->input('olt_id'));

        $validated = $request->validate([
            'olt_id' => ['nullable', 'exists:olts,olt_id'],
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'tipo_modulo' => ['nullable', 'string', Rule::in(SalidaPon::TIPOS_MODULO)],
            'potencia_salida' => ['nullable', 'numeric'],
            'codigo' => ['required', 'string', 'max:50'],
            'puerto_olt' => ['nullable', 'integer', 'min:1', 'max:'.$maxPuerto],
            'estado' => ['nullable', 'string', 'max:20'],
            'nota' => ['nullable', 'string'],
        ]);

        if (! empty($validated['olt_id'])) {
            $olt = Olt::find($validated['olt_id']);
            if ($olt && (int) $olt->nodo_id !== (int) $validated['nodo_id']) {
                return redirect()->back()->withInput()->withErrors([
                    'nodo_id' => 'El nodo debe coincidir con el del OLT seleccionado.',
                ]);
            }
        }

        $validated['puerto_olt'] = $validated['puerto_olt'] ?? 1;
        $validated['estado'] = $validated['estado'] ?? 'activo';
        $validated['olt_id'] = ! empty($validated['olt_id']) ? (int) $validated['olt_id'] : null;

        SalidaPon::create($validated);

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON creada correctamente.');
    }

    public function edit(SalidaPon $salidaPon)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $olts = Olt::with('nodo')->orderBy('codigo')->orderBy('ip')->get();

        return view('salida-pons.edit', compact('salidaPon', 'nodos', 'olts'));
    }

    public function update(Request $request, SalidaPon $salidaPon)
    {
        $request->merge([
            'tipo_modulo' => $request->filled('tipo_modulo') ? $request->input('tipo_modulo') : null,
        ]);

        $maxPuerto = $this->maxPuertosPermitidos($request->input('olt_id'));
        $tiposModulo = SalidaPon::TIPOS_MODULO;
        if ($salidaPon->tipo_modulo && ! in_array($salidaPon->tipo_modulo, $tiposModulo, true)) {
            $tiposModulo[] = $salidaPon->tipo_modulo;
        }

        $validated = $request->validate([
            'olt_id' => ['nullable', 'exists:olts,olt_id'],
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'tipo_modulo' => ['nullable', 'string', Rule::in($tiposModulo)],
            'potencia_salida' => ['nullable', 'numeric'],
            'codigo' => ['required', 'string', 'max:50'],
            'puerto_olt' => ['nullable', 'integer', 'min:1', 'max:'.$maxPuerto],
            'estado' => ['nullable', 'string', 'max:20'],
            'nota' => ['nullable', 'string'],
        ]);

        if (! empty($validated['olt_id'])) {
            $olt = Olt::find($validated['olt_id']);
            if ($olt && (int) $olt->nodo_id !== (int) $validated['nodo_id']) {
                return redirect()->back()->withInput()->withErrors([
                    'nodo_id' => 'El nodo debe coincidir con el del OLT seleccionado.',
                ]);
            }
        }

        $validated['olt_id'] = ! empty($validated['olt_id']) ? (int) $validated['olt_id'] : null;

        $salidaPon->update($validated);

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON actualizada correctamente.');
    }

    public function destroy(SalidaPon $salidaPon)
    {
        $salidaPon->delete();

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON eliminada correctamente.');
    }

    /**
     * Cantidad de puertos disponibles en el selector según el OLT (cantidad_puerto) o un máximo por defecto.
     */
    private function maxPuertosPermitidos(null|string|int $oltId): int
    {
        if (empty($oltId)) {
            return SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT;
        }
        $olt = Olt::find((int) $oltId);
        if (! $olt) {
            return SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT;
        }
        $n = (int) ($olt->cantidad_puerto ?? 0);

        return $n > 0 ? $n : SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT;
    }
}
