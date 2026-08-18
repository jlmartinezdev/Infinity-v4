<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcesarWhatsappAgentJob;
use App\Models\WhatsappMensaje;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plugin N8N: reenvía texto entrante, envía reply y escala a ticket si corresponde.
 */
class WhatsAppAgentService
{
    public const CONTEXTO = 'wa_agent';

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppInboundTicketService $inboundTickets,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('whatsapp.agent.enabled')
            && filled(config('whatsapp.agent.url'));
    }

    public function debeProcesar(WhatsappMensaje $mensaje): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $mensaje->esEntrada() || $mensaje->tipo !== 'text') {
            return false;
        }

        if (trim((string) $mensaje->cuerpo) === '') {
            return false;
        }

        if ($this->yaProcesado($mensaje)) {
            return false;
        }

        if ((bool) config('whatsapp.agent.auto_send') && $this->humanoAtendiendo($mensaje)) {
            return false;
        }

        return true;
    }

    public function dispatchAfterResponse(WhatsappMensaje $mensaje): void
    {
        ProcesarWhatsappAgentJob::dispatch($mensaje->id)->afterResponse();
    }

    /**
     * @return array{
     *   ok: bool,
     *   reply: string|null,
     *   escalate: bool,
     *   cliente_id: int|null,
     *   motivo_escalado: string|null,
     *   n8n_latency_ms: int,
     *   error: string|null,
     *   enviado: bool,
     *   ticket_id: int|null
     * }
     */
    public function procesar(WhatsappMensaje $mensaje, ?bool $enviar = null): array
    {
        $enviar = $enviar ?? (bool) config('whatsapp.agent.auto_send', false);

        if ($this->yaProcesado($mensaje)) {
            return $this->resultadoVacio('duplicado');
        }

        $consulta = $this->consultarN8n($mensaje);
        $reply = $consulta['reply'];
        $escalate = $consulta['escalate'];
        $error = $consulta['error'];

        if ($error !== null || ($reply === null && ! $escalate)) {
            $error = $error ?: 'n8n_sin_reply';
            if ($enviar) {
                $reply = (string) config('whatsapp.agent.fallback_message');
            }
        }

        if ($escalate && ($reply === null || $reply === '')) {
            $reply = (string) config('whatsapp.agent.escalate_message');
        }

        $ticketId = null;
        if ($escalate && ($enviar || (bool) config('whatsapp.agent.auto_ticket', false))) {
            $ticket = $this->inboundTickets->crearOAdjuntar($mensaje, false);
            $ticketId = $ticket['ticket']?->id;
        }

        $enviado = false;
        $debeEnviar = $enviar && filled($reply) && (
            ! $escalate || (bool) config('whatsapp.agent.enviar_reply_en_escalado', true)
        );

        if ($debeEnviar) {
            $salida = $this->whatsapp->sendText($mensaje->telefono, $reply, [
                'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
                'ticket_id' => $ticketId ?? $mensaje->ticket_id,
                'contexto_tipo' => self::CONTEXTO,
                'contexto_id' => $mensaje->id,
            ]);
            $enviado = ! $salida->esFallido();
            if ($salida->esFallido()) {
                $error = trim(($error ? $error.' | ' : '').'meta: '.($salida->error_message ?: 'envio_fallido'));
            }
        }

        $this->auditarEntrada($mensaje, $consulta, $reply, $escalate, $error, $ticketId);

        Log::info('[WA agent] Procesado', [
            'message_id' => $mensaje->wamid,
            'wa_id' => $mensaje->telefono,
            'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
            'escalate' => $escalate,
            'n8n_latency_ms' => $consulta['n8n_latency_ms'],
            'error' => $error,
            'enviado' => $enviado,
            'ticket_id' => $ticketId,
        ]);

        return [
            'ok' => $error === null,
            'reply' => $reply,
            'escalate' => $escalate,
            'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
            'motivo_escalado' => $consulta['motivo_escalado'],
            'n8n_latency_ms' => $consulta['n8n_latency_ms'],
            'error' => $error,
            'enviado' => $enviado,
            'ticket_id' => $ticketId,
        ];
    }

    /**
     * @return array{
     *   reply: string|null,
     *   escalate: bool,
     *   cliente_id: int|null,
     *   motivo_escalado: string|null,
     *   n8n_latency_ms: int,
     *   error: string|null
     * }
     */
    public function consultarN8n(WhatsappMensaje $mensaje): array
    {
        $url = (string) config('whatsapp.agent.url');
        $secret = (string) config('whatsapp.agent.secret');
        $timeoutSec = max(1, (int) ceil(((int) config('whatsapp.agent.timeout_ms', 25000)) / 1000));
        $started = microtime(true);

        $vacio = [
            'reply' => null,
            'escalate' => false,
            'cliente_id' => null,
            'motivo_escalado' => null,
            'n8n_latency_ms' => 0,
            'error' => null,
        ];

        if ($url === '') {
            $vacio['error'] = 'n8n_url_vacia';

            return $vacio;
        }

        $timestamp = data_get($mensaje->payload, 'timestamp');
        $timestamp = is_numeric($timestamp) ? (int) $timestamp : (int) ($mensaje->created_at?->timestamp ?? time());

        try {
            $response = Http::timeout($timeoutSec)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(array_filter([
                    'X-Interplus-Secret' => $secret !== '' ? $secret : null,
                ]))
                ->post($url, [
                    'wa_id' => $mensaje->telefono,
                    'nombre_perfil' => $mensaje->contacto_nombre,
                    'mensaje' => (string) $mensaje->cuerpo,
                    'message_id' => (string) ($mensaje->wamid ?: 'local-'.$mensaje->id),
                    'timestamp' => $timestamp,
                    'tipo' => 'text',
                ]);
        } catch (\Throwable $e) {
            $vacio['n8n_latency_ms'] = (int) round((microtime(true) - $started) * 1000);
            $vacio['error'] = 'n8n_excepcion: '.$e->getMessage();

            return $vacio;
        }

        $vacio['n8n_latency_ms'] = (int) round((microtime(true) - $started) * 1000);

        if ($response->status() === 401) {
            $vacio['error'] = 'n8n_unauthorized';

            return $vacio;
        }

        if (! $response->successful()) {
            $vacio['error'] = 'n8n_http_'.$response->status();

            return $vacio;
        }

        $json = $response->json();
        if (! is_array($json)) {
            $vacio['error'] = 'n8n_json_invalido';

            return $vacio;
        }

        $reply = isset($json['reply']) ? trim((string) $json['reply']) : '';
        $vacio['reply'] = $reply !== '' ? mb_substr($reply, 0, 4096) : null;
        $vacio['escalate'] = filter_var($json['escalate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $vacio['cliente_id'] = isset($json['cliente_id']) && is_numeric($json['cliente_id'])
            ? (int) $json['cliente_id']
            : null;
        $motivo = isset($json['motivo_escalado']) ? trim((string) $json['motivo_escalado']) : '';
        $vacio['motivo_escalado'] = $motivo !== '' ? $motivo : null;

        return $vacio;
    }

    public function yaProcesado(WhatsappMensaje $mensaje): bool
    {
        if (data_get($mensaje->payload, 'wa_agent.reply') || data_get($mensaje->payload, 'wa_agent.usada')) {
            return true;
        }

        return WhatsappMensaje::query()
            ->where('contexto_tipo', self::CONTEXTO)
            ->where('contexto_id', $mensaje->id)
            ->exists();
    }

    /**
     * @return array{mensaje_id:int,reply:string,escalate:bool,motivo_escalado:?string,error:?string}|null
     */
    public function sugerenciaPendientePara(string $telefono): ?array
    {
        $entrada = WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->orderByDesc('id')
            ->first();

        if (! $entrada) {
            return null;
        }

        $agent = data_get($entrada->payload, 'wa_agent');
        if (! is_array($agent) || ! empty($agent['usada']) || ! empty($agent['descartada'])) {
            return null;
        }

        $reply = trim((string) ($agent['reply'] ?? ''));
        if ($reply === '') {
            return null;
        }

        return [
            'mensaje_id' => (int) $entrada->id,
            'reply' => $reply,
            'escalate' => (bool) ($agent['escalate'] ?? false),
            'motivo_escalado' => isset($agent['motivo_escalado']) ? (string) $agent['motivo_escalado'] : null,
            'error' => isset($agent['error']) ? (string) $agent['error'] : null,
        ];
    }

    public function marcarSugerencia(string $telefono, string $estado): void
    {
        if (! in_array($estado, ['usada', 'descartada'], true)) {
            return;
        }

        $entrada = WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->orderByDesc('id')
            ->first();

        if (! $entrada || ! is_array(data_get($entrada->payload, 'wa_agent'))) {
            return;
        }

        $payload = is_array($entrada->payload) ? $entrada->payload : [];
        $payload['wa_agent'][$estado] = true;
        $entrada->payload = $payload;
        $entrada->save();
    }

    public function humanoAtendiendo(WhatsappMensaje $mensaje): bool
    {
        $horas = max(0, (int) config('whatsapp.agent.humano_silencio_horas', 2));
        if ($horas === 0) {
            return false;
        }

        return WhatsappMensaje::query()
            ->where('telefono', $mensaje->telefono)
            ->where('direccion', WhatsappMensaje::DIRECCION_SALIDA)
            ->whereIn('contexto_tipo', ['manual_panel', 'app_whatsapp'])
            ->where('created_at', '>=', now()->subHours($horas))
            ->exists();
    }

    /**
     * @return array{
     *   ok: bool,
     *   reply: null,
     *   escalate: bool,
     *   cliente_id: null,
     *   motivo_escalado: string,
     *   n8n_latency_ms: int,
     *   error: string,
     *   enviado: bool,
     *   ticket_id: null
     * }
     */
    private function resultadoVacio(string $error): array
    {
        return [
            'ok' => false,
            'reply' => null,
            'escalate' => false,
            'cliente_id' => null,
            'motivo_escalado' => null,
            'n8n_latency_ms' => 0,
            'error' => $error,
            'enviado' => false,
            'ticket_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function auditarEntrada(
        WhatsappMensaje $mensaje,
        array $consulta,
        ?string $reply,
        bool $escalate,
        ?string $error,
        ?int $ticketId,
    ): void {
        $payload = is_array($mensaje->payload) ? $mensaje->payload : [];
        $payload['wa_agent'] = [
            'reply' => $reply,
            'escalate' => $escalate,
            'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
            'motivo_escalado' => $consulta['motivo_escalado'] ?? null,
            'n8n_latency_ms' => $consulta['n8n_latency_ms'] ?? 0,
            'error' => $error,
            'ticket_id' => $ticketId,
            'usada' => false,
            'descartada' => false,
        ];
        $mensaje->payload = $payload;
        $mensaje->save();
    }
}
