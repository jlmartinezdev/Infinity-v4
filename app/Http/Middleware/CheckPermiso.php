<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    /**
     * Comprueba que el usuario autenticado tenga el permiso indicado.
     *
     * @param  string  $permiso  Código del permiso (ej: clientes.ver, usuarios.editar)
     */
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }
        if (!$request->user()->tienePermiso($permiso)) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        return $next($request);
    }
}
