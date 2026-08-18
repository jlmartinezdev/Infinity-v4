<?php

namespace App\Services\Monitoreo;

use App\Models\NodoApWireless;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\ApWirelessCaidaAvisoConfig;
use Illuminate\Support\Facades\Log;

/**
 * Actualiza ping del AP sin auditoría y avisa por WhatsApp tras N fallos seguidos.
 */
class ApWirelessPingStatusService
{
    public function __construct(
        private readonly WhatsAppOutboundNotifier $whatsapp,
    ) {}

    /**
     * @param  array{ok: bool, latency_ms: int|null, error: string|null}  $result
     */
    public function aplicarResultado(NodoApWireless $ap, array $result, bool $ipInvalida = false): void
    {
        $ap->aplicarResultadoPing($result);

        if ($ipInvalida || ($result['ok'] ?? false)) {
            return;
        }

        $this->evaluarAlerta($ap->fresh() ?? $ap);
    }

    public function evaluarAlerta(NodoApWireless $ap): bool
    {
        if (! ApWirelessCaidaAvisoConfig::enabled()) {
            return false;
        }

        $umbral = ApWirelessCaidaAvisoConfig::confirmaciones();
        $fallos = (int) ($ap->ping_fallos_seguidos ?? 0);
        $yaEnviada = (bool) ($ap->ping_alerta_enviada ?? false);

        if ($fallos < $umbral || $yaEnviada) {
            return false;
        }

        $destinatarios = ApWirelessCaidaAvisoConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            Log::info('[ap-wireless-caida] aviso omitido: sin destinatarios', [
                'ap_id' => $ap->ap_id,
            ]);

            return false;
        }

        $ok = $this->whatsapp->apWirelessCaido($ap, $destinatarios, false);
        if ($ok) {
            $ap->fill(['ping_alerta_enviada' => true])->saveQuietly();
        }

        return $ok;
    }
}
