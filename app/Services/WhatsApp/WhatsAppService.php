<?php

namespace App\Services\WhatsApp;

use App\Models\Cliente;
use App\Models\WhatsappContacto;
use App\Models\WhatsappMensaje;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente Meta WhatsApp Cloud API (salida + utilidades de entrada).
 */
class WhatsAppService
{
    public function __construct(
        private readonly WhatsAppPhoneNormalizer $phones,
    ) {}

    public function isConfigured(): bool
    {
        if (! config('whatsapp.enabled')) {
            return false;
        }

        return filled(config('whatsapp.token'))
            && filled(config('whatsapp.phone_number_id'));
    }

    public function normalizePhone(?string $phone): ?string
    {
        return $this->phones->normalize($phone);
    }

    /**
     * Busca cliente por teléfono (variantes local / E.164).
     * Esqueleto: match simple; se puede refinar después.
     */
    public function findClienteByPhone(?string $phone): ?Cliente
    {
        $variants = $this->phones->variants($phone);
        if ($variants === []) {
            return null;
        }

        // Match exacto por variantes comunes.
        $cliente = Cliente::query()
            ->whereIn('telefono', $variants)
            ->orderByDesc('cliente_id')
            ->first();

        if ($cliente) {
            return $cliente;
        }

        // Fallback: normalizar teléfonos de clientes con mismo sufijo (últimos 8–10 dígitos).
        $normalized = $this->normalizePhone($phone);
        if (! $normalized || strlen($normalized) < 10) {
            return null;
        }

        $suffix = substr($normalized, -10);

        return Cliente::query()
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->where(function ($q) use ($suffix) {
                $q->where('telefono', 'like', '%'.$suffix)
                    ->orWhere('telefono', 'like', '%'.ltrim($suffix, '0'));
            })
            ->orderByDesc('cliente_id')
            ->get(['cliente_id', 'telefono', 'nombre', 'apellido'])
            ->first(function (Cliente $c) use ($normalized) {
                return $this->normalizePhone($c->telefono) === $normalized;
            });
    }

    /**
     * Envío de texto libre (solo dentro de ventana 24 h del cliente).
     *
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    public function sendText(string $to, string $body, array $meta = []): WhatsappMensaje
    {
        $telefono = $this->normalizePhone($to);
        if (! $telefono) {
            return $this->mensajeFallidoLocal('text', $to, $body, null, null, 'Teléfono inválido', $meta);
        }

        $mensaje = $this->crearSalida([
            'telefono' => $telefono,
            'tipo' => 'text',
            'cuerpo' => $body,
            'estado' => WhatsappMensaje::ESTADO_PENDIENTE,
            ...$this->metaFields($meta),
        ]);

        if (! $this->isConfigured()) {
            return $this->marcarOmitido($mensaje, 'WhatsApp deshabilitado o sin credenciales');
        }

        return $this->enviarPayload($mensaje, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefono,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $body,
            ],
        ]);
    }

    /**
     * Envío de plantilla aprobada en Meta.
     *
     * @param  list<array{type: string, text?: string, image?: array, document?: array, currency?: array, date_time?: array}>  $bodyParameters
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        ?string $language = null,
        array $bodyParameters = [],
        array $meta = [],
    ): WhatsappMensaje {
        $telefono = $this->normalizePhone($to);
        $language = $language ?: (string) config('whatsapp.default_template_language', 'es');

        if (! $telefono) {
            return $this->mensajeFallidoLocal('template', $to, $templateName, $templateName, $language, 'Teléfono inválido', $meta);
        }

        $components = [];
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        $mensaje = $this->crearSalida([
            'telefono' => $telefono,
            'tipo' => 'template',
            'cuerpo' => $templateName,
            'template_name' => $templateName,
            'template_language' => $language,
            'estado' => WhatsappMensaje::ESTADO_PENDIENTE,
            ...$this->metaFields($meta),
        ]);

        if (! $this->isConfigured()) {
            return $this->marcarOmitido($mensaje, 'WhatsApp deshabilitado o sin credenciales');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->enviarPayload($mensaje, $payload);
    }

    /**
     * Lista plantillas del WABA (Meta Graph).
     *
     * @return list<array{name: string, status: string, language: string, category: string, rejected: string}>
     */
    public function listTemplates(int $limit = 30): array
    {
        $waba = (string) config('whatsapp.business_account_id');
        $token = (string) config('whatsapp.token');
        if ($waba === '' || $token === '') {
            return [];
        }

        try {
            $version = (string) config('whatsapp.api_version', 'v25.0');
            $response = $this->http()->get("https://graph.facebook.com/{$version}/{$waba}/message_templates", [
                'fields' => 'name,status,language,category,rejected_reason',
                'limit' => $limit,
            ]);

            if (! $response->successful()) {
                Log::warning('[WhatsApp] No se pudieron listar plantillas', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return collect($response->json('data', []))
                ->map(static fn (array $t) => [
                    'name' => (string) ($t['name'] ?? '-'),
                    'status' => (string) ($t['status'] ?? '-'),
                    'language' => (string) ($t['language'] ?? '-'),
                    'category' => (string) ($t['category'] ?? '-'),
                    'rejected' => (string) ($t['rejected_reason'] ?? 'NONE'),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Excepción listando plantillas: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Reintenta un mensaje saliente fallido (crea un nuevo envío).
     */
    public function reintentar(WhatsappMensaje $original): WhatsappMensaje
    {
        if ($original->direccion !== WhatsappMensaje::DIRECCION_SALIDA) {
            throw new \InvalidArgumentException('Solo se reintentan mensajes de salida.');
        }

        if ($original->estado !== WhatsappMensaje::ESTADO_FALLIDO) {
            throw new \InvalidArgumentException('El mensaje no está fallido.');
        }

        $meta = array_filter([
            'cliente_id' => $original->cliente_id,
            'ticket_id' => $original->ticket_id,
            'contexto_tipo' => $original->contexto_tipo ?: 'reintento',
            'contexto_id' => $original->contexto_id ?: $original->id,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($original->tipo === 'template' && filled($original->template_name)) {
            $params = $this->extraerParamsPlantilla($original);

            return $this->sendTemplate(
                (string) $original->telefono,
                (string) $original->template_name,
                $original->template_language,
                $params,
                $meta
            );
        }

        $cuerpo = (string) ($original->cuerpo ?? '');
        if ($cuerpo === '') {
            throw new \InvalidArgumentException('El mensaje no tiene texto para reenviar.');
        }

        return $this->sendText((string) $original->telefono, $cuerpo, $meta);
    }

    /**
     * @return list<array{type: string, text: string}>
     */
    private function extraerParamsPlantilla(WhatsappMensaje $mensaje): array
    {
        $components = data_get($mensaje->payload, 'request.template.components', []);
        if (! is_array($components)) {
            return [];
        }

        foreach ($components as $component) {
            if (! is_array($component) || ($component['type'] ?? '') !== 'body') {
                continue;
            }
            $params = $component['parameters'] ?? [];
            if (! is_array($params)) {
                return [];
            }

            $out = [];
            foreach ($params as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $out[] = [
                    'type' => (string) ($p['type'] ?? 'text'),
                    'text' => (string) ($p['text'] ?? ''),
                ];
            }

            return $out;
        }

        return [];
    }

    /**
     * Persiste un mensaje entrante desde el webhook.
     *
     * @param  array<string, mixed>  $rawMessage
     */
    public function registrarEntrada(
        string $from,
        string $tipo,
        ?string $cuerpo,
        array $rawMessage,
        ?string $wamid = null,
        ?string $contactoNombre = null,
    ): WhatsappMensaje {
        $telefono = $this->normalizePhone($from) ?? preg_replace('/\D+/', '', $from) ?? $from;
        $cliente = $this->findClienteByPhone($from);
        $nombre = $contactoNombre !== null ? trim($contactoNombre) : null;
        if ($nombre === '') {
            $nombre = null;
        }

        $mensaje = WhatsappMensaje::query()->create([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'telefono' => $telefono,
            'contacto_nombre' => $nombre,
            'tipo' => $tipo,
            'cuerpo' => $cuerpo,
            'wamid' => $wamid,
            'estado' => WhatsappMensaje::ESTADO_RECIBIDO,
            'cliente_id' => $cliente?->cliente_id,
            'payload' => $rawMessage,
        ]);

        $this->sincronizarContacto($telefono, $nombre, $cliente?->cliente_id);

        return $this->adjuntarMediaLocal($mensaje);
    }

    /**
     * Registra un mensaje enviado desde la app WhatsApp Business (eco / smb_message_echoes).
     *
     * @param  array<string, mixed>  $rawMessage
     */
    public function registrarSalidaDesdeApp(
        string $to,
        string $tipo,
        ?string $cuerpo,
        array $rawMessage,
        ?string $wamid = null,
    ): WhatsappMensaje {
        $telefono = $this->normalizePhone($to) ?? preg_replace('/\D+/', '', $to) ?? $to;
        $cliente = $this->findClienteByPhone($to);

        $mensaje = WhatsappMensaje::query()->create([
            'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
            'telefono' => $telefono,
            'contacto_nombre' => WhatsappContacto::query()->where('telefono', $telefono)->value('nombre'),
            'tipo' => $tipo !== '' ? $tipo : 'text',
            'cuerpo' => $cuerpo,
            'wamid' => $wamid,
            'estado' => WhatsappMensaje::ESTADO_ENVIADO,
            'cliente_id' => $cliente?->cliente_id,
            'contexto_tipo' => 'app_whatsapp',
            'payload' => $rawMessage,
        ]);

        $this->sincronizarContacto($telefono, null, $cliente?->cliente_id);

        Log::info('[WhatsApp] Eco de app registrado', [
            'mensaje_id' => $mensaje->id,
            'telefono' => $telefono,
            'tipo' => $tipo,
        ]);

        return $this->adjuntarMediaLocal($mensaje);
    }

    /**
     * Descarga media de Meta y lo guarda en disco (audio, imagen, etc.).
     * El media_id de Meta solo sirve ~7 días; por eso se cachea al llegar.
     */
    public function adjuntarMediaLocal(WhatsappMensaje $mensaje): WhatsappMensaje
    {
        $tipo = (string) $mensaje->tipo;
        if (! in_array($tipo, ['audio', 'image', 'video', 'document', 'sticker'], true)) {
            return $mensaje;
        }

        $payload = is_array($mensaje->payload) ? $mensaje->payload : [];
        $local = data_get($payload, '_local');
        if (is_array($local) && filled($local['path'] ?? null) && \Illuminate\Support\Facades\Storage::disk('local')->exists((string) $local['path'])) {
            return $mensaje;
        }

        $mediaId = (string) data_get($payload, "{$tipo}.id", '');
        if ($mediaId === '') {
            return $mensaje;
        }

        $descarga = $this->descargarMediaBinario($mediaId);
        if (! $descarga) {
            return $mensaje;
        }

        $ext = $this->extensionDesdeMime($descarga['mime'], $tipo);
        $relative = "whatsapp-media/{$mensaje->id}/{$mediaId}.{$ext}";
        \Illuminate\Support\Facades\Storage::disk('local')->put($relative, $descarga['binario']);

        $payload['_local'] = [
            'path' => $relative,
            'mime' => $descarga['mime'],
            'media_id' => $mediaId,
            'size' => $descarga['size'],
            'voice' => (bool) data_get($payload, 'audio.voice', false),
        ];
        $mensaje->payload = $payload;

        if ($tipo === 'audio' && (! filled($mensaje->cuerpo) || $mensaje->cuerpo === $mediaId)) {
            $mensaje->cuerpo = data_get($payload, 'audio.voice') ? 'Nota de voz' : 'Audio';
        } elseif ($tipo === 'image' && ! filled($mensaje->cuerpo)) {
            $mensaje->cuerpo = 'Imagen';
        }

        $mensaje->save();

        return $mensaje;
    }

    /**
     * @return array{binario: string, mime: string, size: int}|null
     */
    public function descargarMediaBinario(string $mediaId): ?array
    {
        if ($mediaId === '' || ! $this->isConfigured()) {
            return null;
        }

        try {
            $meta = $this->http()->get($this->mediaMetaUrl($mediaId), [
                'phone_number_id' => (string) config('whatsapp.phone_number_id'),
            ]);
            if (! $meta->successful()) {
                Log::warning('[WhatsApp] No se pudo resolver media URL', [
                    'media_id' => $mediaId,
                    'status' => $meta->status(),
                    'body' => $meta->body(),
                ]);

                return null;
            }

            $url = (string) $meta->json('url', '');
            $mime = (string) ($meta->json('mime_type') ?: 'application/octet-stream');
            if ($url === '') {
                return null;
            }

            $bin = Http::withToken((string) config('whatsapp.token'))
                ->timeout((int) config('whatsapp.timeout', 30))
                ->get($url);

            if (! $bin->successful()) {
                Log::warning('[WhatsApp] Descarga de media falló', [
                    'media_id' => $mediaId,
                    'status' => $bin->status(),
                ]);

                return null;
            }

            $body = $bin->body();

            return [
                'binario' => $body,
                'mime' => $mime,
                'size' => strlen($body),
            ];
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Excepción descargando media: '.$e->getMessage(), [
                'media_id' => $mediaId,
            ]);

            return null;
        }
    }

    public function rutaMediaLocal(WhatsappMensaje $mensaje): ?string
    {
        $path = data_get($mensaje->payload, '_local.path');
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            return null;
        }

        return $path;
    }

    private function extensionDesdeMime(string $mime, string $tipo): string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));

        return match (true) {
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'mpeg') || str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'mp4') || str_contains($mime, 'm4a') => 'm4a',
            str_contains($mime, 'aac') => 'aac',
            str_contains($mime, 'amr') => 'amr',
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'pdf') => 'pdf',
            default => match ($tipo) {
                'audio' => 'ogg',
                'image', 'sticker' => 'jpg',
                'video' => 'mp4',
                default => 'bin',
            },
        };
    }

    private function mediaMetaUrl(string $mediaId): string
    {
        $base = rtrim((string) config('whatsapp.graph_base_url'), '/');
        $version = trim((string) config('whatsapp.api_version'), '/');

        return "{$base}/{$version}/{$mediaId}";
    }

    /**
     * Upsert del nombre de perfil WhatsApp por teléfono.
     * No modifica clientes.nombre (dato ISP / documento).
     */
    public function sincronizarContacto(string $telefono, ?string $nombre, ?int $clienteId = null): void
    {
        $telefono = $this->normalizePhone($telefono) ?? preg_replace('/\D+/', '', $telefono) ?? $telefono;
        if ($telefono === '' || $telefono === null) {
            return;
        }

        $existing = WhatsappContacto::query()->where('telefono', $telefono)->first();
        $attrs = [
            'ultimo_visto_at' => now(),
            'mensajes_count' => ($existing?->mensajes_count ?? 0) + 1,
        ];
        $cliente = null;

        if ($nombre !== null && $nombre !== '') {
            $attrs['nombre'] = mb_substr($nombre, 0, 200);
        }

        if ($clienteId) {
            $attrs['cliente_id'] = $clienteId;
        } elseif (! $existing?->cliente_id) {
            $cliente = $this->findClienteByPhone($telefono);
            if ($cliente) {
                $attrs['cliente_id'] = $cliente->cliente_id;
                $clienteId = $cliente->cliente_id;
            }
        } else {
            $clienteId = $clienteId ?: $existing?->cliente_id;
        }

        // Si Meta no mandó profile.name, usar nombre del cliente ISP como provisional.
        if (($nombre === null || $nombre === '') && ! ($existing?->nombre) && $clienteId) {
            $cliente = $cliente ?: Cliente::query()->find($clienteId);
            $nombreIsp = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
            if ($nombreIsp !== '') {
                $attrs['nombre'] = mb_substr($nombreIsp, 0, 200);
            }
        }

        WhatsappContacto::query()->updateOrCreate(
            ['telefono' => $telefono],
            $attrs
        );
    }

    /**
     * Actualiza estado de un mensaje saliente (delivered / read / failed).
     *
     * @param  array<string, mixed>  $statusPayload
     */
    public function actualizarEstadoPorWamid(string $wamid, string $estadoMeta, array $statusPayload = []): ?WhatsappMensaje
    {
        $mensaje = WhatsappMensaje::query()->where('wamid', $wamid)->first();
        if (! $mensaje) {
            Log::info('[WhatsApp] Estado sin mensaje local', [
                'wamid' => $wamid,
                'estado' => $estadoMeta,
            ]);

            return null;
        }

        $estado = match ($estadoMeta) {
            'sent' => WhatsappMensaje::ESTADO_ENVIADO,
            'delivered' => WhatsappMensaje::ESTADO_ENTREGADO,
            'read' => WhatsappMensaje::ESTADO_LEIDO,
            'failed' => WhatsappMensaje::ESTADO_FALLIDO,
            default => $mensaje->estado,
        };

        $error = data_get($statusPayload, 'errors.0');
        $mensaje->fill([
            'estado' => $estado,
            'error_code' => $error ? (string) ($error['code'] ?? '') : $mensaje->error_code,
            'error_message' => $error ? (string) ($error['title'] ?? $error['message'] ?? '') : $mensaje->error_message,
            'payload' => array_merge($mensaje->payload ?? [], ['status' => $statusPayload]),
        ])->save();

        return $mensaje;
    }

    /**
     * Avisa a Meta que leímos el mensaje entrante (ticks azules en el celular del cliente).
     * Meta marca la conversación como leída a partir del wamid indicado.
     *
     * @return array{ok: bool, marcados: int, error?: string}
     */
    public function marcarConversacionLeida(string $telefono): array
    {
        $tel = $this->normalizePhone($telefono);
        if (! $tel) {
            return ['ok' => false, 'marcados' => 0, 'error' => 'Teléfono inválido'];
        }

        if (! $this->isConfigured()) {
            return ['ok' => false, 'marcados' => 0, 'error' => 'WhatsApp no configurado'];
        }

        $pendientes = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->where('estado', '!=', WhatsappMensaje::ESTADO_LEIDO)
            ->whereNotNull('wamid')
            ->where('wamid', '!=', '')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        if ($pendientes->isEmpty()) {
            return ['ok' => true, 'marcados' => 0];
        }

        // Meta: con el último wamid alcanza para marcar el hilo como leído.
        $ultimo = $pendientes->first();
        $okMeta = $this->enviarReciboLeido((string) $ultimo->wamid);

        if (! $okMeta) {
            return ['ok' => false, 'marcados' => 0, 'error' => 'Meta rechazó el recibo de lectura'];
        }

        $ids = $pendientes->pluck('id')->all();
        WhatsappMensaje::query()
            ->whereIn('id', $ids)
            ->update(['estado' => WhatsappMensaje::ESTADO_LEIDO, 'updated_at' => now()]);

        return ['ok' => true, 'marcados' => count($ids)];
    }

    public function enviarReciboLeido(string $wamid): bool
    {
        if ($wamid === '' || ! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->http()->post($this->messagesUrl(), [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $wamid,
            ]);

            if (! $response->successful()) {
                Log::warning('[WhatsApp] Recibo de lectura falló', [
                    'wamid' => $wamid,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Excepción recibo de lectura: '.$e->getMessage(), [
                'wamid' => $wamid,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enviarPayload(WhatsappMensaje $mensaje, array $payload): WhatsappMensaje
    {
        try {
            $response = $this->http()->post($this->messagesUrl(), $payload);
            $json = $response->json() ?? [];

            if (! $response->successful()) {
                return $this->marcarRespuestaError($mensaje, $response, $json, $payload);
            }

            $wamid = data_get($json, 'messages.0.id');
            $mensaje->fill([
                'wamid' => is_string($wamid) ? $wamid : null,
                'estado' => WhatsappMensaje::ESTADO_ENVIADO,
                'payload' => [
                    'request' => $payload,
                    'response' => $json,
                ],
            ])->save();

            return $mensaje;
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Excepción al enviar: '.$e->getMessage(), [
                'mensaje_id' => $mensaje->id,
            ]);

            $mensaje->fill([
                'estado' => WhatsappMensaje::ESTADO_FALLIDO,
                'error_message' => $e->getMessage(),
                'payload' => ['request' => $payload, 'exception' => $e->getMessage()],
            ])->save();

            return $mensaje;
        }
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function crearSalida(array $attrs): WhatsappMensaje
    {
        if (empty($attrs['cliente_id'])) {
            $attrs['cliente_id'] = $this->findClienteByPhone($attrs['telefono'] ?? null)?->cliente_id;
        }

        if (empty($attrs['contacto_nombre']) && ! empty($attrs['telefono'])) {
            $nombre = WhatsappContacto::query()
                ->where('telefono', $attrs['telefono'])
                ->value('nombre');
            if ($nombre) {
                $attrs['contacto_nombre'] = $nombre;
            }
        }

        return WhatsappMensaje::query()->create(array_merge([
            'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
        ], $attrs));
    }

    /**
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     * @return array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}
     */
    private function metaFields(array $meta): array
    {
        return array_filter([
            'cliente_id' => $meta['cliente_id'] ?? null,
            'ticket_id' => $meta['ticket_id'] ?? null,
            'contexto_tipo' => $meta['contexto_tipo'] ?? null,
            'contexto_id' => $meta['contexto_id'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    private function mensajeFallidoLocal(
        string $tipo,
        string $to,
        ?string $cuerpo,
        ?string $templateName,
        ?string $language,
        string $error,
        array $meta,
    ): WhatsappMensaje {
        return WhatsappMensaje::query()->create([
            'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
            'telefono' => preg_replace('/\D+/', '', $to) ?: $to,
            'tipo' => $tipo,
            'cuerpo' => $cuerpo,
            'template_name' => $templateName,
            'template_language' => $language,
            'estado' => WhatsappMensaje::ESTADO_FALLIDO,
            'error_message' => $error,
            ...$this->metaFields($meta),
        ]);
    }

    private function marcarOmitido(WhatsappMensaje $mensaje, string $reason): WhatsappMensaje
    {
        Log::info('[WhatsApp] Envío omitido', [
            'mensaje_id' => $mensaje->id,
            'reason' => $reason,
        ]);

        $mensaje->fill([
            'estado' => WhatsappMensaje::ESTADO_FALLIDO,
            'error_message' => $reason,
        ])->save();

        return $mensaje;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $payload
     */
    private function marcarRespuestaError(WhatsappMensaje $mensaje, Response $response, array $json, array $payload): WhatsappMensaje
    {
        $error = data_get($json, 'error', []);
        Log::warning('[WhatsApp] Envío fallido', [
            'mensaje_id' => $mensaje->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        $mensaje->fill([
            'estado' => WhatsappMensaje::ESTADO_FALLIDO,
            'error_code' => isset($error['code']) ? (string) $error['code'] : (string) $response->status(),
            'error_message' => (string) ($error['message'] ?? $response->body()),
            'payload' => [
                'request' => $payload,
                'response' => $json,
            ],
        ])->save();

        return $mensaje;
    }

    private function messagesUrl(): string
    {
        $base = rtrim((string) config('whatsapp.graph_base_url'), '/');
        $version = trim((string) config('whatsapp.api_version'), '/');
        $phoneId = (string) config('whatsapp.phone_number_id');

        return "{$base}/{$version}/{$phoneId}/messages";
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) config('whatsapp.token'))
            ->acceptJson()
            ->timeout((int) config('whatsapp.timeout', 30));
    }
}
