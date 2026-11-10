<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use App\Models\FinCuenta;
use App\Models\Gasto;
use App\Models\Proveedor;
use App\Services\Finanzas\ControlGastoMesService;
use App\Services\Finanzas\PeriodoCerradoException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GastoController extends Controller
{
    /**
     * Consulta base de gastos con los mismos filtros que el listado.
     */
    protected function gastosFiltradosQuery(Request $request): Builder
    {
        $query = Gasto::with(['categoria', 'proveedor', 'pagos.usuario'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $term = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($w) use ($q, $term) {
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                }
                $w->orWhere('descripcion', 'like', $term)
                  ->orWhere('referencia', 'like', $term)
                  ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', $term))
                  ->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'like', $term));
            });
        }

        if ($request->filled('categoria_gasto_id')) {
            $query->where('categoria_gasto_id', $request->categoria_gasto_id);
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('pagado') && $request->pagado !== 'todos') {
            $query->where('pagado', $request->pagado === 'si');
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        return $query;
    }

    /**
     * Listar gastos con métricas ejecutivas, distribución y filtros.
     */
    public function index(Request $request, ControlGastoMesService $cuentas)
    {
        $mesCuenta = $cuentas->mesDesde($request->query('mes'));
        if ($request->routeIs('finanzas.control-gastos') && ! $request->filled('desde') && ! $request->filled('hasta')) {
            $request->merge([
                'desde' => $mesCuenta->toDateString(),
                'hasta' => $mesCuenta->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $query = $this->gastosFiltradosQuery($request);

        $perPagePermitidos = [10, 15, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 15);
        if (! in_array($perPage, $perPagePermitidos, true)) {
            $perPage = 15;
        }

        // Totales calculados sobre la consulta filtrada actual
        $totalFiltrado = (float) (clone $query)->sum('monto');
        $cantidadFiltrada = (int) (clone $query)->count();

        $gastos = $query->paginate($perPage)->withQueryString();
        $categorias = CategoriaGasto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();

        // KPIs financieros
        $gastoHoy = (float) Gasto::whereDate('fecha', now()->toDateString())->sum('monto');
        $gastoSemana = (float) Gasto::whereBetween('fecha', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->sum('monto');
        $gastoMes = (float) Gasto::whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->sum('monto');
        $gastoPendiente = (float) Gasto::where('pagado', false)->sum('monto');
        $countPendientes = (int) Gasto::where('pagado', false)->count();

        // Distribución de gastos del mes en curso por categoría
        $desgloseCategoriasMes = Gasto::query()
            ->whereMonth('fecha', now()->month)
            ->whereYear('fecha', now()->year)
            ->selectRaw('categoria_gasto_id, SUM(monto) as total, COUNT(*) as cantidad')
            ->groupBy('categoria_gasto_id')
            ->with('categoria')
            ->orderByDesc('total')
            ->get();

        $cuentaMes = $cuentas->presentar($mesCuenta);
        $rutaCuenta = $request->routeIs('finanzas.control-gastos') ? 'finanzas.control-gastos' : 'gastos.index';
        $puedeOperarCuenta = $request->user()->tienePermiso('finanzas.crear');
        $cuentasFinanzas = $puedeOperarCuenta
            ? FinCuenta::query()->where('activa', true)->orderBy('orden')->get(['id', 'nombre'])
            : collect();

        return view('gastos.index', compact(
            'gastos',
            'categorias',
            'proveedores',
            'gastoHoy',
            'gastoSemana',
            'gastoMes',
            'gastoPendiente',
            'countPendientes',
            'totalFiltrado',
            'cantidadFiltrada',
            'desgloseCategoriasMes',
            'cuentaMes',
            'rutaCuenta',
            'puedeOperarCuenta',
            'cuentasFinanzas'
        ));
    }

    public function abrirMes(Request $request, ControlGastoMesService $cuentas): RedirectResponse
    {
        return $this->operarMes($request, $cuentas, function (ControlGastoMesService $servicio, Carbon $mes) {
            $servicio->abrir($mes, auth()->id());
        }, 'Mes abierto. Los gastos de ese mes se pueden cargar hasta el cierre.');
    }

    public function cerrarMes(Request $request, ControlGastoMesService $cuentas): RedirectResponse
    {
        $mes = $cuentas->mesDesde($request->input('mes'));
        $volver = redirect()->route('finanzas.control-gastos', ['mes' => $mes->format('Y-m')]);
        $cuentaId = $request->filled('cuenta_id') ? (int) $request->input('cuenta_id') : null;
        try {
            $periodo = $cuentas->cerrar($mes, $cuentaId, auth()->id());
        } catch (InvalidArgumentException|PeriodoCerradoException $e) {
            return $volver->with('error', $e->getMessage());
        }
        $mensaje = (float) $periodo->importe > 0
            ? 'Mes cerrado. El resumen quedó registrado en Gastos e inversiones.'
            : 'Mes cerrado. No había gastos para registrar.';

        return $volver->with('success', $mensaje);
    }

    /**
     * Exportar gastos filtrados a Excel (CSV UTF-8 con separador ; para abrir en Excel).
     */
    public function exportarExcel(Request $request): StreamedResponse
    {
        $gastos = $this->gastosFiltradosQuery($request)->get();

        $filename = 'gastos-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($gastos) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, [
                'ID',
                'Fecha',
                'Categoría',
                'Proveedor',
                'Descripción',
                'Monto (Gs)',
                'Referencia',
                'Estado Pago',
                'Método de Pago',
                'Fecha de Pago',
            ], ';');

            foreach ($gastos as $g) {
                $pago = $g->pagos->first();
                fputcsv($output, [
                    $g->id,
                    $g->fecha?->format('d/m/Y') ?? '',
                    $g->categoria?->nombre ?? 'Sin categoría',
                    $g->proveedor?->nombre ?? 'Sin proveedor',
                    $g->descripcion ?? '',
                    number_format((float) $g->monto, 0, ',', '.'),
                    $g->referencia ?? '',
                    $g->pagado ? 'Pagado' : 'Pendiente',
                    $pago ? ucfirst($pago->metodo_pago) : ($g->pagado ? 'Pagado' : '—'),
                    $pago?->fecha?->format('d/m/Y') ?? '',
                ], ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Formulario crear gasto.
     */
    public function create()
    {
        $categorias = CategoriaGasto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();
        return view('gastos.create', compact('categorias', 'proveedores'));
    }

    /**
     * Guardar nuevo gasto.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'categoria_gasto_id' => ['required', 'integer', 'exists:categorias_gasto,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'descripcion' => ['nullable', 'string'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        $validated['pagado'] = false;

        try {
            app(ControlGastoMesService::class)->asegurarAbierto(Carbon::parse($validated['fecha']));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        Gasto::create($validated);

        return redirect()->route('gastos.index')->with('success', 'Gasto registrado correctamente.');
    }

    /**
     * Formulario editar gasto.
     */
    public function edit(Gasto $gasto)
    {
        $categorias = CategoriaGasto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();
        return view('gastos.edit', compact('gasto', 'categorias', 'proveedores'));
    }

    /**
     * Actualizar gasto.
     */
    public function update(Request $request, Gasto $gasto)
    {
        $validated = $request->validate([
            'categoria_gasto_id' => ['required', 'integer', 'exists:categorias_gasto,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'descripcion' => ['nullable', 'string'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $servicio = app(ControlGastoMesService::class);
            $servicio->asegurarAbierto($gasto->fecha ?? Carbon::parse($validated['fecha']));
            $servicio->asegurarAbierto(Carbon::parse($validated['fecha']));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $gasto->update($validated);

        return redirect()->route('gastos.index')->with('success', 'Gasto actualizado correctamente.');
    }

    /**
     * Formulario para registrar pago de un gasto.
     */
    public function pagar(Gasto $gasto)
    {
        if ($gasto->pagado) {
            return redirect()->route('gastos.index')->with('info', 'Este gasto ya está pagado.');
        }
        try {
            app(ControlGastoMesService::class)->asegurarAbierto($gasto->fecha ?? now());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('gastos.index')->with('error', $e->getMessage());
        }

        return view('gastos.pagar', compact('gasto'));
    }

    /**
     * Eliminar gasto.
     */
    public function destroy(Gasto $gasto)
    {
        try {
            app(ControlGastoMesService::class)->asegurarAbierto($gasto->fecha ?? now());
            $gasto->delete();
            return redirect()->route('gastos.index')->with('success', 'Gasto eliminado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('gastos.index')->with('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se puede eliminar el gasto porque tiene registros asociados.');
        }
    }

    private function operarMes(Request $request, ControlGastoMesService $cuentas, callable $accion, string $ok): RedirectResponse
    {
        $mes = $cuentas->mesDesde($request->input('mes'));
        $volver = redirect()->route('finanzas.control-gastos', ['mes' => $mes->format('Y-m')]);
        try {
            $accion($cuentas, $mes);
        } catch (InvalidArgumentException|PeriodoCerradoException $e) {
            return $volver->with('error', $e->getMessage());
        }

        return $volver->with('success', $ok);
    }
}
