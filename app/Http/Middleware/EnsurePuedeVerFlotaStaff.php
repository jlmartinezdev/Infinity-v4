<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePuedeVerFlotaStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('rol');

        if (! $user || ! $user->puedeVerFlotaStaff()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sin permiso para ver la flota en mapa (staff-mapa-tecnicos.ver).',
                ], 403);
            }
            abort(403, 'Sin permiso para ver la flota en mapa.');
        }

        return $next($request);
    }
}
