<?php

namespace App\Services\Monitoreo;

use App\Models\Router;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\RouterCaidaAvisoConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Actualiza estado/latencia de ping sin auditoría y dispara aviso WhatsApp
 * tras N fallos consecutivos (configurable).
 */
class RouterPingStatusService
{
    public function __construct(
        private readonly WhatsAppOutboundNotifier $whatsapp,
    ) {}

    /**
     * @param  array{ok: bool, latency_ms: int|null, error: string|null}  $result
     */
    public function aplicarResultado(Router $router, array $result, bool $ipInvalida = false): void
    {
        $tieneLatencia = Schema::hasColumn('routers', 'ping_latencia_ms');
        $tieneFallos = Schema::hasColumn('routers', 'ping_fallos_seguidos');
        $tieneAlerta = Schema::hasColumn('routers', 'ping_alerta_enviada');

        if ($ipInvalida) {
            $update = ['estado' => Router::ESTADO_DESCONOCIDO];
            if ($tieneLatencia) {
                $update['ping_latencia_ms'] = null;
                $update['ping_at'] = now();
            }
            if ($tieneFallos) {
                $update['ping_fallos_seguidos'] = 0;
            }
            // No resetear alerta en IP inválida: evita re-spam si vuelve a ser pinguable caído.
            $router->fill($update)->saveQuietly();

            return;
        }

        $ok = (bool) ($result['ok'] ?? false);
        $update = [
            'estado' => $ok ? Router::ESTADO_CONECTADO : Router::ESTADO_DESCONECTADO,
        ];

        if ($tieneLatencia) {
            $update['ping_latencia_ms'] = $ok ? ($result['latency_ms'] ?? null) : null;
            $update['ping_at'] = now();
        }

        $fallos = (int) ($router->ping_fallos_seguidos ?? 0);
        $alertaEnviada = (bool) ($router->ping_alerta_enviada ?? false);

        if ($ok) {
            $fallos = 0;
            $alertaEnviada = false;
        } else {
            $fallos = min(255, $fallos + 1);
        }

        if ($tieneFallos) {
            $update['ping_fallos_seguidos'] = $fallos;
        }
        if ($tieneAlerta) {
            $update['ping_alerta_enviada'] = $alertaEnviada;
        }

        $router->fill($update)->saveQuietly();

        if ($ok || ! $tieneFallos || ! $tieneAlerta) {
            return;
        }

        $this->evaluarAlerta($router->fresh() ?? $router);
    }

    public function evaluarAlerta(Router $router): void
    {
        if (! RouterCaidaAvisoConfig::enabled()) {
            return;
        }

        $umbral = RouterCaidaAvisoConfig::confirmaciones();
        $fallos = (int) ($router->ping_fallos_seguidos ?? 0);
        $yaEnviada = (bool) ($router->ping_alerta_enviada ?? false);

        if ($fallos < $umbral || $yaEnviada) {
            return;
        }

        $destinatarios = RouterCaidaAvisoConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            Log::info('[router-caida] aviso omitido: sin destinatarios', [
                'router_id' => $router->router_id,
            ]);

            return;
        }

        $ok = $this->whatsapp->routerCaido($router, $destinatarios, false);
        if ($ok && Schema::hasColumn('routers', 'ping_alerta_enviada')) {
            $router->fill(['ping_alerta_enviada' => true])->saveQuietly();
        }
    }
}
