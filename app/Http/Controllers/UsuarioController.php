<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Rol;
use App\Services\ClientePortalUserService;
use App\Support\PermisosCatalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    public const TIPO_SISTEMA = 'sistema';

    public const TIPO_CLIENTES = 'clientes';

    /**
     * Listar usuarios (sistema o clientes app).
     */
    public function index(Request $request, ClientePortalUserService $portal)
    {
        $tipo = $request->get('tipo', self::TIPO_SISTEMA);
        if (! in_array($tipo, [self::TIPO_SISTEMA, self::TIPO_CLIENTES], true)) {
            $tipo = self::TIPO_SISTEMA;
        }

        $query = User::with(['rol', 'cliente'])->orderBy('name');

        if ($tipo === self::TIPO_CLIENTES) {
            $query->whereNotNull('cliente_id');
        } else {
            $query->whereNull('cliente_id');
        }

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($builder) use ($q, $tipo) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
                if ($tipo === self::TIPO_CLIENTES) {
                    $builder->orWhereHas('cliente', function ($c) use ($q) {
                        $c->where('cedula', 'like', "%{$q}%")
                            ->orWhere('nombre', 'like', "%{$q}%")
                            ->orWhere('apellido', 'like', "%{$q}%");
                    });
                }
            });
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $usuarios = $query->get();
        $usuarioSeleccionado = null;
        $usuarioId = $request->get('usuario_id');

        if ($tipo === self::TIPO_SISTEMA) {
            if ($usuarioId) {
                $usuarioSeleccionado = User::with('rol')
                    ->whereNull('cliente_id')
                    ->find($usuarioId);
            } elseif ($usuarios->isNotEmpty()) {
                $usuarioSeleccionado = $usuarios->first();
            }
        }

        $esAdmin = false;
        if (Auth::check()) {
            $usuarioActual = Auth::user();
            $esAdmin = $usuarioActual->rol && strtolower($usuarioActual->rol->descripcion) === 'administrador';
        }

        $permisosPortalGlobales = [];
        $arbolPermisosPortal = [];
        $totalClientesPortal = 0;
        if ($tipo === self::TIPO_CLIENTES) {
            $permisosPortalGlobales = $portal->permisosGlobalesPortal();
            $arbolPermisosPortal = PermisosCatalogo::arbolPortalParaUi();
            $totalClientesPortal = User::whereNotNull('cliente_id')->count();
        }

        $rolesStaff = Rol::orderBy('descripcion')
            ->get()
            ->filter(fn (Rol $r) => strcasecmp($r->descripcion, ClientePortalUserService::ROL_CLIENTE_APP) !== 0)
            ->values();

        $totalClientesPortal = User::whereNotNull('cliente_id')->count();

        $permisosPortalGlobales = [];
        $arbolPermisosPortal = [];
        if ($tipo === self::TIPO_CLIENTES) {
            $permisosPortalGlobales = $portal->permisosGlobalesPortal();
            $arbolPermisosPortal = PermisosCatalogo::arbolPortalParaUi();
        }

        return view('usuarios.index', compact(
            'usuarios',
            'usuarioSeleccionado',
            'esAdmin',
            'tipo',
            'permisosPortalGlobales',
            'arbolPermisosPortal',
            'totalClientesPortal',
            'rolesStaff'
        ));
    }

    /**
     * Crear nuevo usuario (solo personal del sistema).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'rol_id' => ['required', 'integer', 'exists:roles,rol_id'],
            'estado' => ['required', 'string', 'in:activo,pendiente_aprobacion,suspendido'],
        ]);

        $rol = Rol::findOrFail($validated['rol_id']);
        if (strcasecmp($rol->descripcion, ClientePortalUserService::ROL_CLIENTE_APP) === 0) {
            return back()->with('error', 'Los usuarios cliente se crean desde el módulo de clientes / sync portal.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'contrasena' => Hash::make($validated['password']),
            'rol_id' => $validated['rol_id'],
            'estado' => $validated['estado'],
            'permisos' => null,
        ]);

        $rol->load('permisos');
        $user->permisos = PermisosCatalogo::migrarPermisos($rol->permisos->pluck('codigo')->toArray());
        $user->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente.',
                'redirect' => route('usuarios.index', ['tipo' => self::TIPO_SISTEMA, 'usuario_id' => $user->usuario_id]),
            ]);
        }

        return redirect()->route('usuarios.index', ['tipo' => self::TIPO_SISTEMA, 'usuario_id' => $user->usuario_id])
            ->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Actualizar usuario.
     */
    public function update(Request $request, $usuario)
    {
        $user = User::findOrFail($usuario);

        if ($user->esClientePortal() && ! $this->esAdministradorActual()) {
            return back()->with('error', 'Solo un administrador puede editar acceso de clientes app. Usá Solicitudes app.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->usuario_id.',usuario_id'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'rol_id' => ['required', 'integer', 'exists:roles,rol_id'],
            'estado' => ['required', 'string', 'in:activo,pendiente_aprobacion,suspendido'],
        ]);

        $rol = Rol::findOrFail($validated['rol_id']);

        if ($user->esClientePortal()) {
            if (strcasecmp($rol->descripcion, ClientePortalUserService::ROL_CLIENTE_APP) !== 0) {
                return back()->with('error', 'Un usuario cliente no puede cambiarse a un rol del sistema.');
            }
        } elseif (strcasecmp($rol->descripcion, ClientePortalUserService::ROL_CLIENTE_APP) === 0) {
            return back()->with('error', 'No se puede asignar el rol Cliente App a un usuario del sistema.');
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'rol_id' => $validated['rol_id'],
            'estado' => $validated['estado'],
        ];

        if (! empty($validated['password'])) {
            $updateData['contrasena'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        $tipo = $user->esClientePortal() ? self::TIPO_CLIENTES : self::TIPO_SISTEMA;

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente.',
                'redirect' => route('usuarios.index', ['tipo' => $tipo, 'usuario_id' => $user->usuario_id]),
            ]);
        }

        return redirect()->route('usuarios.index', ['tipo' => $tipo, 'usuario_id' => $user->usuario_id])
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Eliminar usuario.
     */
    public function destroy($usuario)
    {
        $user = User::findOrFail($usuario);

        if ($user->esClientePortal() && ! $this->esAdministradorActual()) {
            return redirect()->route('usuarios.index', ['tipo' => self::TIPO_CLIENTES])
                ->with('error', 'Solo un administrador puede eliminar usuarios portal. Usá Solicitudes app.');
        }

        $tipo = $user->esClientePortal() ? self::TIPO_CLIENTES : self::TIPO_SISTEMA;
        if ($user->esClientePortal()) {
            app(ClientePortalUserService::class)->eliminarAcceso($user);
        } else {
            $user->delete();
        }

        return redirect()->route('usuarios.index', ['tipo' => $tipo])
            ->with('success', 'Usuario eliminado correctamente.');
    }

    private function esAdministradorActual(): bool
    {
        $u = Auth::user();

        return $u && $u->rol && strtolower($u->rol->descripcion) === 'administrador';
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
            'telefono' => $user->telefono,
            'rol_id' => $user->rol_id,
            'estado' => $user->estado,
            'es_cliente_portal' => $user->esClientePortal(),
        ]);
    }

    /**
     * Actualizar permisos de un usuario del sistema.
     */
    public function updatePermisos(Request $request, $usuario)
    {
        $user = User::findOrFail($usuario);

        if ($user->esClientePortal()) {
            return redirect()->route('usuarios.index', ['tipo' => self::TIPO_CLIENTES])
                ->with('error', 'Los permisos de clientes se gestionan de forma global.');
        }

        $validated = $request->validate([
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string'],
        ]);

        $permisos = $validated['permisos'] ?? [];
        $validos = array_flip(PermisosCatalogo::todosCodigos());
        $permisos = array_values(array_filter(
            $permisos,
            fn ($c) => is_string($c) && isset($validos[$c])
        ));
        $user->permisos = $permisos;
        $user->save();

        return redirect()->route('usuarios.index', ['tipo' => self::TIPO_SISTEMA, 'usuario_id' => $user->usuario_id])
            ->with('success', 'Permisos actualizados correctamente.');
    }

    /**
     * Aplicar el mismo paquete de permisos a todos los usuarios cliente (app).
     */
    public function updatePermisosClientes(Request $request, ClientePortalUserService $portal)
    {
        $validated = $request->validate([
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string'],
        ]);

        $result = $portal->aplicarPermisosGlobalesPortal($validated['permisos'] ?? []);

        return redirect()->route('usuarios.index', ['tipo' => self::TIPO_CLIENTES])
            ->with('success', "Permisos aplicados a {$result['aplicados']} usuarios cliente.");
    }

    /**
     * Aprobar usuario pendiente.
     */
    public function aprobar(Request $request, $usuario)
    {
        $usuarioActual = Auth::user();
        if (! $usuarioActual || ! $usuarioActual->rol || strtolower($usuarioActual->rol->descripcion) !== 'administrador') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para aprobar usuarios.',
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
                    'message' => 'Este usuario no está pendiente de aprobación.',
                ], 400);
            }

            return redirect()->route('usuarios.index', ['tipo' => self::TIPO_SISTEMA, 'usuario_id' => $user->usuario_id])
                ->with('error', 'Este usuario no está pendiente de aprobación.');
        }

        $user->estado = 'activo';
        $user->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario aprobado correctamente.',
                'redirect' => route('usuarios.index', ['tipo' => self::TIPO_SISTEMA, 'usuario_id' => $user->usuario_id]),
            ]);
        }

        return redirect()->route('usuarios.index', ['tipo' => self::TIPO_SISTEMA, 'usuario_id' => $user->usuario_id])
            ->with('success', 'Usuario aprobado correctamente.');
    }
}
