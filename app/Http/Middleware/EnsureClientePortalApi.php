<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientePortalApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->esClientePortal() || ! $user->cliente_id) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso reservado a clientes.',
            ], 403);
        }

        return $next($request);
    }
}
