<?php

namespace App\Http\Middleware;

use App\Services\Portal\DispositivoHeartbeatService;
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

        // Heartbeat last_seen para Staff Activos (soft: no bloquea el request)
        try {
            app(DispositivoHeartbeatService::class)->tocarLastSeen(
                (int) $user->cliente_id,
                $request->header('X-Device-Name')
            );
        } catch (\Throwable) {
            // ignore
        }

        return $next($request);
    }
}
