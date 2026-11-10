<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token del sitio de consulta de cuenta y pago. Header Authorization: Bearer.
 */
class VerifyWebConsultaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('web_consulta.token', '');
        $dado = (string) ($request->bearerToken() ?: $request->header('X-Web-Token', ''));

        if ($token === '' || $dado === '' || ! hash_equals($token, $dado)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        return $next($request);
    }
}
