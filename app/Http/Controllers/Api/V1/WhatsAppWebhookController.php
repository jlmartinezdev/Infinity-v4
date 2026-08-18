<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\WhatsApp\WhatsAppAgentService;
use App\Services\WhatsApp\WhatsAppInboundTicketService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Webhook Meta WhatsApp Cloud API.
 *
 * GET  /api/v1/webhooks/whatsapp  — verificación hub.challenge
 * POST /api/v1/webhooks/whatsapp  — mensajes entrantes + estados
 */
class WhatsAppWebhookController extends ApiController
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppInboundTicketService $inboundTickets,
        private readonly WhatsAppAgentService $agent,
        private readonly \App\Services\WhatsApp\WhatsAppSolicitudVerificacionService $solicitudVerificacion,
    ) {}

    public function verify(Request $request): Response|SymfonyResponse
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expected = (string) config('whatsapp.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[WhatsApp webhook] Verificación fallida', [
            'mode' => $mode,
            'token_ok' => $expected !== '' && hash_equals($expected, $token),
        ]);

        return response('Forbidden', 403);
    }

    public function handle(Request $request): SymfonyResponse
    {
        if (! $this->firmaValida($request)) {
            Log::warning('[WhatsApp webhook] Firma inválida');

            return response('Invalid signature', 403);
        }

        $payload = $request->all();

        Log::info('[WhatsApp webhook] POST recibido', [
            'fields' => collect(data_get($payload, 'entry.0.changes', []))->pluck('field')->filter()->values()->all(),
            'has_messages' => (bool) data_get($payload, 'entry.0.changes.0.value.messages'),
            'has_statuses' => (bool) data_get($payload, 'entry.0.changes.0.value.statuses'),
            'has_echoes' => (bool) data_get($payload, 'entry.0.changes.0.value.message_echoes'),
            'phone_number_id' => data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id'),
        ]);

        try {
            foreach (data_get($payload, 'entry', []) as $entry) {
                foreach (data_get($entry, 'changes', []) as $change) {
                    $field = (string) data_get($change, 'field', '');
                    $value = data_get($change, 'value', []);
                    if (! is_array($value)) {
                        continue;
                    }

                    if ($field === 'messages') {
                        $this->procesarMensajes($value);
                        $this->procesarEstados($value);
                        // Por si Meta incluye ecos en el mismo change.
                        $this->procesarEcos($value);
                    } elseif ($field === 'smb_message_echoes') {
                        Log::info('[WhatsApp webhook] Eco smb_message_echoes', [
                            'echoes' => count(data_get($value, 'message_echoes', []) ?: []),
                        ]);
                        $this->procesarEcos($value);
                    } elseif ($field === 'history') {
                        $this->procesarHistorial($value);
                    } else {
                        Log::info('[WhatsApp webhook] Campo ignorado', ['field' => $field]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Meta reintenta si no devolvemos 200; logueamos y respondemos OK para no ciclar.
            Log::error('[WhatsApp webhook] Error procesando: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function procesarMensajes(array $value): void
    {
        $nombres = $this->mapaNombresContactos($value);

        foreach (data_get($value, 'messages', []) as $message) {
            if (! is_array($message)) {
                continue;
            }

            $from = (string) ($message['from'] ?? '');
            $fromUserId = (string) ($message['from_user_id'] ?? '');
            $wamid = isset($message['id']) ? (string) $message['id'] : null;
            $tipo = (string) ($message['type'] ?? 'unknown');
            $cuerpo = $this->extraerCuerpo($message, $tipo);
            $contactoNombre = null;
            if ($from !== '' && isset($nombres[$from])) {
                $contactoNombre = $nombres[$from];
            } elseif ($fromUserId !== '' && isset($nombres[$fromUserId])) {
                $contactoNombre = $nombres[$fromUserId];
            } elseif ($nombres !== []) {
                // Un solo contacto en el webhook → suele ser el remitente.
                $contactoNombre = count($nombres) === 1 ? reset($nombres) : null;
            }

            if ($wamid && \App\Models\WhatsappMensaje::query()->where('wamid', $wamid)->exists()) {
                continue;
            }

            $registro = $this->whatsapp->registrarEntrada($from ?: $fromUserId, $tipo, $cuerpo, $message, $wamid, $contactoNombre);

            // Primero: OTP invertido de registro ("Quiero mi código de verificación")
            if ($this->solicitudVerificacion->intentarVerificar($registro)) {
                continue;
            }

            // Plugin IA (N8N): solo texto, async después del 200 a Meta.
            if ($this->agent->debeProcesar($registro)) {
                $this->agent->dispatchAfterResponse($registro);

                continue;
            }

            if (config('whatsapp.inbound_tickets_enabled', false)) {
                $this->inboundTickets->handle($registro);
            }
        }

        // Contactos sin mensaje (solo metadatos) también sincronizan nombre.
        foreach (data_get($value, 'contacts', []) as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $telefono = (string) ($contact['wa_id'] ?? '');
            $nombre = trim((string) data_get($contact, 'profile.name', ''));
            if ($telefono !== '' && $nombre !== '') {
                $this->whatsapp->sincronizarContacto($telefono, $nombre);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function procesarEstados(array $value): void
    {
        // Status webhooks a veces traen contacts[].profile.name
        foreach (data_get($value, 'contacts', []) as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $telefono = (string) ($contact['wa_id'] ?? '');
            $nombre = trim((string) data_get($contact, 'profile.name', ''));
            if ($telefono !== '' && $nombre !== '') {
                $this->whatsapp->sincronizarContacto($telefono, $nombre);
            }
        }

        foreach (data_get($value, 'statuses', []) as $status) {
            if (! is_array($status)) {
                continue;
            }
            $wamid = (string) ($status['id'] ?? '');
            $estado = (string) ($status['status'] ?? '');
            if ($wamid === '' || $estado === '') {
                continue;
            }
            $this->whatsapp->actualizarEstadoPorWamid($wamid, $estado, $status);
        }
    }

    /**
     * Mensajes enviados desde la app WhatsApp Business / dispositivo vinculado (ecos).
     *
     * @param  array<string, mixed>  $value
     */
    private function procesarEcos(array $value): void
    {
        $echoes = data_get($value, 'message_echoes', []);
        if (! is_array($echoes) || $echoes === []) {
            return;
        }

        foreach ($echoes as $echo) {
            if (! is_array($echo)) {
                continue;
            }

            $tipo = (string) ($echo['type'] ?? 'unknown');
            if (in_array($tipo, ['revoke', 'edit'], true)) {
                Log::info('[WhatsApp webhook] Eco revoke/edit (aún no aplicado)', [
                    'type' => $tipo,
                    'id' => $echo['id'] ?? null,
                    'original' => data_get($echo, 'edit.original_message_id') ?? data_get($echo, 'revoke.original_message_id'),
                ]);

                continue;
            }

            $to = (string) ($echo['to'] ?? '');
            $wamid = isset($echo['id']) ? (string) $echo['id'] : null;
            $cuerpo = $this->extraerCuerpo($echo, $tipo);

            if ($wamid && \App\Models\WhatsappMensaje::query()->where('wamid', $wamid)->exists()) {
                continue;
            }

            if ($to === '') {
                continue;
            }

            $this->whatsapp->registrarSalidaDesdeApp($to, $tipo, $cuerpo, $echo, $wamid);
        }
    }

    /**
     * Sync de historial (coexistencia). Solo nuevos wamid; no pisa lo ya guardado.
     *
     * @param  array<string, mixed>  $value
     */
    private function procesarHistorial(array $value): void
    {
        $bizDigits = preg_replace('/\D+/', '', (string) data_get($value, 'metadata.display_phone_number', '')) ?: '';
        $chunks = data_get($value, 'history', []);
        if (! is_array($chunks)) {
            return;
        }

        $nuevos = 0;
        foreach ($chunks as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }
            foreach (data_get($chunk, 'threads', []) as $thread) {
                if (! is_array($thread)) {
                    continue;
                }
                $threadId = (string) ($thread['id'] ?? '');
                foreach (data_get($thread, 'messages', []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }
                    $wamid = isset($message['id']) ? (string) $message['id'] : null;
                    if ($wamid && \App\Models\WhatsappMensaje::query()->where('wamid', $wamid)->exists()) {
                        continue;
                    }

                    $tipo = (string) ($message['type'] ?? 'unknown');
                    if (in_array($tipo, ['revoke', 'edit'], true)) {
                        continue;
                    }

                    $from = preg_replace('/\D+/', '', (string) ($message['from'] ?? '')) ?: '';
                    $to = (string) ($message['to'] ?? $threadId);
                    $cuerpo = $this->extraerCuerpo($message, $tipo);
                    $esSalida = $bizDigits !== '' && $from !== '' && str_ends_with($bizDigits, substr($from, -8));

                    if ($esSalida || filled($message['to'] ?? null)) {
                        if ($to === '') {
                            continue;
                        }
                        $this->whatsapp->registrarSalidaDesdeApp($to, $tipo, $cuerpo, $message, $wamid);
                    } else {
                        $contacto = $from !== '' ? $from : $threadId;
                        if ($contacto === '') {
                            continue;
                        }
                        $this->whatsapp->registrarEntrada($contacto, $tipo, $cuerpo, $message, $wamid);
                    }
                    $nuevos++;
                }
            }
        }

        if ($nuevos > 0) {
            Log::info('[WhatsApp webhook] Historial sincronizado', ['nuevos' => $nuevos]);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, string>  wa_id|user_id => nombre
     */
    private function mapaNombresContactos(array $value): array
    {
        $nombres = [];
        foreach (data_get($value, 'contacts', []) as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $nombre = trim((string) data_get($contact, 'profile.name', ''));
            if ($nombre === '') {
                continue;
            }
            foreach (['wa_id', 'user_id', 'from'] as $key) {
                $id = (string) ($contact[$key] ?? '');
                if ($id !== '') {
                    $nombres[$id] = $nombre;
                }
            }
        }

        return $nombres;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function extraerCuerpo(array $message, string $tipo): ?string
    {
        return match ($tipo) {
            'text' => data_get($message, 'text.body'),
            'button' => data_get($message, 'button.text') ?? data_get($message, 'button.payload'),
            'interactive' => data_get($message, 'interactive.button_reply.title')
                ?? data_get($message, 'interactive.list_reply.title')
                ?? data_get($message, 'interactive.type'),
            'image', 'audio', 'video', 'document', 'sticker' => data_get($message, "{$tipo}.caption")
                ?: ($tipo === 'audio'
                    ? (data_get($message, 'audio.voice') ? 'Nota de voz' : 'Audio')
                    : (data_get($message, "{$tipo}.filename") ?: null)),
            'location' => $this->extraerCuerpoUbicacion($message),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function extraerCuerpoUbicacion(array $message): ?string
    {
        $lat = data_get($message, 'location.latitude');
        $lng = data_get($message, 'location.longitude');
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return null;
        }

        $nombre = trim((string) data_get($message, 'location.name', ''));
        $direccion = trim((string) data_get($message, 'location.address', ''));
        $coords = trim((string) $lat).', '.trim((string) $lng);

        if ($nombre !== '' && $direccion !== '') {
            return "{$nombre}\n{$direccion}\n{$coords}";
        }
        if ($nombre !== '') {
            return "{$nombre}\n{$coords}";
        }
        if ($direccion !== '') {
            return "{$direccion}\n{$coords}";
        }

        return $coords;
    }

    private function firmaValida(Request $request): bool
    {
        $secret = (string) config('whatsapp.app_secret');
        if ($secret === '') {
            // Sin secret en .env no validamos (útil en desarrollo local); en prod configurarlo.
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
