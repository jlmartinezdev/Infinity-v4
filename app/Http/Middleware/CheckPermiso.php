<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    /**
     * Comprueba que el usuario autenticado tenga el permiso indicado.
     * Si se pasan varios (ej. permiso:a.ver,b.ver), basta con uno (OR).
     *
     * @param  string  ...$permisos  Códigos (ej: clientes.ver, loyalty-premios.ver)
     */
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        if (! $request->user()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado.',
                ], 401);
            }

            return redirect()->route('login');
        }

        $ok = false;
        foreach ($permisos as $permiso) {
            if ($permiso !== '' && $request->user()->tienePermiso($permiso)) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para realizar esta acción.',
                ], 403);
            }
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }
}
