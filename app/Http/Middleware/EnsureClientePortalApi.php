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

        // Heartbeat last_seen + app_version para Staff / solicitudes (soft: no bloquea)
        try {
            app(DispositivoHeartbeatService::class)->tocarLastSeen(
                (int) $user->cliente_id,
                $request->header('X-Device-Name'),
                DispositivoHeartbeatService::versionDesdeRequest($request)
            );
        } catch (\Throwable) {
            // ignore
        }

        return $next($request);
    }
}
