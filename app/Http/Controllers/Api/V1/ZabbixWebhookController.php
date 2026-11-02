<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Router;
use App\Services\WhatsApp\Soporte\IncidenteZonaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZabbixWebhookController extends ApiController
{
    public function handle(Request $request, IncidenteZonaService $incidentes): JsonResponse
    {
        $secret = (string) config('whatsapp.agent.zabbix_secret', '');
        $dado = (string) $request->header('X-Zabbix-Secret', '');
        if ($secret === '' || ! hash_equals($secret, $dado)) {
            return response()->json(['ok' => false], 401);
        }

        $host = trim((string) $request->input('host', ''));
        if ($host === '') {
            return response()->json(['ok' => false, 'error' => 'falta host'], 422);
        }

        $status = strtolower(trim((string) $request->input('status', 'problem')));
        $router = Router::query()
            ->where(function ($query) use ($host) {
                $query->where('ip', $host)->orWhere('nombre', $host);
            })
            ->first();

        $resuelto = in_array($status, ['ok', 'up', '0', 'resolved'], true);
        if ($resuelto) {
            $incidentes->cerrar($router?->router_id ? (int) $router->router_id : null, $host);
        } else {
            $incidentes->abrir(
                $router?->router_id ? (int) $router->router_id : null,
                $host,
                'zabbix',
                'Alerta de '.$host,
            );
        }

        return response()->json(['ok' => true]);
    }
}
