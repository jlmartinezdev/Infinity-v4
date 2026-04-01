<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Show the login form.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('login');
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'remember' => 'sometimes|boolean'
            ]);
        } catch (ValidationException $e) {
            // Si es una petición AJAX, devolver errores de validación
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $e->errors()
                ], 422);
            }
            throw $e;
        }

        // Buscar el usuario por email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            if ($request->expectsJson() || $request->ajax()) {
                throw ValidationException::withMessages([
                    'email' => ['Las credenciales proporcionadas no son correctas.'],
                ]);
            }
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no son correctas.',
            ])->onlyInput('email');
        }

        // Verificar la contraseña manualmente
        if (!Hash::check($request->password, $user->contrasena)) {
            if ($request->expectsJson() || $request->ajax()) {
                throw ValidationException::withMessages([
                    'email' => ['Las credenciales proporcionadas no son correctas.'],
                ]);
            }
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no son correctas.',
            ])->onlyInput('email');
        }

        // Verificar que el usuario esté activo
        if ($user->estado !== 'activo') {
            if ($request->expectsJson() || $request->ajax()) {
                throw ValidationException::withMessages([
                    'email' => ['Tu cuenta está pendiente de aprobación o ha sido suspendida.'],
                ]);
            }
            return back()->withErrors([
                'email' => 'Tu cuenta está pendiente de aprobación o ha sido suspendida.',
            ])->onlyInput('email');
        }

        $remember = $request->boolean('remember', false);

        // Iniciar sesión manualmente
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Si es una petición AJAX o espera JSON, devolver JSON
        if ($request->expectsJson() || $request->ajax()) {
            $redirectAfter = $user->tienePermiso('dashboard.ver')
                ? url('/')
                : route('inicio');

            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'user' => Auth::user(),
                'redirect' => $redirectAfter,
            ]);
        }

        // Si es una petición web normal, redirigir al inicio según permisos
        $defaultHome = $user->tienePermiso('dashboard.ver')
            ? route('home', [], false)
            : route('inicio', [], false);

        return redirect()->intended($defaultHome);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Si es una petición AJAX o espera JSON, devolver JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ]);
        }

        // Si es una petición web normal, redirigir al login
        return redirect()->route('login');
    }

    /**
     * Show the registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegisterForm()
    {
        return view('register');
    }

    /**
     * Handle a registration request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'license' => 'required|string|min:5',
        ]);

        // Validar clave de licencia
        $validKeys = [
            'LIC-ISP-2025-ADMIN',
            'LIC-ISP-2025-TECNICO',
            'LIC-ISP-2025-CAJERO'
        ];

        $licenseKey = strtoupper(trim($request->license));
        
        if (!in_array($licenseKey, $validKeys)) {
            if ($request->expectsJson() || $request->ajax()) {
                throw ValidationException::withMessages([
                    'license' => ['Licencia inválida. Solicite una clave válida al administrador.'],
                ]);
            }
            return back()->withErrors([
                'license' => 'Licencia inválida. Solicite una clave válida al administrador.',
            ])->onlyInput('name', 'email');
        }

        // Determinar rol basado en la licencia y obtener o crear el rol
        $rolDescripcion = 'Cajero'; // Por defecto
        if (strpos($licenseKey, 'ADMIN') !== false) {
            $rolDescripcion = 'Administrador';
        } elseif (strpos($licenseKey, 'TECNICO') !== false) {
            $rolDescripcion = 'Técnico';
        } elseif (strpos($licenseKey, 'CAJERO') !== false) {
            $rolDescripcion = 'Cajero';
        }

        // Buscar o crear el rol
        $rol = DB::table('roles')->where('descripcion', $rolDescripcion)->first();
        
        if (!$rol) {
            // Si el rol no existe, crearlo
            $rolId = DB::table('roles')->insertGetId([
                'descripcion' => $rolDescripcion,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $rolId = $rol->rol_id;
        }

        // Crear el usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contrasena' => Hash::make($request->password),
            'rol_id' => $rolId,
            'permisos' => null, // Se puede configurar después
            'estado' => 'pendiente_aprobacion',
            'notas' => 'Registro pendiente de aprobación. Licencia: ' . $licenseKey,
        ]);

        $message = 'Registro enviado exitosamente. Un administrador aprobará su acceso.';

        // Si es una petición AJAX o espera JSON, devolver JSON
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => $user
            ], 201);
        }

        // Si es una petición web normal, redirigir al login
        return redirect()->route('login')->with('success', $message);
    }

    /**
     * Get the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => Auth::user()
        ]);
    }
}
