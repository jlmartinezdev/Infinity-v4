<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\CategoriaGasto;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GastoController extends Controller
{
    /**
     * Consulta base de gastos con los mismos filtros que el listado.
     */
    protected function gastosFiltradosQuery(Request $request): Builder
    {
        $query = Gasto::with(['categoria', 'proveedor'])->orderBy('fecha', 'desc');

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
     * Listar gastos.
     */
    public function index(Request $request)
    {
        $query = $this->gastosFiltradosQuery($request);

        $gastos = $query->paginate(15)->withQueryString();
        $categorias = CategoriaGasto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();

        $gastoHoy = (float) Gasto::whereDate('fecha', now()->toDateString())->sum('monto');
        $gastoSemana = (float) Gasto::whereBetween('fecha', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->sum('monto');
        $gastoMes = (float) Gasto::whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->sum('monto');

        return view('gastos.index', compact('gastos', 'categorias', 'proveedores', 'gastoHoy', 'gastoSemana', 'gastoMes'));
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
                'Monto',
                'Referencia',
                'Pagado',
            ], ';');

            foreach ($gastos as $g) {
                fputcsv($output, [
                    $g->id,
                    $g->fecha?->format('d/m/Y') ?? '',
                    $g->categoria?->nombre ?? '',
                    $g->proveedor?->nombre ?? '',
                    $g->descripcion ?? '',
                    number_format((float) $g->monto, 2, ',', ''),
                    $g->referencia ?? '',
                    $g->pagado ? 'Sí' : 'No',
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
        return view('gastos.pagar', compact('gasto'));
    }

    /**
     * Eliminar gasto.
     */
    public function destroy(Gasto $gasto)
    {
        try {
            $gasto->delete();
            return redirect()->route('gastos.index')->with('success', 'Gasto eliminado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('gastos.index')->with('error', 'No se puede eliminar el gasto porque tiene registros asociados.');
        }
    }
}
