<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /**
     * Listar roles.
     */
    public function index(Request $request)
    {
        $query = Rol::query()->orderBy('descripcion');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where('descripcion', 'like', "%{$q}%");
        }

        $roles = $query->paginate(15)->withQueryString();

        return view('roles.index', compact('roles'));
    }

    /**
     * Formulario crear rol.
     */
    public function create()
    {
        return view('roles.create');
    }

    /**
     * Guardar nuevo rol.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:120', 'unique:roles,descripcion'],
        ]);

        Rol::create($validated);

        return redirect()->route('roles.index')->with('success', 'Rol creado correctamente.');
    }

    /**
     * Formulario editar rol.
     */
    public function edit(Rol $role)
    {
        return view('roles.edit', compact('role'));
    }

    /**
     * Actualizar rol.
     */
    public function update(Request $request, Rol $role)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:120', 'unique:roles,descripcion,' . $role->rol_id . ',rol_id'],
        ]);

        $role->update($validated);

        return redirect()->route('roles.index')->with('success', 'Rol actualizado correctamente.');
    }

    /**
     * Eliminar rol.
     */
    public function destroy(Rol $role)
    {
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Rol eliminado correctamente.');
    }
}
