<?php

namespace App\Http\Controllers;

use App\Models\EstadoPedido;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstadoPedidoController extends Controller
{
    /**
     * Listar estados de pedidos.
     */
    public function index(Request $request)
    {
        $query = EstadoPedido::with('rol')->orderBy('descripcion');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where('descripcion', 'like', "%{$q}%");
        }

        $estados = $query->paginate(15)->withQueryString();

        return view('estados-pedidos.index', compact('estados'));
    }

    /**
     * Formulario crear estado de pedido.
     */
    public function create()
    {
        $roles = Rol::orderBy('descripcion')->get();
        return view('estados-pedidos.create', compact('roles'));
    }

    /**
     * Guardar nuevo estado de pedido.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:120'],
            'rol_id' => ['nullable', 'integer', 'exists:roles,rol_id'],
            'parametro' => ['nullable', 'string'],
        ]);

        EstadoPedido::create($validated);

        return redirect()->route('estados-pedidos.index')->with('success', 'Estado de pedido creado correctamente.');
    }

    /**
     * Formulario editar estado de pedido.
     */
    public function edit(EstadoPedido $estadosPedido)
    {
        $roles = Rol::orderBy('descripcion')->get();
        return view('estados-pedidos.edit', compact('estadosPedido', 'roles'));
    }

    /**
     * Actualizar estado de pedido.
     */
    public function update(Request $request, EstadoPedido $estadosPedido)
    {
        $validated = $request->validate([
            'descripcion' => ['required', 'string', 'max:120'],
            'rol_id' => ['nullable', 'integer', 'exists:roles,rol_id'],
            'parametro' => ['nullable', 'string'],
        ]);

        $estadosPedido->update($validated);

        return redirect()->route('estados-pedidos.index')->with('success', 'Estado de pedido actualizado correctamente.');
    }

    /**
     * Eliminar estado de pedido.
     */
    public function destroy(EstadoPedido $estadosPedido)
    {
        $estadosPedido->delete();

        return redirect()->route('estados-pedidos.index')->with('success', 'Estado de pedido eliminado correctamente.');
    }
}
