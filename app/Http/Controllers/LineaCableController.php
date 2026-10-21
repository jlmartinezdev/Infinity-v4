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

        // Métricas de Planta Externa / Cables
        $todosCables = LineaCable::all();
        $totalCables = $todosCables->count();
        $totalMetros = (float) $todosCables->sum('longitud_metros');
        $totalPelos = (int) $todosCables->sum('cantidad_hilos');

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
            'perPage'
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
            'capacidades'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'          => ['nullable', 'string', 'max:100'],
            'tipo_cable'      => ['required', 'in:troncal,distribucion,drop'],
            'cantidad_hilos'  => ['required', 'integer', 'min:1', 'max:288'],
            'estado'          => ['required', 'in:activo,proyectado,mantenimiento,danado'],
            'fibra_color_id'  => ['required', 'exists:fibra_colores,fibra_color_id'],
            'origen_tipo'     => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'origen_id'       => ['required', 'integer', 'min:1'],
            'destino_tipo'    => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'destino_id'      => ['required', 'integer', 'min:1'],
            'longitud_metros' => ['nullable', 'numeric', 'min:0'],
            'coordenadas'     => ['nullable', 'array'],
            'coordenadas.*'   => ['array', 'size:2'],
            'notas'           => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($validated['nombre'])) {
            $tipoLabel = strtoupper(substr($validated['tipo_cable'], 0, 4));
            $validated['nombre'] = $tipoLabel . '-' . $validated['cantidad_hilos'] . 'FO-' . date('ymdHis');
        }

        $coords = $request->coordenadas;
        if (is_array($coords)) {
            $validated['coordenadas'] = array_values(array_map(fn ($c) => [(float) ($c[0] ?? 0), (float) ($c[1] ?? 0)], $coords));
        } else {
            $validated['coordenadas'] = null;
        }

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
            'capacidades'
        ));
    }

    public function update(Request $request, LineaCable $lineas_cable)
    {
        $lineaCable = $lineas_cable;

        $validated = $request->validate([
            'nombre'          => ['nullable', 'string', 'max:100'],
            'tipo_cable'      => ['required', 'in:troncal,distribucion,drop'],
            'cantidad_hilos'  => ['required', 'integer', 'min:1', 'max:288'],
            'estado'          => ['required', 'in:activo,proyectado,mantenimiento,danado'],
            'fibra_color_id'  => ['required', 'exists:fibra_colores,fibra_color_id'],
            'origen_tipo'     => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'origen_id'       => ['required', 'integer', 'min:1'],
            'destino_tipo'    => ['required', 'in:nodo,caja_nap,splitter_primario,splitter_secundario,salida_pon'],
            'destino_id'      => ['required', 'integer', 'min:1'],
            'longitud_metros' => ['nullable', 'numeric', 'min:0'],
            'coordenadas'     => ['nullable', 'array'],
            'coordenadas.*'   => ['array', 'size:2'],
            'notas'           => ['nullable', 'string', 'max:2000'],
        ]);

        $coords = $request->coordenadas;
        if (is_array($coords)) {
            $validated['coordenadas'] = array_values(array_map(fn ($c) => [(float) ($c[0] ?? 0), (float) ($c[1] ?? 0)], $coords));
        } else {
            $validated['coordenadas'] = null;
        }

        $lineaCable->update($validated);

        return redirect()->route('sistema.lineas-cable.index')->with('success', "Cable '{$lineaCable->nombre}' actualizado correctamente.");
    }

    /**
     * Actualiza el estado y destino de los pelos/hilos individuales del cable.
     */
    public function guardarPelos(Request $request, LineaCable $lineas_cable)
    {
        $pelosInput = $request->input('pelos', []);
        if (! is_array($pelosInput)) {
            $pelosInput = [];
        }

        $detalleActualizado = [];
        foreach ($pelosInput as $p) {
            $num = (int) ($p['numero'] ?? 0);
            if ($num > 0) {
                $detalleActualizado[] = [
                    'numero'          => $num,
                    'estado'          => in_array($p['estado'] ?? '', ['iluminado', 'libre', 'reservado', 'danado']) ? $p['estado'] : 'libre',
                    'uso'             => trim((string) ($p['uso'] ?? '')),
                    'destino_detalle' => trim((string) ($p['destino_detalle'] ?? '')),
                    'atenuacion_db'   => isset($p['atenuacion_db']) && $p['atenuacion_db'] !== '' ? (float) $p['atenuacion_db'] : null,
                ];
            }
        }

        $lineas_cable->update([
            'hilos_detalle' => $detalleActualizado,
        ]);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Hilos de fibra óptica actualizados correctamente.',
                'pelos'   => $lineas_cable->fresh()->pelos,
            ]);
        }

        return redirect()->back()->with('success', 'Matriz de pelos de fibra actualizada correctamente.');
    }

    public function destroy(LineaCable $lineas_cable)
    {
        $nombre = $lineas_cable->nombre ?: '#'.$lineas_cable->linea_cable_id;
        $lineas_cable->delete();

        return redirect()->route('sistema.lineas-cable.index')->with('success', "Cable '{$nombre}' eliminado correctamente.");
    }
}
