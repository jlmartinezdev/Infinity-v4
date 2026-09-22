<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\CategoriaProducto;
use App\Models\Proveedor;
use App\Support\LoyaltyImageUploader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    /**
     * Listar productos.
     */
    public function index(Request $request)
    {
        $query = Producto::with(['categoria', 'proveedor'])->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $productos = $query->paginate(15)->withQueryString();
        $categorias = CategoriaProducto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias', 'proveedores'));
    }

    /**
     * Armar lista de pedido a proveedor y exportar código + cantidad.
     */
    public function pedido()
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        $productosJs = $productos->map(fn (Producto $p) => [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'imagen_url' => $p->imagenUrl(),
            'stock_actual' => (float) $p->stock_actual,
            'stock_minimo' => (float) $p->stock_minimo,
            'precio_compra' => (float) $p->precio_compra,
            'moneda' => $p->moneda ?: Producto::MONEDA_PYG,
            'proveedor_id' => $p->proveedor_id,
            'proveedor' => $p->proveedor?->nombre,
            'categoria_id' => $p->categoria_id,
            'categoria' => $p->categoria?->nombre,
            'unidad' => $p->unidad ?: 'unidad',
            'stock_bajo' => $p->stockBajo(),
        ])->values();

        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get(['id', 'nombre']);
        $categorias = CategoriaProducto::orderBy('nombre')->get(['id', 'nombre']);

        $pedidoConfig = [
            'productos' => $productosJs,
            'proveedores' => $proveedores,
            'categorias' => $categorias,
        ];

        return view('productos.pedido', compact('pedidoConfig'));
    }

    /**
     * Formulario crear producto.
     */
    public function create()
    {
        $categorias = CategoriaProducto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();

        return view('productos.create', compact('categorias', 'proveedores'));
    }

    /**
     * Guardar nuevo producto.
     */
    public function store(Request $request)
    {
        $validated = $this->datosValidados($request);
        $validated['imagen'] = LoyaltyImageUploader::guardar($request, 'productos');

        Producto::create($validated);

        return redirect()->route('productos.index')->with('success', 'Producto creado correctamente.');
    }

    /**
     * Formulario editar producto.
     */
    public function edit(Producto $producto)
    {
        $categorias = CategoriaProducto::orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 'activo')->orderBy('nombre')->get();

        if ($producto->proveedor_id && ! $proveedores->contains('id', $producto->proveedor_id) && $producto->proveedor) {
            $proveedores = $proveedores->prepend($producto->proveedor)->unique('id')->values();
        }

        return view('productos.edit', compact('producto', 'categorias', 'proveedores'));
    }

    /**
     * Actualizar producto.
     */
    public function update(Request $request, Producto $producto)
    {
        $validated = $this->datosValidados($request, $producto);
        $validated['imagen'] = LoyaltyImageUploader::guardar($request, 'productos', $producto->imagen);

        $producto->update($validated);

        return redirect()->route('productos.index')->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Eliminar producto.
     */
    public function destroy(Producto $producto)
    {
        try {
            $imagen = $producto->imagen;
            $producto->delete();
            LoyaltyImageUploader::borrar($imagen);
            return redirect()->route('productos.index')->with('success', 'Producto eliminado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('productos.index')->with('error', 'No se puede eliminar el producto porque tiene registros asociados.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidados(Request $request, ?Producto $producto = null): array
    {
        $validated = $request->validate([
            'categoria_id' => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'codigo' => [
                'nullable',
                'string',
                'max:50',
                $producto
                    ? Rule::unique('productos', 'codigo')->ignore($producto->id)
                    : Rule::unique('productos', 'codigo'),
            ],
            'unidad' => ['nullable', 'string', 'max:20'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'moneda' => ['required', 'string', Rule::in(Producto::MONEDAS)],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:activo,inactivo'],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'eliminar_imagen' => ['nullable', 'boolean'],
        ]);

        unset($validated['imagen'], $validated['eliminar_imagen']);

        $validated['unidad'] = $validated['unidad'] ?? 'unidad';
        $validated['stock_minimo'] = $validated['stock_minimo'] ?? 0;
        $validated['precio_compra'] = $validated['precio_compra'] ?? 0;
        $validated['precio_venta'] = $validated['precio_venta'] ?? 0;
        $validated['moneda'] = $validated['moneda'] ?? Producto::MONEDA_PYG;
        $validated['proveedor_id'] = $validated['proveedor_id'] ?? null;

        return $validated;
    }
}
