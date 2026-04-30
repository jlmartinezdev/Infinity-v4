<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Olt;
use App\Models\OltPuerto;
use App\Models\SalidaPon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalidaPonController extends Controller
{
    public function index(Request $request)
    {
        $query = SalidaPon::with(['nodo', 'olt.nodo', 'oltPuerto', 'cajaNaps'])->orderBy('codigo');

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
        $olts = Olt::with(['nodo', 'oltPuertos' => fn ($q) => $q->orderBy('numero')])
            ->orderBy('codigo')
            ->orderBy('ip')
            ->get();

        return view('salida-pons.create', compact('nodos', 'olts'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'tipo_modulo' => $request->filled('tipo_modulo') ? $request->input('tipo_modulo') : null,
        ]);

        $validated = $this->validatedBase($request);

        $normalized = $this->normalizarOltPuertoYPuerto(
            $validated,
            $request->input('olt_puerto_id'),
            $request->input('olt_id'),
            $request->input('puerto_olt'),
            null
        );

        SalidaPon::create($normalized);

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON creada correctamente.');
    }

    public function edit(SalidaPon $salidaPon)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $olts = Olt::with(['nodo', 'oltPuertos' => fn ($q) => $q->orderBy('numero')])
            ->orderBy('codigo')
            ->orderBy('ip')
            ->get();

        $salidaPon->loadMissing('oltPuerto');

        return view('salida-pons.edit', compact('salidaPon', 'nodos', 'olts'));
    }

    public function update(Request $request, SalidaPon $salidaPon)
    {
        $request->merge([
            'tipo_modulo' => $request->filled('tipo_modulo') ? $request->input('tipo_modulo') : null,
        ]);

        $validated = $this->validatedBase($request, $salidaPon);

        $normalized = $this->normalizarOltPuertoYPuerto(
            $validated,
            $request->input('olt_puerto_id'),
            $request->input('olt_id'),
            $request->input('puerto_olt'),
            $salidaPon
        );

        $salidaPon->update($normalized);

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON actualizada correctamente.');
    }

    public function destroy(SalidaPon $salidaPon)
    {
        $salidaPon->delete();

        return redirect()->route('sistema.salida-pons.index')->with('success', 'Salida PON eliminada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedBase(Request $request, ?SalidaPon $salidaPonExistente = null): array
    {
        $tiposModulo = SalidaPon::TIPOS_MODULO;
        if ($salidaPonExistente && $salidaPonExistente->tipo_modulo && ! in_array($salidaPonExistente->tipo_modulo, $tiposModulo, true)) {
            $tiposModulo[] = $salidaPonExistente->tipo_modulo;
        }

        return $request->validate([
            'olt_id' => ['nullable', 'exists:olts,olt_id'],
            'olt_puerto_id' => ['nullable', 'integer', 'exists:olt_puertos,olt_puerto_id'],
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'tipo_modulo' => ['nullable', 'string', Rule::in($tiposModulo)],
            'potencia_salida' => ['nullable', 'numeric'],
            'codigo' => ['required', 'string', 'max:50'],
            'puerto_olt' => ['nullable', 'integer', 'min:1', 'max:128'],
            'estado' => ['nullable', 'string', 'max:20'],
            'nota' => ['nullable', 'string'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizarOltPuertoYPuerto(
        array $validated,
        mixed $oltPuertoIdInput,
        mixed $oltIdInput,
        mixed $puertoOltInput,
        ?SalidaPon $existente
    ): array {
        $oltPuertoId = $oltPuertoIdInput !== null && $oltPuertoIdInput !== '' ? (int) $oltPuertoIdInput : null;
        $oltId = ! empty($validated['olt_id']) ? (int) $validated['olt_id'] : null;

        if ($oltPuertoId) {
            $op = OltPuerto::with('olt')->find($oltPuertoId);
            if (! $op) {
                throw ValidationException::withMessages(['olt_puerto_id' => 'Puerto PON no válido.']);
            }
            if ((int) $op->olt->nodo_id !== (int) $validated['nodo_id']) {
                throw ValidationException::withMessages(['olt_puerto_id' => 'El puerto debe pertenecer al nodo elegido.']);
            }
            if ($oltId !== null && (int) $oltId !== (int) $op->olt_id) {
                throw ValidationException::withMessages(['olt_puerto_id' => 'El puerto debe corresponder al OLT seleccionado.']);
            }

            $duplicado = SalidaPon::query()
                ->where('olt_puerto_id', $op->olt_puerto_id)
                ->when($existente, fn ($q) => $q->where('salida_pon_id', '!=', $existente->salida_pon_id))
                ->exists();
            if ($duplicado) {
                throw ValidationException::withMessages(['olt_puerto_id' => 'Ya existe una salida PON asociada a ese puerto del OLT.']);
            }

            $validated['olt_id'] = $op->olt_id;
            $validated['olt_puerto_id'] = $op->olt_puerto_id;
            $validated['puerto_olt'] = (int) $op->numero;

            return $validated;
        }

        $validated['olt_puerto_id'] = null;

        if ($oltId) {
            $olt = Olt::withCount('oltPuertos')->find($oltId);
            if (! $olt) {
                throw ValidationException::withMessages(['olt_id' => 'OLT no válido.']);
            }
            if ((int) $olt->nodo_id !== (int) $validated['nodo_id']) {
                throw ValidationException::withMessages([
                    'nodo_id' => 'El nodo debe coincidir con el del OLT seleccionado.',
                ]);
            }

            if ($olt->olt_puertos_count > 0) {
                throw ValidationException::withMessages([
                    'olt_puerto_id' => 'Seleccioná un puerto PON del listado o cargá puertos en el OLT.',
                ]);
            }

            $max = $this->maxPuertosPermitidos($oltId);
            $puerto = (int) ($puertoOltInput ?? 1);
            Validator::make(
                ['puerto_olt' => $puerto],
                ['puerto_olt' => ['required', 'integer', 'min:1', 'max:'.$max]],
                [],
                ['puerto_olt' => 'puerto OLT']
            )->validate();

            $validated['olt_id'] = $oltId;
            $validated['puerto_olt'] = $puerto;

            return $validated;
        }

        $validated['olt_id'] = null;
        $validated['puerto_olt'] = (int) ($puertoOltInput ?? 1);
        if ($validated['puerto_olt'] < 1) {
            $validated['puerto_olt'] = 1;
        }

        return $validated;
    }

    /**
     * Cantidad de puertos disponibles en el selector numérico según el OLT (cantidad_puerto) o un máximo por defecto.
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
