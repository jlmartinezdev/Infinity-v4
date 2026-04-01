<?php

namespace App\Http\Controllers;

use App\Models\CategoriaGasto;
use Illuminate\Http\Request;

class CategoriaGastoController extends Controller
{
    /**
     * Listar categorías de gasto.
     */
    public function index(Request $request)
    {
        $query = CategoriaGasto::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        $categorias = $query->paginate(15)->withQueryString();

        return view('categorias-gasto.index', compact('categorias'));
    }

    /**
     * Formulario crear categoría.
     */
    public function create()
    {
        return view('categorias-gasto.create');
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

        CategoriaGasto::create($validated);

        return redirect()->route('categorias-gasto.index')->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Formulario editar categoría.
     */
    public function edit(CategoriaGasto $categoriaGasto)
    {
        return view('categorias-gasto.edit', compact('categoriaGasto'));
    }

    /**
     * Actualizar categoría.
     */
    public function update(Request $request, CategoriaGasto $categoriaGasto)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $categoriaGasto->update($validated);

        return redirect()->route('categorias-gasto.index')->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Eliminar categoría.
     */
    public function destroy(CategoriaGasto $categoriaGasto)
    {
        try {
            $categoriaGasto->delete();
            return redirect()->route('categorias-gasto.index')->with('success', 'Categoría eliminada correctamente.');
        } catch (\Throwable $e) {
            return redirect()->route('categorias-gasto.index')->with('error', 'No se puede eliminar la categoría porque tiene gastos asociados.');
        }
    }
}
