<?php

namespace App\Http\Controllers;

use App\Models\CajaNap;
use App\Models\CajaNapPuertoActivo;
use App\Models\LineaCable;
use App\Models\Nodo;
use App\Models\Olt;
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
        $busqueda = trim((string) ($request->input('q', $request->input('buscar', ''))));
        $nodoId = $request->input('nodo_id');
        $salidaPonId = $request->input('salida_pon_id');
        $tipo = $request->input('tipo');
        $estado = $request->input('estado');
        $splitterSegundo = $request->input('splitter_segundo_nivel');
        $filtroGps = $request->input('filtro_gps');
        $filtroOcupacion = $request->input('filtro_ocupacion');
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $query = CajaNap::with(['nodo', 'salidaPon.olt', 'salidaPon.oltPuerto'])
            ->withCount([
                'puertosActivos as puertos_ocupados_count' => fn ($q) => $q->whereNotNull('servicio_id'),
                'puertosActivos as puertos_total_count',
            ])
            ->orderBy('codigo');

        if ($busqueda !== '') {
            $query->where(function ($qry) use ($busqueda) {
                $qry->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('direccion', 'like', "%{$busqueda}%")
                    ->orWhere('nota', 'like', "%{$busqueda}%")
                    ->orWhereHas('nodo', fn ($n) => $n->where('descripcion', 'like', "%{$busqueda}%"))
                    ->orWhereHas('salidaPon', function ($sp) use ($busqueda) {
                        $sp->where('codigo', 'like', "%{$busqueda}%")
                            ->orWhereHas('olt', fn ($o) => $o->where('codigo', 'like', "%{$busqueda}%")->orWhere('ip', 'like', "%{$busqueda}%"));
                    });
            });
        }

        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', (int) $nodoId);
        }

        if ($request->filled('salida_pon_id')) {
            $query->where('salida_pon_id', (int) $salidaPonId);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $estado);
        }

        if ($request->filled('splitter_segundo_nivel')) {
            $query->where('splitter_segundo_nivel', (int) $splitterSegundo);
        }

        if ($filtroGps === 'con_gps') {
            $query->whereNotNull('lat')->whereNotNull('lon');
        } elseif ($filtroGps === 'sin_gps') {
            $query->where(function ($qry) {
                $qry->whereNull('lat')->orWhereNull('lon');
            });
        }

        if ($filtroOcupacion === 'con_libres') {
            $query->whereRaw('COALESCE(splitter_segundo_nivel, 0) > (SELECT COUNT(*) FROM caja_nap_puerto_activos WHERE caja_nap_puerto_activos.caja_nap_id = caja_naps.caja_nap_id AND caja_nap_puerto_activos.servicio_id IS NOT NULL)');
        } elseif ($filtroOcupacion === 'saturadas') {
            $query->whereRaw('COALESCE(splitter_segundo_nivel, 0) > 0 AND COALESCE(splitter_segundo_nivel, 0) <= (SELECT COUNT(*) FROM caja_nap_puerto_activos WHERE caja_nap_puerto_activos.caja_nap_id = caja_naps.caja_nap_id AND caja_nap_puerto_activos.servicio_id IS NOT NULL)');
        } elseif ($filtroOcupacion === 'vacias') {
            $query->whereRaw('(SELECT COUNT(*) FROM caja_nap_puerto_activos WHERE caja_nap_puerto_activos.caja_nap_id = caja_naps.caja_nap_id AND caja_nap_puerto_activos.servicio_id IS NOT NULL) = 0');
        }

        $cajas = $query->paginate($perPage)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();
        $salidas = SalidaPon::with(['nodo', 'olt'])->orderBy('codigo')->get();

        // Métricas FTTH / NOC
        $totalCajas = CajaNap::count();
        $activasCajas = CajaNap::where('estado', 'activo')->count();
        $totalPuertosCapacidad = (int) CajaNap::sum('splitter_segundo_nivel');
        $puertosOcupados = CajaNapPuertoActivo::whereNotNull('servicio_id')->count();
        $puertosLibres = max(0, $totalPuertosCapacidad - $puertosOcupados);
        $porcentajeOcupacion = $totalPuertosCapacidad > 0
            ? round(($puertosOcupados / $totalPuertosCapacidad) * 100, 1)
            : 0;
        $conGps = CajaNap::whereNotNull('lat')->whereNotNull('lon')->count();
        $avgPotencia = CajaNap::whereNotNull('potencia_salida')->avg('potencia_salida');
        $conPotencia = CajaNap::whereNotNull('potencia_salida')->count();

        $kpis = [
            'total' => $totalCajas,
            'activas' => $activasCajas,
            'capacidad_total' => $totalPuertosCapacidad,
            'puertos_ocupados' => $puertosOcupados,
            'puertos_libres' => $puertosLibres,
            'porcentaje_ocupacion' => $porcentajeOcupacion,
            'con_gps' => $conGps,
            'potencia_promedio' => $avgPotencia !== null ? round((float) $avgPotencia, 1) : null,
            'con_potencia' => $conPotencia,
        ];

        // Trabajos de red abiertos sobre activos tipo caja_nap
        $trabajosAbiertosPorCaja = [];
        if (class_exists(\App\Models\TrabajoRed::class)) {
            $trabajosAbiertosPorCaja = \App\Models\TrabajoRed::query()
                ->where('activo_tipo', 'caja_nap')
                ->whereIn('estado', \App\Models\TrabajoRed::estadosAbiertos())
                ->selectRaw('activo_id, COUNT(*) as total')
                ->groupBy('activo_id')
                ->pluck('total', 'activo_id')
                ->all();
        }

        return view('cajas-nap.index', compact(
            'cajas',
            'nodos',
            'salidas',
            'kpis',
            'busqueda',
            'perPage',
            'trabajosAbiertosPorCaja'
        ));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $salidas = SalidaPon::with(['olt', 'nodo', 'oltPuerto'])->orderBy('codigo')->get();
        $apiKey = config('services.google.maps_key', '');
        $codigoSugerido = CajaNap::siguienteCodigo();

        return view('cajas-nap.create', compact('nodos', 'salidas', 'apiKey', 'codigoSugerido'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'salida_pon_id' => ['nullable', 'exists:salida_pons,salida_pon_id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'splitter_primer_nivel' => ['nullable', 'string', 'max:10', Rule::in(CajaNap::SPLITTERS_PRIMER_NIVEL)],
            'splitter_segundo_nivel' => ['nullable', 'string', 'in:8,16'],
            'potencia_salida' => ['nullable', 'numeric'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'estado' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['splitter_segundo_nivel'] = isset($validated['splitter_segundo_nivel']) && $validated['splitter_segundo_nivel'] !== ''
            ? (int) $validated['splitter_segundo_nivel']
            : null;

        $validated['splitter_primer_nivel'] = ($validated['splitter_primer_nivel'] ?? '') !== ''
            ? $validated['splitter_primer_nivel']
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
        $validated['tipo'] = 'secundaria';
        $validated['codigo'] = CajaNap::siguienteCodigo();
        $cajaNap = CajaNap::create($validated);
        $cajaNap->refresh();
        $cajaNap->sincronizarPuertosActivos();

        $returnOltId = (int) $request->input('return_olt');
        if ($returnOltId > 0 && Olt::query()->where('olt_id', $returnOltId)->exists()) {
            return redirect()
                ->route('sistema.olts.show', $returnOltId)
                ->with('success', 'Caja NAP creada correctamente.');
        }

        return redirect()->route('sistema.cajas-nap.index')->with('success', 'Caja NAP creada correctamente.');
    }

    public function show(CajaNap $cajaNap)
    {
        $cajaNap->load([
            'nodo',
            'salidaPon.olt',
            'salidaPon.oltPuerto',
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
                'label' => '#'.$s->servicio_id.' — '.$s->etiqueta(),
            ])
        );
    }

    public function edit(CajaNap $cajaNap)
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $salidas = SalidaPon::with(['olt', 'nodo', 'oltPuerto'])->orderBy('codigo')->get();
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
            'splitter_primer_nivel' => ['nullable', 'string', 'max:10', Rule::in(CajaNap::splittersPrimerNivelPermitidos($cajaNap->splitter_primer_nivel))],
            'splitter_segundo_nivel' => ['nullable', 'string', 'in:8,16'],
            'potencia_salida' => ['nullable', 'numeric'],
            'nota' => ['nullable', 'string', 'max:2000'],
            'estado' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['splitter_segundo_nivel'] = isset($validated['splitter_segundo_nivel']) && $validated['splitter_segundo_nivel'] !== ''
            ? (int) $validated['splitter_segundo_nivel']
            : null;

        $validated['splitter_primer_nivel'] = ($validated['splitter_primer_nivel'] ?? '') !== ''
            ? $validated['splitter_primer_nivel']
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
