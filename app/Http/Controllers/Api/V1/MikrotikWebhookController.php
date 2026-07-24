<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MikrotikWebhookController extends ApiController
{
    /**
     * Webhook PPPoE up/down desde script MikroTik (on-up / on-down del profile PPP).
     *
     * Auth: Authorization: Bearer {router.webhook_token}
     * Body JSON o form: evento|event (up|down), usuario|user, ip, mac|caller_id, uptime
     */
    public function pppoe(Request $request): JsonResponse
    {
        $router = $this->routerDesdeToken($request);
        if (! $router) {
            return $this->fail('Token de webhook inválido o ausente.', 401);
        }

        $evento = strtolower(trim((string) (
            $request->input('evento')
            ?? $request->input('event')
            ?? $request->input('action')
            ?? ''
        )));
        $online = in_array($evento, ['up', 'connect', 'connected', 'login', '1', 'true'], true);
        $offline = in_array($evento, ['down', 'disconnect', 'disconnected', 'logout', '0', 'false'], true);

        if (! $online && ! $offline) {
            return $this->fail('Campo evento inválido. Usá up o down.', 422);
        }

        $usuario = trim((string) (
            $request->input('usuario')
            ?? $request->input('user')
            ?? $request->input('name')
            ?? ''
        ));
        if ($usuario === '') {
            return $this->fail('Falta usuario PPPoE.', 422);
        }

        $servicio = Servicio::query()
            ->where('usuario_pppoe', $usuario)
            ->whereHas('pool', fn ($q) => $q->where('router_id', $router->router_id))
            ->orderByDesc('servicio_id')
            ->first();

        // Fallback: mismo usuario en otro pool (migración / config incompleta)
        if (! $servicio) {
            $servicio = Servicio::query()
                ->where('usuario_pppoe', $usuario)
                ->orderByDesc('servicio_id')
                ->first();
        }

        if (! $servicio) {
            Log::info('[MikroTik webhook] PPPoE sin servicio', [
                'router_id' => $router->router_id,
                'usuario' => $usuario,
                'evento' => $evento,
            ]);

            return $this->fail('Servicio no encontrado para ese usuario PPPoE.', 404, [
                'usuario' => $usuario,
                'router_id' => $router->router_id,
            ]);
        }

        $mac = trim((string) (
            $request->input('mac')
            ?? $request->input('caller_id')
            ?? $request->input('caller-id')
            ?? ''
        ));
        $ip = trim((string) ($request->input('ip') ?? $request->input('remote_address') ?? $request->input('address') ?? ''));
        $uptime = trim((string) ($request->input('uptime') ?? ''));

        try {
            $eventoModelo = ServicioConexionEvento::registrarPppoeSiCambio(
                $servicio,
                $online,
                [
                    'usuario_pppoe' => $usuario,
                    'ip' => $ip !== '' ? $ip : $servicio->ip,
                    'mac' => $mac !== '' ? strtoupper(str_replace('-', ':', $mac)) : $servicio->mac_address,
                    'router_id' => $router->router_id,
                    'uptime' => $uptime !== '' ? $uptime : null,
                    'payload' => [
                        'evento' => $online ? 'up' : 'down',
                        'raw' => $request->except(['token', 'password']),
                    ],
                ],
                ServicioConexionEvento::FUENTE_WEBHOOK
            );
        } catch (\Throwable $e) {
            Log::warning('[MikroTik webhook] Error al registrar', [
                'error' => $e->getMessage(),
                'usuario' => $usuario,
            ]);

            return $this->fail('No se pudo registrar el evento: '.$e->getMessage(), 500);
        }

        if ($eventoModelo && ! $online) {
            try {
                app(WhatsAppOutboundNotifier::class)->enlaceCaido($servicio);
            } catch (\Throwable $e) {
                Log::warning('[WhatsApp] Aviso enlace caído omitido: '.$e->getMessage(), [
                    'servicio_id' => $servicio->servicio_id,
                ]);
            }
        }

        return $this->ok([
            'registrado' => $eventoModelo !== null,
            'duplicado' => $eventoModelo === null,
            'servicio_id' => $servicio->servicio_id,
            'tipo' => $online ? ServicioConexionEvento::TIPO_PPPOE_UP : ServicioConexionEvento::TIPO_PPPOE_DOWN,
            'evento_id' => $eventoModelo?->servicio_conexion_evento_id,
        ], $eventoModelo
            ? 'Evento PPPoE registrado.'
            : 'Sin cambio (mismo estado que el último evento).');
    }

    private function routerDesdeToken(Request $request): ?Router
    {
        $auth = (string) $request->header('Authorization', '');
        $token = null;
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
            $token = $m[1];
        }
        $token = $token ?: trim((string) $request->input('token', ''));
        if ($token === '' || strlen($token) < 16) {
            return null;
        }

        return Router::query()
            ->where('webhook_token', $token)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '!=', 'inactivo');
            })
            ->first();
    }
}
