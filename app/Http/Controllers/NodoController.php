<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Services\NodoPppoeMigracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodoController extends Controller
{
    /**
     * Listar nodos.
     */
    public function index(Request $request)
    {
        $query = Nodo::query()->orderBy('descripcion');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($query) use ($q) {
                $query->where('descripcion', 'like', "%{$q}%")
                    ->orWhere('ciudad', 'like', "%{$q}%")
                    ->orWhere('coordenas_gps', 'like', "%{$q}%");
            });
        }

        $nodos = $query->paginate(15)->withQueryString();

        return view('nodos.index', compact('nodos'));
    }

    /**
     * Formulario crear nodo.
     */
    public function create()
    {
        return view('nodos.create');
    }

    /**
     * Guardar nuevo nodo.
     */
    public function store(Request $request)
    {
        $validated = $this->validarNodo($request);

        Nodo::create($validated);

        return redirect()->route('nodos.index')->with('success', 'Nodo creado correctamente.');
    }

    /**
     * Formulario editar nodo.
     */
    public function edit($nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();
        return view('nodos.edit', compact('nodo'));
    }

    /**
     * Actualizar nodo.
     */
    public function update(Request $request, $nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();

        $validated = $this->validarNodo($request);

        $nodo->update($validated);

        return redirect()->route('nodos.index')->with('success', 'Nodo actualizado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validarNodo(Request $request): array
    {
        $validated = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:120'],
            'coordenas_gps' => ['nullable', 'string', 'max:50'],
            'ciudad' => ['nullable', 'string', 'max:50'],
            'tecnologia_gpon' => ['nullable', 'boolean'],
            'tecnologia_wireless' => ['nullable', 'boolean'],
        ]);

        $validated['tecnologia_gpon'] = $request->boolean('tecnologia_gpon');
        $validated['tecnologia_wireless'] = $request->boolean('tecnologia_wireless');

        if (! $validated['tecnologia_gpon'] && ! $validated['tecnologia_wireless']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tecnologia_gpon' => 'Seleccioná al menos una tecnología que maneje el nodo (GPON o Wireless).',
            ]);
        }

        return $validated;
    }

    /**
     * Herramienta: mover/copiar usuarios PPPoE entre routers del nodo.
     */
    public function migrarPppoe($nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();

        return view('nodos.migrar-pppoe', compact('nodo'));
    }

    public function migrarPppoeDatos($nodo, NodoPppoeMigracionService $service): JsonResponse
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();

        return response()->json($service->datosIniciales($nodo));
    }

    public function migrarPppoeServicios(Request $request, $nodo, NodoPppoeMigracionService $service): JsonResponse
    {
        Nodo::where('nodo_id', $nodo)->firstOrFail();

        $validated = $request->validate([
            'router_origen_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        return response()->json([
            'servicios' => $service->listarServiciosPorRouter((int) $validated['router_origen_id']),
        ]);
    }

    public function migrarPppoePools(Request $request, $nodo, NodoPppoeMigracionService $service): JsonResponse
    {
        Nodo::where('nodo_id', $nodo)->firstOrFail();

        $validated = $request->validate([
            'router_destino_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        return response()->json([
            'pools' => $service->listarPoolsPorRouter((int) $validated['router_destino_id']),
        ]);
    }

    public function migrarPppoeEjecutar(Request $request, $nodo, NodoPppoeMigracionService $service): JsonResponse
    {
        Nodo::where('nodo_id', $nodo)->firstOrFail();

        $validated = $request->validate([
            'modo' => ['required', 'in:mover,copiar'],
            'router_origen_id' => ['required', 'integer', 'exists:routers,router_id'],
            'router_destino_id' => ['required', 'integer', 'exists:routers,router_id'],
            'pool_destino_id' => ['required_if:modo,mover', 'nullable', 'integer', 'exists:router_ip_pools,pool_id'],
            'asignar_ip_automatica' => ['nullable', 'boolean'],
            'servicio_ids' => ['required', 'array', 'min:1'],
            'servicio_ids.*' => ['integer', 'exists:servicios,servicio_id'],
        ]);

        try {
            $resultado = $service->ejecutar(
                $validated['modo'],
                (int) $validated['router_origen_id'],
                (int) $validated['router_destino_id'],
                (int) ($validated['pool_destino_id'] ?? 0),
                array_map('intval', $validated['servicio_ids']),
                $request->boolean('asignar_ip_automatica', true)
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $total = count($validated['servicio_ids']);
        $errores = count($resultado['errores']);

        return response()->json([
            'success' => $errores === 0,
            'message' => $errores === 0
                ? "Procesados {$resultado['ok']} de {$total} servicio(s) correctamente."
                : "Completados {$resultado['ok']} de {$total}; {$errores} con error.",
            'resultado' => $resultado,
        ]);
    }

    /**
     * Eliminar nodo.
     */
    public function destroy($nodo)
    {
        $nodo = Nodo::where('nodo_id', $nodo)->firstOrFail();
        $nodo->delete();

        return redirect()->route('nodos.index')->with('success', 'Nodo eliminado correctamente.');
    }
}
