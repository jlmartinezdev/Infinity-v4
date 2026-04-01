<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProducto;
use Illuminate\Http\Request;

class CategoriaProductoController extends Controller
{
    /**
     * Listar categorías de producto.
     */
    public function index(Request $request)
    {
        $query = CategoriaProducto::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        $categorias = $query->paginate(15)->withQueryString();

        return view('categorias-producto.index', compact('categorias'));
    }

    /**
     * Formulario crear categoría.
     */
    public function create()
    {
        return view('categorias-producto.create');
    }

    /**
     * Guardar nueva categoría.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        CategoriaProducto::create($validated);

        return redirect()->route('categorias-producto.index')->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Formulario editar categoría.
     */
    public function edit(CategoriaProducto $categoriaProducto)
    {
        return view('categorias-producto.edit', compact('categoriaProducto'));
    }

    /**
     * Actualizar categoría.
     */
    public function update(Request $request, CategoriaProducto $categoriaProducto)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $categoriaProducto->update($validated);

        return redirect()->route('categorias-producto.index')->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Eliminar categoría.
     */
    public function destroy(CategoriaProducto $categoriaProducto)
    {
        try {
            $categoriaProducto->delete();
            return redirect()->route('categorias-producto.index')->with('success', 'Categoría eliminada correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('categorias-producto.index')->with('error', 'No se puede eliminar la categoría porque tiene productos asociados.');
        }
    }
}
