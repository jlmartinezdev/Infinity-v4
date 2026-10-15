<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\Nodo;
use App\Models\NodoApWireless;
use App\Models\Olt;
use App\Models\Router;
use App\Models\SalidaPon;
use App\Models\TrabajoRed;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrabajoRedController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->get('q', ''));
        $query = TrabajoRed::with(['nodo', 'usuario', 'asignado']);

        $cola = trim((string) $request->get('cola', ''));
        if ($cola === 'abiertos') {
            $query->whereIn('estado', TrabajoRed::estadosAbiertos());
        } elseif ($cola === 'pendientes' || $cola === 'pendiente') {
            $query->where('estado', 'pendiente');
        } elseif ($cola === 'en_camino') {
            $query->where('estado', 'en_camino');
        } elseif ($cola === 'en_proceso') {
            $query->where('estado', 'en_proceso');
        } elseif ($cola === 'resueltos') {
            $query->whereIn('estado', ['resuelto', 'cerrado']);
        } elseif ($cola === 'alta') {
            $query->where('prioridad', 'alta')->whereIn('estado', TrabajoRed::estadosAbiertos());
        } elseif ($cola === 'sin_asignar') {
            $query->whereNull('asignado_id')->whereIn('estado', TrabajoRed::estadosAbiertos());
        } elseif ($cola === 'en_curso') {
            $query->whereNotIn('estado', ['resuelto', 'cerrado']);
        } elseif ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('prioridad') && $cola !== 'alta') {
            $query->where('prioridad', $request->prioridad);
        }
        if ($request->boolean('sin_asignar') && $cola !== 'sin_asignar') {
            $query->whereNull('asignado_id')->whereIn('estado', TrabajoRed::estadosAbiertos());
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', (int) $request->nodo_id);
        }
        if ($request->filled('activo_tipo')) {
            $query->where('activo_tipo', $request->activo_tipo);
        }
        if ($request->filled('asignado_id')) {
            $query->where('asignado_id', (int) $request->asignado_id);
        }

        if ($busqueda !== '') {
            $term = '%'.addcslashes($busqueda, '%_\\').'%';
            $query->where(function ($w) use ($busqueda, $term) {
                if (ctype_digit($busqueda)) {
                    $w->orWhere('id', (int) $busqueda);
                }
                $w->orWhere('titulo', 'like', $term)
                    ->orWhere('descripcion', 'like', $term)
                    ->orWhere('observaciones', 'like', $term)
                    ->orWhere('ubicacion_desde', 'like', $term)
                    ->orWhere('ubicacion_hasta', 'like', $term)
                    ->orWhereHas('nodo', fn ($n) => $n->where('descripcion', 'like', $term))
                    ->orWhereHas('asignado', fn ($u) => $u->where('name', 'like', $term));
                $this->aplicarBusquedaActivo($w, $term);
            });
        }

        $query->orderByDesc('created_at');

        $perPagePermitidos = [10, 15, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 15);
        if (! in_array($perPage, $perPagePermitidos, true)) {
            $perPage = 15;
        }

        $trabajos = $query->paginate($perPage)->withQueryString();

        $kpis = TrabajoRed::query()
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN estado NOT IN ('resuelto', 'cerrado') THEN 1 END) as en_curso,
                COUNT(CASE WHEN estado IN ('pendiente', 'en_camino', 'en_proceso') THEN 1 END) as abiertos,
                COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                COUNT(CASE WHEN estado = 'en_camino' THEN 1 END) as en_camino,
                COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                COUNT(CASE WHEN estado IN ('resuelto', 'cerrado') THEN 1 END) as resueltos,
                COUNT(CASE WHEN prioridad = 'alta' AND estado IN ('pendiente', 'en_camino', 'en_proceso') THEN 1 END) as alta,
                COUNT(CASE WHEN estado IN ('pendiente', 'en_camino', 'en_proceso') AND asignado_id IS NULL THEN 1 END) as sin_asignar
            ")
            ->first();

        $tecnicos = User::staff()->activos()->orderBy('name')->get(['usuario_id', 'name', 'telefono']);
        $tecnicosCampo = User::staff()->activos()
            ->whereHas('rol', fn ($q) => $q->whereRaw('LOWER(descripcion) = ?', ['tecnico']))
            ->whereRaw('LOWER(name) NOT LIKE ?', ['%test%'])
            ->orderBy('name')
            ->get(['usuario_id', 'name', 'telefono']);
        $nodos = Nodo::query()->orderBy('descripcion')->get(['nodo_id', 'descripcion']);

        return view('trabajos-red.index', compact(
            'trabajos',
            'busqueda',
            'tecnicos',
            'tecnicosCampo',
            'nodos',
            'kpis',
            'cola'
        ));
    }

    public function create(): View
    {
        return view('trabajos-red.create', $this->datosFormulario());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validar($request);
        $validated['usuario_id'] = $request->user()->usuario_id;
        $validated['estado'] = $validated['estado'] ?? 'pendiente';
        $validated['prioridad'] = $validated['prioridad'] ?? 'media';
        $validated['nodo_id'] = $this->resolverNodoId($validated);

        $duplicado = TrabajoRed::query()
            ->where('usuario_id', $validated['usuario_id'])
            ->where('tipo', $validated['tipo'])
            ->where('titulo', $validated['titulo'])
            ->where('activo_tipo', $validated['activo_tipo'])
            ->where('activo_id', $validated['activo_id'])
            ->where('created_at', '>=', now()->subMinutes(3))
            ->latest('id')
            ->first();
        if ($duplicado) {
            return back()
                ->withInput()
                ->withErrors(['titulo' => 'Ya existe un trabajo reciente igual (ID #'.$duplicado->id.').']);
        }

        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('trabajos-red', 'public');
        }

        TrabajoRed::create($validated);

        return redirect()->route('trabajos-red.index')->with('success', 'Trabajo de red creado.');
    }

    public function edit(TrabajoRed $trabajoRed): View
    {
        $trabajoRed->load(['nodo', 'usuario', 'asignado']);

        return view('trabajos-red.edit', array_merge(
            $this->datosFormulario(),
            ['trabajo' => $trabajoRed]
        ));
    }

    public function update(Request $request, TrabajoRed $trabajoRed): RedirectResponse
    {
        $validated = $this->validar($request, true);
        $validated['nodo_id'] = $this->resolverNodoId($validated);
        $validated['actualizado_por_id'] = $request->user()->usuario_id;

        if (in_array($validated['estado'], ['resuelto', 'cerrado', 'cancelado'], true) && ! $trabajoRed->fecha_cierre) {
            $validated['fecha_cierre'] = now();
        }

        if ($request->hasFile('imagen')) {
            if ($trabajoRed->imagen) {
                Storage::disk('public')->delete($trabajoRed->imagen);
            }
            $validated['imagen'] = $request->file('imagen')->store('trabajos-red', 'public');
        }

        $trabajoRed->update($validated);

        return redirect()->route('trabajos-red.index')->with('success', 'Trabajo de red actualizado.');
    }

    public function destroy(TrabajoRed $trabajoRed): RedirectResponse
    {
        if ($trabajoRed->imagen) {
            Storage::disk('public')->delete($trabajoRed->imagen);
        }
        $trabajoRed->delete();

        return redirect()->route('trabajos-red.index')->with('success', 'Trabajo de red eliminado.');
    }

    public function updateEstado(Request $request, TrabajoRed $trabajoRed): JsonResponse
    {
        $estadosValidos = implode(',', array_keys(TrabajoRed::estados()));
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:'.$estadosValidos],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'estado' => $validated['estado'],
            'actualizado_por_id' => $request->user()->usuario_id,
        ];
        if ($request->exists('asignado_id')) {
            $data['asignado_id'] = $validated['asignado_id'] ?? null;
        }
        if ($request->filled('observaciones')) {
            $nuevaNota = trim((string) $validated['observaciones']);
            $prefijo = '['.now()->format('d/m/Y H:i').' · '.$request->user()->name.']: ';
            $notaEntrada = $prefijo.$nuevaNota;
            $existente = trim((string) $trabajoRed->observaciones);
            $data['observaciones'] = $existente !== '' ? $existente."\n\n".$notaEntrada : $notaEntrada;
        }
        if (in_array($validated['estado'], ['resuelto', 'cerrado', 'cancelado'], true) && ! $trabajoRed->fecha_cierre) {
            $data['fecha_cierre'] = now();
        }
        $trabajoRed->update($data);

        return response()->json([
            'success' => true,
            'estado' => $trabajoRed->estado,
            'asignado_id' => $trabajoRed->asignado_id,
            'observaciones' => $trabajoRed->observaciones,
        ]);
    }

    public function activos(Request $request): JsonResponse
    {
        $tipo = (string) $request->get('activo_tipo', '');
        $nodoId = (int) $request->get('nodo_id', 0);
        if (! isset(TrabajoRed::activoTipos()[$tipo]) || $nodoId < 1) {
            return response()->json(['items' => []]);
        }

        return response()->json(['items' => $this->opcionesActivo($tipo, $nodoId)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        $nodos = Nodo::query()->orderBy('descripcion')->get(['nodo_id', 'descripcion', 'coordenas_gps']);
        $nodosCoords = [];
        foreach ($nodos as $nodo) {
            $coords = $nodo->getCoordenadasParaMapa();
            if ($coords) {
                $nodosCoords[(string) $nodo->nodo_id] = $coords;
            }
        }

        return [
            'nodos' => $nodos,
            'tecnicos' => User::staff()->activos()->orderBy('name')->get(['usuario_id', 'name']),
            'googleMapsApiKey' => (string) config('services.google.maps_key', ''),
            'nodosCoords' => $nodosCoords,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, bool $requiereEstado = false): array
    {
        $tipos = implode(',', array_keys(TrabajoRed::tipos()));
        $estados = implode(',', array_keys(TrabajoRed::estados()));
        $activos = implode(',', array_keys(TrabajoRed::activoTipos()));

        $rules = [
            'tipo' => ['required', 'string', 'in:'.$tipos],
            'titulo' => ['required', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'prioridad' => ['nullable', 'string', 'in:baja,media,alta'],
            'nodo_id' => ['required', 'integer', 'exists:nodos,nodo_id'],
            'activo_tipo' => ['required', 'string', 'in:'.$activos],
            'activo_id' => ['required', 'integer'],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'imagen' => ['nullable', 'image', 'max:2048'],
            'ubicacion_desde' => ['nullable', 'string', 'max:180'],
            'url_ubicacion_desde' => ['nullable', 'string', 'max:500'],
            'ubicacion_hasta' => ['nullable', 'string', 'max:180'],
            'url_ubicacion_hasta' => ['nullable', 'string', 'max:500'],
        ];
        if ($requiereEstado) {
            $rules['estado'] = ['required', 'string', 'in:'.$estados];
        } else {
            $rules['estado'] = ['nullable', 'string', 'in:'.$estados];
        }

        $validated = $request->validate($rules);
        $validated['titulo'] = trim((string) $validated['titulo']);
        $validated['descripcion'] = isset($validated['descripcion']) ? trim((string) $validated['descripcion']) : null;
        $validated['observaciones'] = isset($validated['observaciones']) ? trim((string) $validated['observaciones']) : null;
        if (($validated['descripcion'] ?? '') === '') {
            $validated['descripcion'] = null;
        }
        if (($validated['observaciones'] ?? '') === '') {
            $validated['observaciones'] = null;
        }
        foreach (['ubicacion_desde', 'url_ubicacion_desde', 'ubicacion_hasta', 'url_ubicacion_hasta'] as $campoGps) {
            $validated[$campoGps] = trim((string) ($validated[$campoGps] ?? '')) ?: null;
        }

        $activo = TrabajoRed::resolverActivo($validated['activo_tipo'], $validated['activo_id']);
        if (! $activo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'activo_id' => 'El activo no existe.',
            ]);
        }
        $nodoActivo = TrabajoRed::nodoIdDesdeModelo($activo);
        if ($nodoActivo && (int) $validated['nodo_id'] !== $nodoActivo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'activo_id' => 'Ese activo no pertenece al nodo seleccionado.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolverNodoId(array $validated): int
    {
        $activo = TrabajoRed::resolverActivo($validated['activo_tipo'] ?? null, $validated['activo_id'] ?? null);
        $desdeActivo = TrabajoRed::nodoIdDesdeModelo($activo);

        return $desdeActivo ?: (int) $validated['nodo_id'];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function opcionesActivo(string $tipo, int $nodoId): array
    {
        $items = match ($tipo) {
            'nodo' => Nodo::query()->where('nodo_id', $nodoId)->get(),
            'olt' => Olt::query()->where('nodo_id', $nodoId)->orderBy('codigo')->get(),
            'router' => Router::query()->where('nodo_id', $nodoId)->orderBy('nombre')->get(),
            'caja_nap' => CajaNap::query()->where('nodo_id', $nodoId)->orderBy('codigo')->get(),
            'salida_pon' => SalidaPon::query()->where('nodo_id', $nodoId)->orderBy('codigo')->get(),
            'ap' => NodoApWireless::query()->where('nodo_id', $nodoId)->orderBy('nombre')->get(),
            default => collect(),
        };

        $out = [];
        foreach ($items as $item) {
            $out[] = [
                'id' => (int) $item->getKey(),
                'label' => TrabajoRed::etiquetaDeModelo($item, $tipo),
            ];
        }

        return $out;
    }

    private function aplicarBusquedaActivo(\Illuminate\Database\Eloquent\Builder $w, string $term): void
    {
        $w->orWhereExists(function ($q) use ($term) {
            $q->selectRaw('1')->from('olts')
                ->whereColumn('olts.olt_id', 'trabajos_red.activo_id')
                ->where('trabajos_red.activo_tipo', 'olt')
                ->where(function ($x) use ($term) {
                    $x->where('codigo', 'like', $term)->orWhere('ip', 'like', $term);
                });
        })->orWhereExists(function ($q) use ($term) {
            $q->selectRaw('1')->from('routers')
                ->whereColumn('routers.router_id', 'trabajos_red.activo_id')
                ->where('trabajos_red.activo_tipo', 'router')
                ->where(function ($x) use ($term) {
                    $x->where('nombre', 'like', $term)->orWhere('ip', 'like', $term);
                });
        })->orWhereExists(function ($q) use ($term) {
            $q->selectRaw('1')->from('caja_naps')
                ->whereColumn('caja_naps.caja_nap_id', 'trabajos_red.activo_id')
                ->where('trabajos_red.activo_tipo', 'caja_nap')
                ->where(function ($x) use ($term) {
                    $x->where('codigo', 'like', $term)->orWhere('descripcion', 'like', $term);
                });
        })->orWhereExists(function ($q) use ($term) {
            $q->selectRaw('1')->from('salida_pons')
                ->whereColumn('salida_pons.salida_pon_id', 'trabajos_red.activo_id')
                ->where('trabajos_red.activo_tipo', 'salida_pon')
                ->where('codigo', 'like', $term);
        })->orWhereExists(function ($q) use ($term) {
            $q->selectRaw('1')->from('nodo_aps_wireless')
                ->whereColumn('nodo_aps_wireless.ap_id', 'trabajos_red.activo_id')
                ->where('trabajos_red.activo_tipo', 'ap')
                ->where(function ($x) use ($term) {
                    $x->where('nombre', 'like', $term)->orWhere('ip', 'like', $term);
                });
        });
    }
}
