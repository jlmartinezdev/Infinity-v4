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

        // Claim atómico: un solo aviso por episodio de caída (aunque el cron se solape).
        $claimed = NodoApWireless::query()
            ->where('ap_id', $ap->ap_id)
            ->where('ping_alerta_enviada', false)
            ->where('ping_fallos_seguidos', '>=', $umbral)
            ->update(['ping_alerta_enviada' => true]);

        if ($claimed === 0) {
            return false;
        }

        $ap->ping_alerta_enviada = true;
        $ok = $this->whatsapp->apWirelessCaido($ap, $destinatarios, false);
        if (! $ok) {
            Log::warning('[ap-wireless-caida] aviso marcado pero WhatsApp no envió', [
                'ap_id' => $ap->ap_id,
            ]);
        }

        return $ok;
    }
}
