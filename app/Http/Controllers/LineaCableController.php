<?php

namespace App\Http\Controllers;

use App\Models\CajaFusion;
use App\Models\CajaNap;
use App\Models\FibraColor;
use App\Models\LineaCable;
use App\Models\Nodo;
use App\Models\SalidaPon;
use App\Models\SplitterPrimario;
use App\Models\SplitterSecundario;
use Illuminate\Http\Request;

class LineaCableController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = trim((string) ($request->input('q', $request->input('buscar', ''))));
        $tipoCable = $request->input('tipo_cable');
        $estado = $request->input('estado');
        $capacidad = $request->input('cantidad_hilos');
        $fibraColorId = $request->input('fibra_color_id');
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50, 100], true)) {
            $perPage = 15;
        }

        $query = LineaCable::with('fibraColor')->orderBy('linea_cable_id', 'desc');

        if ($busqueda !== '') {
            $query->where(function ($qry) use ($busqueda) {
                $qry->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('notas', 'like', "%{$busqueda}%")
                    ->orWhere('origen_tipo', 'like', "%{$busqueda}%")
                    ->orWhere('destino_tipo', 'like', "%{$busqueda}%")
                    ->orWhereHas('fibraColor', fn ($fc) => $fc->where('nombre', 'like', "%{$busqueda}%"));
            });
        }

        if ($request->filled('tipo_cable')) {
            $query->where('tipo_cable', $tipoCable);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $estado);
        }

        if ($request->filled('cantidad_hilos')) {
            $query->where('cantidad_hilos', (int) $capacidad);
        }

        if ($request->filled('fibra_color_id')) {
            $query->where('fibra_color_id', (int) $fibraColorId);
        }

        $lineas = $query->paginate($perPage)->withQueryString();
        LineaCable::precargarExtremos($lineas->getCollection());

        // Métricas de Planta Externa / Cables (solo las columnas que hacen falta)
        $todosCables = LineaCable::query()
            ->get(['linea_cable_id', 'tipo_cable', 'cantidad_hilos', 'longitud_metros', 'hilos_detalle']);
        $totalCables = $todosCables->count();
        $totalMetros = (float) $todosCables->sum('longitud_metros');
        $totalPelos = (int) $todosCables->sum(fn ($c) => $c->cantidad_hilos > 0 ? $c->cantidad_hilos : 12);

        $pelosIluminados = 0;
        foreach ($todosCables as $c) {
            $pelosIluminados += $c->pelosIluminadosCount();
        }
        $pelosLibres = max(0, $totalPelos - $pelosIluminados);
        $porcentajeOcupacion = $totalPelos > 0 ? round(($pelosIluminados / $totalPelos) * 100, 1) : 0;

        $cablesTroncales = $todosCables->where('tipo_cable', 'troncal')->count();
        $cablesDistribucion = $todosCables->where('tipo_cable', 'distribucion')->count();
        $cablesDrop = $todosCables->where('tipo_cable', 'drop')->count();

        $kpis = [
            'total_cables'        => $totalCables,
            'total_metros'        => $totalMetros,
            'total_km'            => round($totalMetros / 1000, 2),
            'total_pelos'         => $totalPelos,
            'pelos_iluminados'    => $pelosIluminados,
            'pelos_libres'        => $pelosLibres,
            'porcentaje_ocupacion'=> $porcentajeOcupacion,
            'cables_troncales'    => $cablesTroncales,
            'cables_distribucion' => $cablesDistribucion,
            'cables_drop'         => $cablesDrop,
        ];

        $cajasFusion = CajaFusion::orderBy('codigo')->get();
        $codigoCajaSugerido = CajaFusion::siguienteCodigo();
        $cablesEnFusion = LineaCable::query()
            ->where(function ($q) {
                $q->where('origen_tipo', 'caja_fusion')->orWhere('destino_tipo', 'caja_fusion');
            })
            ->orderBy('nombre')
            ->get(['linea_cable_id', 'nombre', 'cantidad_hilos', 'origen_tipo', 'origen_id', 'destino_tipo', 'destino_id']);

        $fibraColores = FibraColor::activos()->orderBy('nombre')->get();
        $coloresTIA = LineaCable::COLORES_TIA598;
        $tiposCable = LineaCable::TIPOS_CABLE;
        $estados = LineaCable::ESTADOS;
        $capacidades = LineaCable::CAPACIDADES_ESTANDAR;

        return view('lineas-cable.index', compact(
            'lineas',
            'kpis',
            'fibraColores',
            'coloresTIA',
            'tiposCable',
            'estados',
            'capacidades',
            'busqueda',
            'perPage',
            'cajasFusion',
            'cablesEnFusion',
            'codigoCajaSugerido'
        ));
    }

    public function create(Request $request)
    {
        $fibraColores = FibraColor::activos()->orderBy('nombre')->get();
        $nodos = Nodo::orderBy('descripcion')->get();
        $cajas = CajaNap::with('nodo')->orderBy('codigo')->get();
        $salidaPons = SalidaPon::with(['nodo', 'olt'])->orderBy('codigo')->get();
        $splittersPrimarios = SplitterPrimario::with('cajaNap')->orderBy('codigo')->get();
        $splittersSecundarios = SplitterSecundario::with(['cajaNap', 'splitterPrimario'])->orderBy('codigo')->get();

        $coloresTIA = LineaCable::COLORES_TIA598;
        $tiposCable = LineaCable::TIPOS_CABLE;
        $estados = LineaCable::ESTADOS;
        $capacidades = LineaCable::CAPACIDADES_ESTANDAR;
        $cables = LineaCable::orderBy('nombre')->orderBy('linea_cable_id')->get(['linea_cable_id', 'nombre', 'cantidad_hilos']);
        $cajasFusion = CajaFusion::orderBy('codigo')->get();

        return view('lineas-cable.create', compact(
            'fibraColores',
            'nodos',
            'cajas',
            'salidaPons',
            'splittersPrimarios',
            'splittersSecundarios',
            'coloresTIA',
            'tiposCable',
            'estados',
            'capacidades',
            'cables',
            'cajasFusion'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validarCable($request);

        $cable = LineaCable::create($validated);

        return redirect()->route('sistema.lineas-cable.index')->with('success', "Cable '{$cable->nombre}' registrado correctamente.");
    }

    public function show(LineaCable $lineas_cable)
    {
        return redirect()->route('sistema.lineas-cable.index');
    }

    public function edit(LineaCable $lineas_cable)
    {
        $lineaCable = $lineas_cable;
        $lineaCable->load('fibraColor');

        $fibraColores = FibraColor::activos()->orderBy('nombre')->get();
        $nodos = Nodo::orderBy('descripcion')->get();
        $cajas = CajaNap::with('nodo')->orderBy('codigo')->get();
        $salidaPons = SalidaPon::with(['nodo', 'olt'])->orderBy('codigo')->get();
        $splittersPrimarios = SplitterPrimario::with('cajaNap')->orderBy('codigo')->get();
        $splittersSecundarios = SplitterSecundario::with(['cajaNap', 'splitterPrimario'])->orderBy('codigo')->get();

        $coloresTIA = LineaCable::COLORES_TIA598;
        $tiposCable = LineaCable::TIPOS_CABLE;
        $estados = LineaCable::ESTADOS;
        $capacidades = LineaCable::CAPACIDADES_ESTANDAR;
        $cables = LineaCable::query()
            ->where('linea_cable_id', '!=', $lineaCable->linea_cable_id)
            ->orderBy('nombre')
            ->orderBy('linea_cable_id')
            ->get(['linea_cable_id', 'nombre', 'cantidad_hilos']);
        $cajasFusion = CajaFusion::orderBy('codigo')->get();

        return view('lineas-cable.edit', compact(
            'lineaCable',
            'fibraColores',
            'nodos',
            'cajas',
            'salidaPons',
            'splittersPrimarios',
            'splittersSecundarios',
            'coloresTIA',
            'tiposCable',
            'estados',
            'capacidades',
            'cables',
            'cajasFusion'
        ));
    }

    public function update(Request $request, LineaCable $lineas_cable)
    {
        $lineaCable = $lineas_cable;

        $validated = $this->validarCable($request, (int) $lineaCable->linea_cable_id, $lineaCable->coordenadas);

        $lineaCable->update($validated);

        return redirect()->route('sistema.lineas-cable.index')->with('success', "Cable '{$lineaCable->nombre}' actualizado correctamente.");
    }

    /**
     * Actualiza el estado y destino de los pelos/hilos individuales del cable.
     */
    public function guardarPelos(Request $request, LineaCable $lineas_cable)
    {
        $request->validate([
            'cantidad_hilos'          => ['nullable', 'integer', 'min:1', 'max:288'],
            'pelos'                   => ['nullable', 'array'],
            'pelos.*.numero'          => ['required', 'integer', 'min:1'],
            'pelos.*.estado'          => ['nullable', 'in:'.implode(',', LineaCable::ESTADOS_PELO)],
            'pelos.*.color_pos'       => ['nullable', 'integer', 'min:1', 'max:12'],
            'pelos.*.uso'             => ['nullable', 'string', 'max:255'],
            'pelos.*.destino_detalle' => ['nullable', 'string', 'max:255'],
            'pelos.*.atenuacion_db'   => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($request->filled('cantidad_hilos')) {
            $lineas_cable->cantidad_hilos = (int) $request->input('cantidad_hilos');
        }

        $pelosInput = $request->input('pelos', []);
        $total = $lineas_cable->cantidad_hilos > 0 ? (int) $lineas_cable->cantidad_hilos : 12;

        // Se parte de lo guardado: un campo que el formulario no manda se conserva
        // (antes se perdían destino_detalle y atenuacion_db en cada guardado).
        $detalle = [];
        foreach ($lineas_cable->pelos as $actual) {
            $detalle[$actual['numero']] = [
                'numero'          => $actual['numero'],
                'estado'          => $actual['estado'],
                'color_pos'       => $actual['color_pos'],
                'uso'             => $actual['uso'],
                'destino_detalle' => $actual['destino_detalle'],
                'atenuacion_db'   => $actual['atenuacion_db'],
            ];
        }

        $detalle = array_filter($detalle, fn ($num) => $num >= 1 && $num <= $total, ARRAY_FILTER_USE_KEY);

        foreach ($pelosInput as $p) {
            $num = (int) ($p['numero'] ?? 0);
            if ($num < 1 || $num > $total) {
                continue;
            }
            if (! isset($detalle[$num])) {
                $detalle[$num] = [
                    'numero' => $num,
                    'estado' => 'libre',
                    'color_pos' => (($num - 1) % 12) + 1,
                    'uso' => '',
                    'destino_detalle' => '',
                    'atenuacion_db' => null,
                ];
            }
            if (array_key_exists('estado', $p)) {
                $detalle[$num]['estado'] = in_array($p['estado'], LineaCable::ESTADOS_PELO, true) ? $p['estado'] : 'libre';
            }
            if (array_key_exists('color_pos', $p) && $p['color_pos'] !== '' && $p['color_pos'] !== null) {
                $colorPos = (int) $p['color_pos'];
                if (isset(LineaCable::COLORES_TIA598[$colorPos])) {
                    $detalle[$num]['color_pos'] = $colorPos;
                }
            }
            foreach (['uso', 'destino_detalle'] as $campo) {
                if (array_key_exists($campo, $p)) {
                    $detalle[$num][$campo] = trim((string) $p[$campo]);
                }
            }
            if (array_key_exists('atenuacion_db', $p)) {
                $detalle[$num]['atenuacion_db'] = $p['atenuacion_db'] !== '' && $p['atenuacion_db'] !== null ? (float) $p['atenuacion_db'] : null;
            }
        }

        ksort($detalle);
        $lineas_cable->hilos_detalle = array_values($detalle);
        $lineas_cable->save();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Hilos de fibra óptica actualizados correctamente.',
                'pelos'   => $lineas_cable->fresh()->pelos,
            ]);
        }

        return redirect()->back()->with('success', 'Matriz de pelos de fibra actualizada correctamente.');
    }

    /**
     * Reglas comunes de alta y edición. Comprueba que el origen y el destino existan
     * en la tabla que corresponde a su tipo y arma el nombre si viene vacío.
     */
    public function storeCajaFusion(Request $request)
    {
        foreach (['lat', 'lon'] as $campo) {
            $valor = $request->input($campo);
            $request->merge([$campo => ($valor === '' || $valor === null) ? null : $valor]);
        }

        $validated = $request->validate([
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'lat'       => ['nullable', 'numeric', 'between:-90,90', 'required_with:lon'],
            'lon'       => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'notas'     => ['nullable', 'string', 'max:2000'],
        ], [], [
            'lat' => 'latitud',
            'lon' => 'longitud',
        ]);

        $caja = CajaFusion::create([
            'codigo'    => CajaFusion::siguienteCodigo(),
            'ubicacion' => filled($validated['ubicacion'] ?? null) ? trim($validated['ubicacion']) : null,
            'lat'       => isset($validated['lat']) ? round((float) $validated['lat'], 6) : null,
            'lon'       => isset($validated['lon']) ? round((float) $validated['lon'], 6) : null,
            'notas'     => filled($validated['notas'] ?? null) ? trim($validated['notas']) : null,
        ]);

        return redirect()
            ->route('sistema.lineas-cable.index')
            ->with('success', "Caja de fusión '{$caja->codigo}' agregada. Ya se puede usar para unir cables.");
    }

    /**
     * Reglas comunes de alta y edición. Comprueba que el origen y el destino existan
     * en la tabla que corresponde a su tipo y arma el nombre si viene vacío.
     */
    protected function validarCable(Request $request, ?int $exceptoId = null, ?array $coordsActuales = null): array
    {
        $this->normalizarPuntosGps($request);

        $tipos = implode(',', array_keys(LineaCable::EXTREMOS));

        $existeExtremo = function (string $prefix) use ($request) {
            return function (string $attribute, $value, \Closure $fail) use ($prefix, $request) {
                $class = LineaCable::EXTREMOS[$request->input("{$prefix}_tipo")] ?? null;
                if ($class && ! $class::whereKey((int) $value)->exists()) {
                    $fail('El '.$prefix.' elegido no existe.');
                }
            };
        };

        $validated = $request->validate([
            'nombre'          => ['nullable', 'string', 'max:100'],
            'tipo_cable'      => ['required', 'in:'.implode(',', array_keys(LineaCable::TIPOS_CABLE))],
            'cantidad_hilos'  => ['required', 'integer', 'min:1', 'max:288'],
            'estado'          => ['required', 'in:'.implode(',', array_keys(LineaCable::ESTADOS))],
            'fibra_color_id'  => ['required', 'exists:fibra_colores,fibra_color_id'],
            'origen_tipo'     => ['required', 'in:'.$tipos],
            'origen_id'       => ['required', 'integer', 'min:1', $existeExtremo('origen')],
            'destino_tipo'    => ['required', 'in:'.$tipos],
            'destino_id'      => ['required', 'integer', 'min:1', $existeExtremo('destino')],
            'longitud_metros' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'inicio_lat'      => ['nullable', 'numeric', 'between:-90,90', 'required_with:inicio_lon,fin_lat,fin_lon'],
            'inicio_lon'      => ['nullable', 'numeric', 'between:-180,180', 'required_with:inicio_lat,fin_lat,fin_lon'],
            'fin_lat'         => ['nullable', 'numeric', 'between:-90,90', 'required_with:fin_lon,inicio_lat,inicio_lon'],
            'fin_lon'         => ['nullable', 'numeric', 'between:-180,180', 'required_with:fin_lat,inicio_lat,inicio_lon'],
            'coordenadas'     => ['nullable', 'array', 'max:200'],
            'coordenadas.*'   => ['array', 'size:2'],
            'coordenadas.*.0' => ['required', 'numeric', 'between:-90,90'],
            'coordenadas.*.1' => ['required', 'numeric', 'between:-180,180'],
            'notas'           => ['nullable', 'string', 'max:2000'],
        ], [], [
            'origen_id'  => 'origen',
            'destino_id' => 'destino',
            'inicio_lat' => 'latitud de inicio',
            'inicio_lon' => 'longitud de inicio',
            'fin_lat'    => 'latitud de fin',
            'fin_lon'    => 'longitud de fin',
        ]);

        if ($validated['origen_tipo'] === $validated['destino_tipo'] && (int) $validated['origen_id'] === (int) $validated['destino_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'destino_id' => 'El destino no puede ser el mismo punto que el origen.',
            ]);
        }

        if ($exceptoId) {
            foreach (['origen', 'destino'] as $lado) {
                if ($validated[$lado.'_tipo'] === 'linea_cable' && (int) $validated[$lado.'_id'] === $exceptoId) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $lado.'_id' => 'Un cable no puede unirse a sí mismo.',
                    ]);
                }
            }
        }

        if (empty($validated['nombre'])) {
            $tipoLabel = strtoupper(substr($validated['tipo_cable'], 0, 4));
            $validated['nombre'] = $tipoLabel.'-'.$validated['cantidad_hilos'].'FO-'.date('ymdHis');
        }

        $inicio = $this->parGps($validated['inicio_lat'] ?? null, $validated['inicio_lon'] ?? null);
        $fin = $this->parGps($validated['fin_lat'] ?? null, $validated['fin_lon'] ?? null);
        unset($validated['inicio_lat'], $validated['inicio_lon'], $validated['fin_lat'], $validated['fin_lon']);

        $ruta = $this->rutaPublicada($validated['coordenadas'] ?? null);

        if ($inicio && $fin && $ruta) {
            $ruta[0] = $inicio;
            $ruta[count($ruta) - 1] = $fin;
            $validated['coordenadas'] = $ruta;
        } elseif ($inicio && $fin) {
            $path = is_array($coordsActuales) ? array_values($coordsActuales) : [];
            if (count($path) > 2) {
                $path[0] = $inicio;
                $path[count($path) - 1] = $fin;
                $validated['coordenadas'] = array_map(fn ($c) => [(float) $c[0], (float) $c[1]], $path);
            } else {
                $validated['coordenadas'] = [$inicio, $fin];
            }
        } elseif ($request->exists('inicio_lat') || $request->exists('fin_lat')) {
            $validated['coordenadas'] = null;
        } else {
            $coords = $validated['coordenadas'] ?? null;
            $validated['coordenadas'] = is_array($coords) && count($coords) > 0
                ? array_values(array_map(fn ($c) => [(float) $c[0], (float) $c[1]], $coords))
                : null;
        }

        return $validated;
    }

    /**
     * Deja la latitud y la longitud como número, o null si el campo viene vacío.
     * Si en la latitud se pegan los dos números, los separa.
     */
    private function normalizarPuntosGps(Request $request): void
    {
        foreach (['inicio', 'fin'] as $lado) {
            $latKey = $lado.'_lat';
            $lonKey = $lado.'_lon';
            $lat = $this->textoCoord($request->input($latKey));
            $lon = $this->textoCoord($request->input($lonKey));

            if ($lat !== null && $lon === null && preg_match('/^(-?\d+(?:\.\d+)?)[\s,;]+(-?\d+(?:\.\d+)?)$/', $lat, $m)) {
                $lat = $m[1];
                $lon = $m[2];
            }

            $request->merge([$latKey => $lat, $lonKey => $lon]);
        }
    }

    private function textoCoord(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $esPar = str_contains($texto, ';')
            || preg_match('/\s/', $texto)
            || substr_count($texto, ',') >= 2
            || substr_count($texto, '.') >= 2;
        if ($esPar && preg_match('/^(-?\d+(?:[.,]\d+)?)(?:\s*[;,]\s*|\s+)(-?\d+(?:[.,]\d+)?)$/', $texto, $m)) {
            return str_replace(',', '.', $m[1]).' '.str_replace(',', '.', $m[2]);
        }

        return str_replace(',', '.', $texto);
    }

    /**
     * Recorrido que calculó el mapa (la calle entre el inicio y el fin).
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function rutaPublicada(?array $coords): ?array
    {
        if (! is_array($coords) || count($coords) < 2) {
            return null;
        }

        $out = [];
        foreach ($coords as $punto) {
            if (! is_array($punto) || count($punto) < 2 || ! is_numeric($punto[0]) || ! is_numeric($punto[1])) {
                return null;
            }
            $lat = round((float) $punto[0], 6);
            $lon = round((float) $punto[1], 6);
            if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                return null;
            }
            $ultimo = $out[count($out) - 1] ?? null;
            if ($ultimo && $ultimo[0] === $lat && $ultimo[1] === $lon) {
                continue;
            }
            $out[] = [$lat, $lon];
        }

        return count($out) >= 2 ? $out : null;
    }

    /** @return array{0: float, 1: float}|null */
    private function parGps(mixed $lat, mixed $lon): ?array
    {
        if ($lat === null || $lat === '' || $lon === null || $lon === '') {
            return null;
        }

        return [round((float) $lat, 6), round((float) $lon, 6)];
    }

    public function destroy(LineaCable $lineas_cable)
    {
        $nombre = $lineas_cable->nombre ?: '#'.$lineas_cable->linea_cable_id;
        $lineas_cable->delete();

        return redirect()->route('sistema.lineas-cable.index')->with('success', "Cable '{$nombre}' eliminado correctamente.");
    }
}
