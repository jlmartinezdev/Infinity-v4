<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token del widget de Windows. Header Authorization: Bearer.
 * La sesión del panel no alcanza: el tablero de Widgets no es un login de navegador.
 */
class VerifyWidgetRedToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('widget_red.token', '');
        $dado = (string) $request->bearerToken();

        if ($token === '' || $dado === '' || ! hash_equals($token, $dado)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        return $next($request);
    }
}
