<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    /**
     * Listar ventas.
     */
    public function index(Request $request)
    {
        $query = Venta::with(['cliente', 'servicio', 'detalles.producto'])->orderBy('fecha', 'desc');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
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

        $ventas = $query->paginate(15)->withQueryString();
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])->orderBy('nombre')->get();

        return view('ventas.index', compact('ventas', 'clientes'));
    }

    /**
     * Formulario crear venta.
     */
    public function create()
    {
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])->orderBy('nombre')->get();
        $productos = Producto::with('categoria')->where('estado', 'activo')->orderBy('nombre')->get();
        $servicios = Servicio::with('cliente')->whereIn('estado', ['activo', 'inactivo'])->orderBy('servicio_id')->get();
        return view('ventas.create', compact('clientes', 'productos', 'servicios'));
    }

    /**
     * Guardar nueva venta.
     */
    public function store(Request $request, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,servicio_id'],
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

        foreach ($detalles as $d) {
            $producto = Producto::find($d['producto_id']);
            if (!$producto || (float) $producto->stock_actual < (float) $d['cantidad']) {
                return redirect()->back()->withInput()->with('error', "Stock insuficiente para el producto: {$producto?->nombre}. Stock actual: " . ($producto?->stock_actual ?? 0));
            }
        }

        $subtotal = 0;
        foreach ($detalles as $d) {
            $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
            $subtotal += $lineSubtotal;
        }

        $descuento = (float) ($validated['descuento'] ?? 0);
        $impuesto = (float) ($validated['impuesto'] ?? 0);
        $total = $subtotal - $descuento + $impuesto;

        DB::transaction(function () use ($validated, $detalles, $subtotal, $descuento, $impuesto, $total, $inventarioService) {
            $venta = Venta::create([
                'cliente_id' => $validated['cliente_id'],
                'servicio_id' => $validated['servicio_id'] ?? null,
                'fecha' => $validated['fecha'],
                'numero_factura' => $validated['numero_factura'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuesto' => $impuesto,
                'total' => $total,
                'cobrado' => 0,
                'estado' => 'pendiente',
                'notas' => $validated['notas'] ?? null,
            ]);

            foreach ($detalles as $d) {
                $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $d['producto_id'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            $venta->load('detalles');
            $inventarioService->registrarSalidaVenta($venta);
        });

        return redirect()->route('ventas.index')->with('success', 'Venta registrada correctamente.');
    }

    /**
     * Ver detalle de venta.
     */
    public function show(Venta $venta)
    {
        $venta->load(['cliente', 'servicio', 'detalles.producto']);
        return view('ventas.show', compact('venta'));
    }

    /**
     * Formulario editar venta.
     */
    public function edit(Venta $venta)
    {
        $venta->load(['cliente', 'servicio', 'detalles.producto']);
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])->orderBy('nombre')->get();
        $productos = Producto::with('categoria')->where('estado', 'activo')->orderBy('nombre')->get();
        $servicios = Servicio::with('cliente')->whereIn('estado', ['activo', 'inactivo'])->orderBy('servicio_id')->get();
        return view('ventas.edit', compact('venta', 'clientes', 'productos', 'servicios'));
    }

    /**
     * Actualizar venta.
     */
    public function update(Request $request, Venta $venta, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,servicio_id'],
            'fecha' => ['required', 'date'],
            'numero_factura' => ['nullable', 'string', 'max:100'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'impuesto' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:pendiente,cobrado,parcial,anulado'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $detalles = $validated['detalles'];

        if ($validated['estado'] !== 'anulado') {
            foreach ($detalles as $d) {
                $producto = Producto::find($d['producto_id']);
                $cantidadNueva = (float) $d['cantidad'];
                $cantidadAnterior = (float) ($venta->detalles->firstWhere('producto_id', $d['producto_id'])?->cantidad ?? 0);
                $diferencia = $cantidadNueva - $cantidadAnterior;

                if ($diferencia > 0 && (!$producto || (float) $producto->stock_actual < $diferencia)) {
                    return redirect()->back()->withInput()->with('error', "Stock insuficiente para el producto: {$producto?->nombre}. Stock actual: " . ($producto?->stock_actual ?? 0));
                }
            }
        }

        $subtotal = 0;
        foreach ($detalles as $d) {
            $lineSubtotal = (float) $d['cantidad'] * (float) $d['precio_unitario'];
            $subtotal += $lineSubtotal;
        }

        $descuento = (float) ($validated['descuento'] ?? 0);
        $impuesto = (float) ($validated['impuesto'] ?? 0);
        $total = $subtotal - $descuento + $impuesto;

        DB::transaction(function () use ($venta, $validated, $detalles, $subtotal, $descuento, $impuesto, $total, $inventarioService) {
            if ($venta->estado !== 'anulado') {
                $inventarioService->revertirVenta($venta);
            }

            $venta->detalles()->delete();

            $venta->update([
                'cliente_id' => $validated['cliente_id'],
                'servicio_id' => $validated['servicio_id'] ?? null,
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
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $d['producto_id'],
                    'cantidad' => $d['cantidad'],
                    'precio_unitario' => $d['precio_unitario'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            if ($validated['estado'] !== 'anulado') {
                $venta->load('detalles');
                $inventarioService->registrarSalidaVenta($venta);
            }
        });

        return redirect()->route('ventas.show', $venta)->with('success', 'Venta actualizada correctamente.');
    }

    /**
     * Eliminar venta.
     */
    public function destroy(Venta $venta, InventarioService $inventarioService)
    {
        DB::transaction(function () use ($venta, $inventarioService) {
            if ($venta->estado !== 'anulado') {
                $inventarioService->revertirVenta($venta);
            }
            $venta->detalles()->delete();
            $venta->delete();
        });

        return redirect()->route('ventas.index')->with('success', 'Venta eliminada correctamente.');
    }
}
