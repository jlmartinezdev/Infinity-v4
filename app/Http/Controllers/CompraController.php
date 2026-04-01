<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    /**
     * Listar compras.
     */
    public function index(Request $request)
    {
        $query = Compra::with(['proveedor', 'detalles.producto'])->orderBy('fecha', 'desc');

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        $compras = $query->paginate(15)->withQueryString();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();

        return view('compras.index', compact('compras', 'proveedores'));
    }

    /**
     * Formulario crear compra.
     */
    public function create()
    {
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();
        $productos = Producto::with('categoria')->where('estado', 'activo')->orderBy('nombre')->get();
        return view('compras.create', compact('proveedores', 'productos'));
    }

    /**
     * Guardar nueva compra.
     */
    public function store(Request $request, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'numero_factura' => ['nullable', 'string', 'max:100'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'impuesto' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $detalles = $validated['detalles'];
        $subtotal = 0;

        foreach ($detalles as $d) {
            $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
            $subtotal += $lineSubtotal;
        }

        $descuento = (float) ($validated['descuento'] ?? 0);
        $impuesto = (float) ($validated['impuesto'] ?? 0);
        $total = $subtotal - $descuento + $impuesto;

        DB::transaction(function () use ($validated, $detalles, $subtotal, $descuento, $impuesto, $total, $inventarioService) {
            $compra = Compra::create([
                'proveedor_id' => $validated['proveedor_id'],
                'fecha' => $validated['fecha'],
                'numero_factura' => $validated['numero_factura'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuesto' => $impuesto,
                'total' => $total,
                'pagado' => 0,
                'estado' => 'pendiente',
                'notas' => $validated['notas'] ?? null,
            ]);

            foreach ($detalles as $d) {
                $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $d['producto_id'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            $compra->load('detalles');
            $inventarioService->registrarEntradaCompra($compra);
        });

        return redirect()->route('compras.index')->with('success', 'Compra registrada correctamente.');
    }

    /**
     * Ver detalle de compra.
     */
    public function show(Compra $compra)
    {
        $compra->load(['proveedor', 'detalles.producto']);
        return view('compras.show', compact('compra'));
    }

    /**
     * Formulario editar compra.
     */
    public function edit(Compra $compra)
    {
        $compra->load(['proveedor', 'detalles.producto']);
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();
        $productos = Producto::with('categoria')->where('estado', 'activo')->orderBy('nombre')->get();
        return view('compras.edit', compact('compra', 'proveedores', 'productos'));
    }

    /**
     * Actualizar compra.
     */
    public function update(Request $request, Compra $compra, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'fecha' => ['required', 'date'],
            'numero_factura' => ['nullable', 'string', 'max:100'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'impuesto' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:pendiente,pagado,parcial,anulado'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $detalles = $validated['detalles'];
        $subtotal = 0;

        foreach ($detalles as $d) {
            $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
            $subtotal += $lineSubtotal;
        }

        $descuento = (float) ($validated['descuento'] ?? 0);
        $impuesto = (float) ($validated['impuesto'] ?? 0);
        $total = $subtotal - $descuento + $impuesto;

        DB::transaction(function () use ($compra, $validated, $detalles, $subtotal, $descuento, $impuesto, $total, $inventarioService) {
            if ($compra->estado !== 'anulado') {
                $inventarioService->revertirCompra($compra);
            }

            $compra->detalles()->delete();

            $compra->update([
                'proveedor_id' => $validated['proveedor_id'],
                'fecha' => $validated['fecha'],
                'numero_factura' => $validated['numero_factura'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuesto' => $impuesto,
                'total' => $total,
                'estado' => $validated['estado'],
                'notas' => $validated['notas'] ?? null,
            ]);

            foreach ($detalles as $d) {
                $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $d['producto_id'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            if ($validated['estado'] !== 'anulado') {
                $compra->load('detalles');
                $inventarioService->registrarEntradaCompra($compra);
            }
        });

        return redirect()->route('compras.show', $compra)->with('success', 'Compra actualizada correctamente.');
    }

    /**
     * Eliminar compra.
     */
    public function destroy(Compra $compra, InventarioService $inventarioService)
    {
        DB::transaction(function () use ($compra, $inventarioService) {
            if ($compra->estado !== 'anulado') {
                $inventarioService->revertirCompra($compra);
            }
            $compra->detalles()->delete();
            $compra->delete();
        });

        return redirect()->route('compras.index')->with('success', 'Compra eliminada correctamente.');
    }
}
