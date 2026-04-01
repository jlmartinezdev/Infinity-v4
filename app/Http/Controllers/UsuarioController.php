<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    /**
     * Listar usuarios.
     */
    public function index(Request $request)
    {
        $query = User::with('rol')->orderBy('name');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $usuarios = $query->get();
        $usuarioSeleccionado = null;
        $usuarioId = $request->get('usuario_id');

        if ($usuarioId) {
            $usuarioSeleccionado = User::with('rol')->find($usuarioId);
        } elseif ($usuarios->isNotEmpty()) {
            $usuarioSeleccionado = $usuarios->first();
        }

        // Verificar si el usuario actual es administrador
        $esAdmin = false;
        if (Auth::check()) {
            $usuarioActual = Auth::user();
            $esAdmin = $usuarioActual->rol && strtolower($usuarioActual->rol->descripcion) === 'administrador';
        }

        return view('usuarios.index', compact('usuarios', 'usuarioSeleccionado', 'esAdmin'));
    }

    /**
     * Crear nuevo usuario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'rol_id' => ['required', 'integer', 'exists:roles,rol_id'],
            'estado' => ['required', 'string', 'in:activo,pendiente_aprobacion,suspendido'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'contrasena' => Hash::make($validated['password']),
            'rol_id' => $validated['rol_id'],
            'estado' => $validated['estado'],
            'permisos' => null,
        ]);
        // Inicializar permisos con los del rol para que el menú y las comprobaciones usen los checkboxes
        $rol = Rol::with('permisos')->find($validated['rol_id']);
        if ($rol) {
            $user->permisos = $rol->permisos->pluck('codigo')->toArray();
            $user->save();
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente.',
                'redirect' => route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ]);
        }

        return redirect()->route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Actualizar usuario.
     */
    public function update(Request $request, $usuario)
    {
        $user = User::findOrFail($usuario);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->usuario_id . ',usuario_id'],
            'password' => ['nullable', 'string', 'min:6'],
            'rol_id' => ['required', 'integer', 'exists:roles,rol_id'],
            'estado' => ['required', 'string', 'in:activo,pendiente_aprobacion,suspendido'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'rol_id' => $validated['rol_id'],
            'estado' => $validated['estado'],
        ];

        if (!empty($validated['password'])) {
            $updateData['contrasena'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente.',
                'redirect' => route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ]);
        }

        return redirect()->route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Eliminar usuario.
     */
    public function destroy($usuario)
    {
        $user = User::findOrFail($usuario);
        $user->delete();

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Obtener datos de usuario para editar (API).
     */
    public function editData($usuario)
    {
        $user = User::findOrFail($usuario);
        
        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'rol_id' => $user->rol_id,
            'estado' => $user->estado,
        ]);
    }

    /**
     * Actualizar permisos de usuario.
     */
    public function updatePermisos(Request $request, $usuario)
    {
        $user = User::findOrFail($usuario);

        $validated = $request->validate([
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string'],
        ]);

        $permisos = $validated['permisos'] ?? [];
        $user->permisos = $permisos;
        $user->save();

        return redirect()->route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ->with('success', 'Permisos actualizados correctamente.');
    }

    /**
     * Aprobar usuario pendiente.
     */
    public function aprobar(Request $request, $usuario)
    {
        // Verificar que el usuario actual sea administrador
        $usuarioActual = Auth::user();
        if (!$usuarioActual || !$usuarioActual->rol || strtolower($usuarioActual->rol->descripcion) !== 'administrador') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para aprobar usuarios.'
                ], 403);
            }
            return redirect()->route('usuarios.index')
                ->with('error', 'No tienes permisos para aprobar usuarios.');
        }

        $user = User::findOrFail($usuario);
        
        if ($user->estado !== 'pendiente_aprobacion') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este usuario no está pendiente de aprobación.'
                ], 400);
            }
            return redirect()->route('usuarios.index', ['usuario_id' => $user->usuario_id])
                ->with('error', 'Este usuario no está pendiente de aprobación.');
        }

        $user->estado = 'activo';
        $user->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario aprobado correctamente.',
                'redirect' => route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ]);
        }

        return redirect()->route('usuarios.index', ['usuario_id' => $user->usuario_id])
            ->with('success', 'Usuario aprobado correctamente.');
    }
}
