<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\CategoriaProducto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    /**
     * Listar productos.
     */
    public function index(Request $request)
    {
        $query = Producto::with('categoria')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $productos = $query->paginate(15)->withQueryString();
        $categorias = CategoriaProducto::orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias'));
    }

    /**
     * Formulario crear producto.
     */
    public function create()
    {
        $categorias = CategoriaProducto::orderBy('nombre')->get();
        return view('productos.create', compact('categorias'));
    }

    /**
     * Guardar nuevo producto.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'categoria_id' => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('productos', 'codigo')],
            'unidad' => ['nullable', 'string', 'max:20'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:activo,inactivo'],
        ]);

        $validated['unidad'] = $validated['unidad'] ?? 'unidad';
        $validated['stock_minimo'] = $validated['stock_minimo'] ?? 0;
        $validated['precio_compra'] = $validated['precio_compra'] ?? 0;
        $validated['precio_venta'] = $validated['precio_venta'] ?? 0;

        Producto::create($validated);

        return redirect()->route('productos.index')->with('success', 'Producto creado correctamente.');
    }

    /**
     * Formulario editar producto.
     */
    public function edit(Producto $producto)
    {
        $categorias = CategoriaProducto::orderBy('nombre')->get();
        return view('productos.edit', compact('producto', 'categorias'));
    }

    /**
     * Actualizar producto.
     */
    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'categoria_id' => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('productos', 'codigo')->ignore($producto->id)],
            'unidad' => ['nullable', 'string', 'max:20'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:activo,inactivo'],
        ]);

        $validated['unidad'] = $validated['unidad'] ?? 'unidad';
        $validated['stock_minimo'] = $validated['stock_minimo'] ?? 0;
        $validated['precio_compra'] = $validated['precio_compra'] ?? 0;
        $validated['precio_venta'] = $validated['precio_venta'] ?? 0;

        $producto->update($validated);

        return redirect()->route('productos.index')->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Eliminar producto.
     */
    public function destroy(Producto $producto)
    {
        try {
            $producto->delete();
            return redirect()->route('productos.index')->with('success', 'Producto eliminado correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('productos.index')->with('error', 'No se puede eliminar el producto porque tiene registros asociados.');
        }
    }
}
