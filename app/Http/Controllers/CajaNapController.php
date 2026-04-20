<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\CajaNapPuertoActivo;
use App\Models\LineaCable;
use App\Models\Nodo;
use App\Models\SalidaPon;
use App\Models\Servicio;
use App\Models\SplitterPrimario;
use App\Models\SplitterSecundario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CajaNapController extends Controller
{
    public function index(Request $request)
    {
        $query = CajaNap::with('nodo')
            ->withCount([
                'puertosActivos as puertos_ocupados_count' => fn ($q) => $q->whereNotNull('servicio_id'),
            ])
            ->orderBy('codigo');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhere('direccion', 'like', "%{$q}%")
                    ->orWhereHas('nodo', fn ($n) => $n->where('descripcion', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', $request->nodo_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $cajas = $query->paginate(15)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('cajas-nap.index', compact('cajas', 'nodos'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $salidas = SalidaPon::with('olt')->orderBy('codigo')->get();
        $apiKey = config('services.google.maps_key', '');

        return view('cajas-nap.create', compact('nodos', 'salidas', 'apiKey'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'salida_pon_id' => ['nullable', 'exists:salida_pons,salida_pon_id'],
            'codigo' => ['required', 'string', 'max:50', 'unique:caja_naps,codigo'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:primaria,secundaria'],
            'splitter_primer_nivel' => ['nullable', 'string', 'max:10'],
            'splitter_segundo_nivel' => ['nullable', 'string', 'in:8,16'],
            'potencia_salida' => ['nullable', 'numeric'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'estado' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['splitter_segundo_nivel'] = isset($validated['splitter_segundo_nivel']) && $validated['splitter_segundo_nivel'] !== ''
            ? (int) $validated['splitter_segundo_nivel']
            : null;

        $validated['salida_pon_id'] = ! empty($validated['salida_pon_id']) ? (int) $validated['salida_pon_id'] : null;
        $validated['potencia_salida'] = isset($validated['potencia_salida']) && $validated['potencia_salida'] !== ''
            ? $validated['potencia_salida'] : null;

        if (! empty($validated['salida_pon_id'])) {
            $sp = SalidaPon::find($validated['salida_pon_id']);
            if ($sp && (int) $sp->nodo_id !== (int) $validated['nodo_id']) {
                return redirect()->back()->withInput()->withErrors([
                    'salida_pon_id' => 'La salida PON debe pertenecer al mismo nodo que la caja.',
                ]);
            }
        }

        $validated['estado'] = $validated['estado'] ?? 'activo';
        $cajaNap = CajaNap::create($validated);
        $cajaNap->refresh();
        $cajaNap->sincronizarPuertosActivos();

        return redirect()->route('sistema.cajas-nap.index')->with('success', 'Caja NAP creada correctamente.');
    }

    public function show(CajaNap $cajaNap)
    {
        $cajaNap->load([
            'nodo',
            'salidaPon.olt',
            'splitterPrimarios.splitterSecundarios',
            'puertosActivos' => fn ($q) => $q->orderBy('numero_puerto'),
            'puertosActivos.servicio.cliente',
        ]);

        return view('cajas-nap.show', compact('cajaNap'));
    }

    /**
     * Servicios de un cliente que aún no están asignados a ningún puerto NAP (JSON para asignar desde la vista).
     */
    public function serviciosPorCliente(Request $request, CajaNap $cajaNap)
    {
        if (! auth()->user()?->tienePermiso('sistema.editar')) {
            abort(403);
        }

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'cliente_id')],
        ]);

        $ocupados = CajaNapPuertoActivo::query()
            ->whereNotNull('servicio_id')
            ->pluck('servicio_id')
            ->all();

        $servicios = Servicio::query()
            ->where('cliente_id', (int) $validated['cliente_id'])
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
            ->with('plan')
            ->orderByDesc('servicio_id')
            ->get()
            ->filter(fn (Servicio $s) => ! in_array((int) $s->servicio_id, array_map('intval', $ocupados), true));

        return response()->json(
            $servicios->values()->map(fn (Servicio $s) => [
                'servicio_id' => (int) $s->servicio_id,
                'estado' => $s->estado,
                'plan' => $s->plan?->nombre,
                'label' => '#'.$s->servicio_id.($s->plan ? ' — '.$s->plan->nombre : ''),
            ])
        );
    }

    public function edit(CajaNap $cajaNap)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $salidas = SalidaPon::with('olt')->orderBy('codigo')->get();
        $apiKey = config('services.google.maps_key', '');

        return view('cajas-nap.edit', compact('cajaNap', 'nodos', 'salidas', 'apiKey'));
    }

    public function update(Request $request, CajaNap $cajaNap)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'salida_pon_id' => ['nullable', 'exists:salida_pons,salida_pon_id'],
            'codigo' => ['required', 'string', 'max:50', 'unique:caja_naps,codigo,'.$cajaNap->caja_nap_id.',caja_nap_id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:primaria,secundaria'],
            'splitter_primer_nivel' => ['nullable', 'string', 'max:10'],
            'splitter_segundo_nivel' => ['nullable', 'string', 'in:8,16'],
            'potencia_salida' => ['nullable', 'numeric'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'estado' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['splitter_segundo_nivel'] = isset($validated['splitter_segundo_nivel']) && $validated['splitter_segundo_nivel'] !== ''
            ? (int) $validated['splitter_segundo_nivel']
            : null;

        $validated['salida_pon_id'] = ! empty($validated['salida_pon_id']) ? (int) $validated['salida_pon_id'] : null;
        $validated['potencia_salida'] = isset($validated['potencia_salida']) && $validated['potencia_salida'] !== ''
            ? $validated['potencia_salida'] : null;

        if (! empty($validated['salida_pon_id'])) {
            $sp = SalidaPon::find($validated['salida_pon_id']);
            if ($sp && (int) $sp->nodo_id !== (int) $validated['nodo_id']) {
                return redirect()->back()->withInput()->withErrors([
                    'salida_pon_id' => 'La salida PON debe pertenecer al mismo nodo que la caja.',
                ]);
            }
        }

        $nuevoSplitter = $validated['splitter_segundo_nivel'] ?? null;
        $viejoSplitter = $cajaNap->splitter_segundo_nivel;

        if ($nuevoSplitter === null && $cajaNap->puertosActivos()->whereNotNull('servicio_id')->exists()) {
            return redirect()->back()->withInput()->withErrors([
                'splitter_segundo_nivel' => 'No se puede quitar el splitter mientras haya servicios asignados a puertos.',
            ]);
        }

        if ($nuevoSplitter !== null && $viejoSplitter !== null && (int) $nuevoSplitter < (int) $viejoSplitter) {
            $ocupadosFuera = $cajaNap->puertosActivos()
                ->where('numero_puerto', '>', (int) $nuevoSplitter)
                ->whereNotNull('servicio_id')
                ->exists();
            if ($ocupadosFuera) {
                return redirect()->back()->withInput()->withErrors([
                    'splitter_segundo_nivel' => 'No se puede reducir el splitter: hay servicios en puertos que quedarían fuera del nuevo límite.',
                ]);
            }
        }

        $cajaNap->update($validated);
        $cajaNap->refresh();
        $cajaNap->sincronizarPuertosActivos();

        return redirect()->route('sistema.cajas-nap.index')->with('success', 'Caja NAP actualizada correctamente.');
    }

    public function asignarPuertoActivo(Request $request, CajaNap $cajaNap, CajaNapPuertoActivo $puertoActivo)
    {
        if ((int) $puertoActivo->caja_nap_id !== (int) $cajaNap->caja_nap_id) {
            abort(404);
        }
        if ($puertoActivo->servicio_id !== null) {
            return redirect()->route('sistema.cajas-nap.show', $cajaNap)
                ->with('error', 'Ese puerto ya está ocupado.');
        }

        $validated = $request->validate([
            'servicio_id' => ['required', 'integer', Rule::exists('servicios', 'servicio_id')],
            'potencia_cliente' => ['nullable', 'numeric'],
        ]);

        $sid = (int) $validated['servicio_id'];
        if (CajaNapPuertoActivo::where('servicio_id', $sid)->exists()) {
            return redirect()->route('sistema.cajas-nap.show', $cajaNap)
                ->with('error', 'Ese servicio ya está asignado a otro puerto de caja NAP.');
        }

        $puertoActivo->update([
            'servicio_id' => $sid,
            'potencia_cliente' => $validated['potencia_cliente'] ?? null,
        ]);

        return redirect()->route('sistema.cajas-nap.show', $cajaNap)
            ->with('success', 'Servicio asignado al puerto.');
    }

    public function liberarPuertoActivo(CajaNap $cajaNap, CajaNapPuertoActivo $puertoActivo)
    {
        if ((int) $puertoActivo->caja_nap_id !== (int) $cajaNap->caja_nap_id) {
            abort(404);
        }

        $puertoActivo->update([
            'servicio_id' => null,
            'potencia_cliente' => null,
        ]);

        return redirect()->route('sistema.cajas-nap.show', $cajaNap)
            ->with('success', 'Puerto liberado.');
    }

    public function destroy(CajaNap $cajaNap)
    {
        $cajaNap->delete();

        return redirect()->route('sistema.cajas-nap.index')->with('success', 'Caja NAP eliminada correctamente.');
    }

    /**
     * Vista del mapa con cajas NAP, nodos, líneas de cable.
     */
    public function mapa(Request $request)
    {
        $apiKey = config('services.google.maps_key', '');
        $nodos = Nodo::orderBy('descripcion')->get();
        $nodoId = $request->get('nodo_id');

        return view('cajas-nap.mapa', compact('apiKey', 'nodos', 'nodoId'));
    }

    /**
     * API JSON para el mapa: cajas, nodos, líneas.
     */
    public function mapaData(Request $request)
    {
        $nodoId = $request->get('nodo_id');

        $fmtCoords = static function (float $lat, float $lon): string {
            return number_format($lat, 6, '.', '').', '.number_format($lon, 6, '.', '');
        };

        $cajas = CajaNap::with([
            'nodo',
            'puertosActivos' => fn ($q) => $q->orderBy('numero_puerto'),
        ])
            ->when($nodoId, fn ($q) => $q->where('nodo_id', $nodoId))
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->get()
            ->map(function (CajaNap $c) use ($fmtCoords) {
                $cap = $c->splitter_segundo_nivel ? (int) $c->splitter_segundo_nivel : 0;
                $porNumero = $c->puertosActivos->keyBy('numero_puerto');
                $puertosFtth = [];
                if (in_array($cap, [8, 16], true)) {
                    for ($n = 1; $n <= $cap; $n++) {
                        $p = $porNumero->get($n);
                        $puertosFtth[] = [
                            'n' => $n,
                            'ocupado' => $p && $p->servicio_id !== null,
                        ];
                    }
                }

                return [
                    'id' => $c->caja_nap_id,
                    'tipo' => 'caja_nap',
                    'codigo' => $c->codigo,
                    'descripcion' => $c->descripcion,
                    'lat' => (float) $c->lat,
                    'lon' => (float) $c->lon,
                    'direccion' => $c->direccion ? trim((string) $c->direccion) : null,
                    'coords_texto' => $fmtCoords((float) $c->lat, (float) $c->lon),
                    'nodo' => $c->nodo?->descripcion,
                    'tipo_caja' => $c->tipo,
                    'splitter_ftth' => in_array($cap, [8, 16], true) ? $cap : null,
                    'puertos_ftth' => $puertosFtth,
                    'url_show' => route('sistema.cajas-nap.show', $c),
                ];
            });

        $nodos = Nodo::whereNotNull('coordenas_gps')
            ->when($nodoId, fn ($q) => $q->where('nodo_id', $nodoId))
            ->get();
        $nodosData = [];
        foreach ($nodos as $n) {
            $coords = $n->getCoordenadasParaMapa();
            if ($coords) {
                $nodosData[] = [
                    'id' => $n->nodo_id,
                    'tipo' => 'nodo',
                    'descripcion' => $n->descripcion,
                    'lat' => $coords['lat'],
                    'lon' => $coords['lon'],
                    'coords_texto' => $fmtCoords((float) $coords['lat'], (float) $coords['lon']),
                ];
            }
        }

        $salidaPons = SalidaPon::with(['nodo', 'olt', 'cajaNaps'])
            ->when($nodoId, fn ($q) => $q->where('nodo_id', $nodoId))
            ->get();
        $ponsData = [];
        foreach ($salidaPons as $p) {
            $coords = $p->getCoordenadasParaMapa();
            if ($coords) {
                $ponsData[] = [
                    'id' => $p->salida_pon_id,
                    'tipo' => 'salida_pon',
                    'codigo' => $p->codigo,
                    'lat' => $coords['lat'],
                    'lon' => $coords['lon'],
                    'coords_texto' => $fmtCoords((float) $coords['lat'], (float) $coords['lon']),
                ];
            }
        }

        $lineas = LineaCable::with('fibraColor')
            ->when($nodoId, function ($q) use ($nodoId) {
                $q->where(function ($q2) use ($nodoId) {
                    $q2->where('origen_tipo', 'nodo')->where('origen_id', $nodoId)
                        ->orWhere('destino_tipo', 'nodo')->where('destino_id', $nodoId);
                });
            })
            ->get();

        $lineasData = [];
        foreach ($lineas as $l) {
            $path = $l->coordenadas;
            if (! $path && $l->origen_model && $l->destino_model) {
                $orig = $this->coordsFromModel($l->origen_model);
                $dest = $this->coordsFromModel($l->destino_model);
                if ($orig && $dest) {
                    $path = [[$orig['lat'], $orig['lon']], [$dest['lat'], $dest['lon']]];
                }
            }
            if ($path) {
                $lineasData[] = [
                    'id' => $l->linea_cable_id,
                    'path' => $path,
                    'color' => $l->fibraColor?->codigo_hex ?? '#666666',
                    'nombre_color' => $l->fibraColor?->nombre ?? 'Sin color',
                ];
            }
        }

        return response()->json([
            'cajas' => $cajas->values()->all(),
            'nodos' => $nodosData,
            'salida_pons' => $ponsData,
            'lineas' => $lineasData,
        ]);
    }

    private function coordsFromModel($model): ?array
    {
        if ($model instanceof Nodo) {
            return $model->getCoordenadasParaMapa();
        }
        if ($model instanceof CajaNap) {
            return $model->lat && $model->lon ? ['lat' => (float) $model->lat, 'lon' => (float) $model->lon] : null;
        }
        if ($model instanceof SalidaPon) {
            return $model->getCoordenadasParaMapa();
        }
        if ($model instanceof SplitterPrimario || $model instanceof SplitterSecundario) {
            $model->loadMissing('cajaNap');
            $caja = $model->cajaNap;

            return $caja && $caja->lat && $caja->lon ? ['lat' => (float) $caja->lat, 'lon' => (float) $caja->lon] : null;
        }

        return null;
    }
}
