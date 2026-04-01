<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    /**
     * Listado de registros de auditoría con filtros.
     */
    public function index(Request $request): View
    {
        $query = Auditoria::query()->with('usuario')->orderByDesc('created_at');

        if ($request->filled('tabla')) {
            $query->where('tabla', $request->tabla);
        }
        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }
        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $auditorias = $query->paginate(25)->withQueryString();

        $tablas = Auditoria::query()->select('tabla')->distinct()->orderBy('tabla')->pluck('tabla', 'tabla');
        $usuarios = User::query()->select('usuario_id', 'name')->orderBy('name')->get()->pluck('name', 'usuario_id');

        return view('sistema.auditoria.index', [
            'auditorias' => $auditorias,
            'tablas' => $tablas,
            'usuarios' => $usuarios,
        ]);
    }
}
