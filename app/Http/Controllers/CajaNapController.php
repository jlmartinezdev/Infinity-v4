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

class CajaNapController extends Controller
{
    public function index(Request $request)
    {
        $query = CajaNap::with('nodo')->orderBy('codigo');

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
        return view('cajas-nap.create', compact('nodos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'codigo' => ['required', 'string', 'max:50', 'unique:caja_naps,codigo'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:primaria,secundaria'],
            'estado' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['estado'] = $validated['estado'] ?? 'activo';
        CajaNap::create($validated);

        return redirect()->route('cajas-nap.index')->with('success', 'Caja NAP creada correctamente.');
    }

    public function show(CajaNap $cajaNap)
    {
        $cajaNap->load(['nodo', 'splitterPrimarios.splitterSecundarios', 'salidaPons']);
        return view('cajas-nap.show', compact('cajaNap'));
    }

    public function edit(CajaNap $cajaNap)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        return view('cajas-nap.edit', compact('cajaNap', 'nodos'));
    }

    public function update(Request $request, CajaNap $cajaNap)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'codigo' => ['required', 'string', 'max:50', 'unique:caja_naps,codigo,' . $cajaNap->caja_nap_id . ',caja_nap_id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:primaria,secundaria'],
            'estado' => ['nullable', 'string', 'max:20'],
        ]);

        $cajaNap->update($validated);

        return redirect()->route('cajas-nap.index')->with('success', 'Caja NAP actualizada correctamente.');
    }

    public function destroy(CajaNap $cajaNap)
    {
        $cajaNap->delete();
        return redirect()->route('cajas-nap.index')->with('success', 'Caja NAP eliminada correctamente.');
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

        $cajas = CajaNap::with('nodo')
            ->when($nodoId, fn ($q) => $q->where('nodo_id', $nodoId))
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->caja_nap_id,
                'tipo' => 'caja_nap',
                'codigo' => $c->codigo,
                'descripcion' => $c->descripcion,
                'lat' => (float) $c->lat,
                'lon' => (float) $c->lon,
                'nodo' => $c->nodo?->descripcion,
                'tipo_caja' => $c->tipo,
            ]);

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
                ];
            }
        }

        $salidaPons = SalidaPon::with(['nodo', 'cajaNap'])
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
