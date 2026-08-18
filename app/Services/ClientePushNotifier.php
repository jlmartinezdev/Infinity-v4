<?php

namespace App\Services;

use App\Models\FacturaInterna;
use App\Models\FacturacionParametro;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Push FCM a la app del cliente (mismo proyecto Firebase que staff).
 */
class ClientePushNotifier
{
    public function __construct(
        protected FcmPushService $fcm
    ) {}

    public function facturaGenerada(FacturaInterna $factura): void
    {
        if (! $factura->cliente_id) {
            return;
        }
        if (in_array(strtolower((string) $factura->estado), ['anulada', 'cancelada'], true)) {
            return;
        }

        $total = (float) ($factura->total ?? 0);
        if ($total <= 0) {
            return;
        }

        $monto = number_format($total, 0, ',', '.');
        $venc = $factura->fecha_vencimiento
            ? $factura->fecha_vencimiento->format('d/m/Y')
            : null;

        $body = "Nueva factura #{$factura->id} por Gs. {$monto}.";
        if ($venc) {
            $body .= " Vence el {$venc}.";
        }

        $this->enviar((int) $factura->cliente_id, 'Nueva factura', $body, [
            'tipo' => 'facturas',
            'factura_id' => (string) $factura->id,
            'monto' => (string) $total,
            'fecha_vencimiento' => $venc ?? '',
        ]);
    }

    /**
     * Aviso N días antes del vencimiento (param notificacion_dias_antes, default 3).
     */
    public function facturaPorVencer(FacturaInterna $factura, int $diasAntes): void
    {
        if (! $factura->cliente_id || ! $factura->fecha_vencimiento) {
            return;
        }
        if (in_array(strtolower((string) $factura->estado), ['anulada', 'cancelada', 'pagada'], true)) {
            return;
        }
        if ((float) $factura->saldo_pendiente <= 0.009) {
            return;
        }

        $cacheKey = 'fcm_factura_por_vencer:'.$factura->id.':'.$factura->fecha_vencimiento->toDateString().':'.$diasAntes;
        if (! Cache::add($cacheKey, 1, now()->addDays(max(7, $diasAntes + 2)))) {
            return; // ya avisado
        }

        $monto = number_format((float) $factura->saldo_pendiente, 0, ',', '.');
        $venc = $factura->fecha_vencimiento->format('d/m/Y');
        $diasTxt = $diasAntes === 1 ? '1 día' : "{$diasAntes} días";

        $this->enviar((int) $factura->cliente_id, 'Factura por vencer', "Su factura #{$factura->id} (Gs. {$monto}) vence en {$diasTxt} ({$venc}).", [
            'tipo' => 'facturas',
            'factura_id' => (string) $factura->id,
            'monto' => (string) $factura->saldo_pendiente,
            'fecha_vencimiento' => $venc,
            'dias_antes' => (string) $diasAntes,
        ]);
    }

    public function ticketActualizado(Ticket $ticket, ?string $cambio = null): void
    {
        if (! $ticket->cliente_id) {
            return;
        }

        $estados = Ticket::estados();
        $estadoLabel = $estados[$ticket->estado] ?? $ticket->estado;
        $asunto = $ticket->relationLoaded('ticketAsunto')
            ? ($ticket->ticketAsunto->nombre ?? null)
            : ($ticket->ticketAsunto()->value('nombre'));

        $title = 'Actualización de ticket';
        $body = 'Ticket #'.$ticket->id;
        if ($asunto) {
            $body .= ' ('.$asunto.')';
        }
        $body .= ': estado '.$estadoLabel.'.';
        if ($cambio === 'observaciones' && filled($ticket->observaciones)) {
            $snippet = mb_substr(trim((string) $ticket->observaciones), 0, 80);
            $body .= ' '.$snippet.(mb_strlen(trim((string) $ticket->observaciones)) > 80 ? '…' : '');
        }

        $this->enviar((int) $ticket->cliente_id, $title, $body, [
            'tipo' => 'soporte',
            'ticket_id' => (string) $ticket->id,
            'estado' => (string) $ticket->estado,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function enviar(int $clienteId, string $title, string $body, array $data): void
    {
        try {
            $ok = $this->fcm->notifyCliente($clienteId, $title, $body, $data);
            if (! $ok) {
                Log::info('FCM cliente omitido o sin token', [
                    'cliente_id' => $clienteId,
                    'tipo' => $data['tipo'] ?? null,
                    'title' => $title,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM cliente excepción: '.$e->getMessage(), [
                'cliente_id' => $clienteId,
                'tipo' => $data['tipo'] ?? null,
            ]);
        }
    }

    public static function diasAntesVencimiento(): int
    {
        $n = FacturacionParametro::notificacionDiasAntes();

        return max(1, min(30, (int) $n));
    }
}
