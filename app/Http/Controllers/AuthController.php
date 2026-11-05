<?php

namespace App\Http\Controllers;

use App\Services\Auth\LoginIntentoLimiter;
use App\Services\Auth\WebLoginDispositivoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User && $user->tienePermiso('dashboard.ver')) {
                return redirect()->route('home');
            }

            return redirect()->route('inicio');
        }

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

        $limiter = app(LoginIntentoLimiter::class);
        $cuenta = (string) $request->email;
        if ($limiter->bloqueado($cuenta)) {
            return $this->respuestaIntentoBloqueado($request, $limiter->mensaje($cuenta));
        }

        // Buscar el usuario por email
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->contrasena)) {
            $limiter->registrarFallo($cuenta);
            if ($limiter->bloqueado($cuenta)) {
                return $this->respuestaIntentoBloqueado($request, $limiter->mensaje($cuenta));
            }
            if ($request->expectsJson() || $request->ajax()) {
                throw ValidationException::withMessages([
                    'email' => ['Las credenciales proporcionadas no son correctas.'],
                ]);
            }
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no son correctas.',
            ])->onlyInput('email');
        }

        $limiter->limpiar($cuenta);

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

        // Usuarios de portal cliente solo pueden ingresar por la API móvil
        if ($user->esClientePortal()) {
            if ($request->expectsJson() || $request->ajax()) {
                throw ValidationException::withMessages([
                    'email' => ['Esta cuenta es de cliente. Use la aplicación móvil.'],
                ]);
            }
            return back()->withErrors([
                'email' => 'Esta cuenta es de cliente. Use la aplicación móvil.',
            ])->onlyInput('email');
        }

        $remember = $request->boolean('remember', false);

        // Iniciar sesión manualmente (SessionGuard ya regenera la sesión en login(); no llamar regenerate() otra vez
        // o la cookie remember_web_* puede quedar desalineada con la sesión en algunos entornos).
        Auth::login($user, $remember);
        $user->registrarAcceso($request->ip());

        $dispositivo = null;
        if ($remember) {
            $dispositivo = app(WebLoginDispositivoService::class)->emitir($user);
        }

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
                'remember' => $remember,
                'dispositivo' => $dispositivo,
            ]);
        }

        // Si es una petición web normal, redirigir al inicio según permisos
        $defaultHome = $user->tienePermiso('dashboard.ver')
            ? route('home', [], false)
            : route('inicio', [], false);

        return redirect()->intended($defaultHome);
    }

    /**
     * Reingreso con token de dispositivo (localStorage). No usa la contraseña.
     */
    public function loginDispositivo(Request $request, WebLoginDispositivoService $dispositivos)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|min:32|max:128',
        ]);

        $user = $dispositivos->autenticar($validated['email'], $validated['token']);
        if (! $user) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesión guardada vencida. Iniciá sesión de nuevo.',
                ], 401);
            }

            return back()->withErrors([
                'email' => 'Sesión guardada vencida. Iniciá sesión de nuevo.',
            ]);
        }

        Auth::login($user, true);
        $user->registrarAcceso($request->ip());

        $redirectAfter = $user->tienePermiso('dashboard.ver')
            ? url('/')
            : route('inicio');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesión restaurada',
                'redirect' => $redirectAfter,
                'remember' => true,
                'dispositivo' => [
                    'token' => $validated['token'],
                    'expira_en' => WebLoginDispositivoService::DIAS * 86400,
                ],
            ]);
        }

        return redirect()->intended(
            $user->tienePermiso('dashboard.ver')
                ? route('home', [], false)
                : route('inicio', [], false)
        );
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user instanceof User) {
            app(WebLoginDispositivoService::class)->revocarTodos($user);
        }

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

    private function respuestaIntentoBloqueado(Request $request, string $mensaje): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $mensaje,
                'errors' => ['email' => [$mensaje]],
            ], 429);
        }

        return back()->withErrors([
            'email' => $mensaje,
        ])->onlyInput('email');
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
