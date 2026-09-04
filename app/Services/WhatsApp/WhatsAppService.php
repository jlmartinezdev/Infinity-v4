<?php

namespace App\Services\WhatsApp;

use App\Models\Cliente;
use App\Models\WhatsappContacto;
use App\Models\WhatsappMensaje;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        if (WhatsAppAgentService::esTelefonoTest($telefono)) {
            return $this->mensajeFallidoLocal('text', $telefono, $body, null, null, 'Número de prueba n8n: no se envía a WhatsApp', $meta);
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
     * Sube un archivo a Meta y lo envía como imagen/documento/audio/video (ventana 24 h).
     *
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    public function sendUploadedMedia(
        string $to,
        \Illuminate\Http\UploadedFile $file,
        ?string $caption = null,
        array $meta = [],
    ): WhatsappMensaje {
        $telefono = $this->normalizePhone($to);
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $mime = trim(explode(';', $mime)[0]);
        if ($this->tipoDesdeMime($mime) === null) {
            $mime = $this->mimeDesdeExtension((string) $file->getClientOriginalExtension()) ?: $mime;
        }
        $tipo = $this->tipoDesdeMime($mime);
        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }
        $nombreOriginal = $file->getClientOriginalName() ?: ('archivo.'.$this->extensionDesdeMime($mime, $tipo ?: 'document'));
        $cuerpo = $caption ?: match ($tipo) {
            'image' => 'Imagen',
            'video' => 'Video',
            'audio' => 'Audio',
            default => $nombreOriginal,
        };

        if (! $telefono) {
            return $this->mensajeFallidoLocal($tipo ?: 'document', $to, $cuerpo, null, null, 'Teléfono inválido', $meta);
        }

        if ($tipo === null) {
            return $this->mensajeFallidoLocal(
                'document',
                $to,
                $cuerpo,
                null,
                null,
                'Tipo de archivo no soportado por WhatsApp (usá JPG, PNG, PDF, MP4, etc.).',
                $meta
            );
        }

        $maxBytes = $this->maxBytesParaTipo($tipo);
        if ($file->getSize() > $maxBytes) {
            $mb = (int) round($maxBytes / 1048576);

            return $this->mensajeFallidoLocal(
                $tipo,
                $to,
                $cuerpo,
                null,
                null,
                "El archivo supera el límite de {$mb} MB para {$tipo}.",
                $meta
            );
        }

        $mensaje = $this->crearSalida([
            'telefono' => $telefono,
            'tipo' => $tipo,
            'cuerpo' => $cuerpo,
            'estado' => WhatsappMensaje::ESTADO_PENDIENTE,
            ...$this->metaFields($meta),
        ]);

        if (! $this->isConfigured()) {
            return $this->marcarOmitido($mensaje, 'WhatsApp deshabilitado o sin credenciales');
        }

        $ext = $this->extensionDesdeMime($mime, $tipo);
        $relative = "whatsapp-media/{$mensaje->id}/out.{$ext}";
        try {
            $binario = file_get_contents($file->getRealPath());
            if ($binario === false) {
                throw new \RuntimeException('No se pudo leer el archivo.');
            }
            \Illuminate\Support\Facades\Storage::disk('local')->put($relative, $binario);
        } catch (\Throwable $e) {
            return $this->marcarOmitido($mensaje, 'No se pudo guardar el archivo: '.$e->getMessage());
        }

        $local = [
            'path' => $relative,
            'mime' => $mime,
            'filename' => $nombreOriginal,
            'size' => strlen($binario),
            'voice' => false,
        ];
        $mensaje->payload = ['_local' => $local];
        $mensaje->save();

        $mediaId = $this->uploadMediaToMeta($relative, $mime, $nombreOriginal);
        if (! $mediaId) {
            $mensaje->fill([
                'estado' => WhatsappMensaje::ESTADO_FALLIDO,
                'error_message' => 'Meta rechazó la subida del archivo.',
                'payload' => array_merge($mensaje->payload ?? [], ['upload_error' => true]),
            ])->save();

            return $mensaje;
        }

        $local['media_id'] = $mediaId;
        $mediaObject = ['id' => $mediaId];
        if ($caption !== null && in_array($tipo, ['image', 'video', 'document'], true)) {
            $mediaObject['caption'] = $caption;
        }
        if ($tipo === 'document') {
            $mediaObject['filename'] = $nombreOriginal;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefono,
            'type' => $tipo,
            $tipo => $mediaObject,
        ];

        $mensaje = $this->enviarPayload($mensaje, $payload);

        $merged = is_array($mensaje->payload) ? $mensaje->payload : [];
        $merged['_local'] = $local;
        $merged[$tipo] = array_merge(
            is_array($merged[$tipo] ?? null) ? $merged[$tipo] : [],
            $mediaObject
        );
        $mensaje->payload = $merged;
        $mensaje->save();

        return $mensaje;
    }

    /**
     * @return 'image'|'document'|'audio'|'video'|null
     */
    public function tipoDesdeMime(string $mime): ?string
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));

        return match (true) {
            in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true) => 'image',
            in_array($mime, ['video/mp4', 'video/3gpp'], true) => 'video',
            in_array($mime, ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg', 'audio/opus'], true) => 'audio',
            in_array($mime, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
            ], true) => 'document',
            default => null,
        };
    }

    private function mimeDesdeExtension(string $ext): ?string
    {
        return match (strtolower(trim($ext))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            '3gp' => 'video/3gpp',
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'amr' => 'audio/amr',
            'ogg', 'opus' => 'audio/ogg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            default => null,
        };
    }

    private function maxBytesParaTipo(string $tipo): int
    {
        return match ($tipo) {
            'image' => 5 * 1024 * 1024,
            'audio', 'video' => 16 * 1024 * 1024,
            'document' => 20 * 1024 * 1024, // Meta permite 100 MB; acotamos por PHP/práctico
            default => 5 * 1024 * 1024,
        };
    }

    private function uploadMediaToMeta(string $relativePath, string $mime, string $filename): ?string
    {
        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($relativePath);
        if (! is_file($absolute)) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('whatsapp.token'))
                ->timeout(max(60, (int) config('whatsapp.timeout', 30)))
                ->attach('file', file_get_contents($absolute), $filename, ['Content-Type' => $mime])
                ->post($this->mediaUploadUrl(), [
                    'messaging_product' => 'whatsapp',
                    'type' => $mime,
                ]);

            if (! $response->successful()) {
                Log::warning('[WhatsApp] Upload media falló', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'mime' => $mime,
                ]);

                return null;
            }

            $id = $response->json('id');

            return is_string($id) && $id !== '' ? $id : null;
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Excepción upload media: '.$e->getMessage(), [
                'mime' => $mime,
            ]);

            return null;
        }
    }

    private function mediaUploadUrl(): string
    {
        $base = rtrim((string) config('whatsapp.graph_base_url'), '/');
        $version = trim((string) config('whatsapp.api_version'), '/');
        $phoneId = (string) config('whatsapp.phone_number_id');

        return "{$base}/{$version}/{$phoneId}/media";
    }

    /**
     * Envío de plantilla aprobada en Meta.
     *
     * @param  list<array{type: string, text?: string, image?: array, document?: array, currency?: array, date_time?: array, parameter_name?: string}>  $bodyParameters
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     * @param  list<array{type: string, text?: string}>  $urlButtonParameters  Parámetros del botón URL (índice 0 por defecto)
     * @param  int  $urlButtonIndex  Índice del botón URL en la plantilla (0-based)
     * @param  string|null  $cuerpoVisible  Texto legible para el panel/chat (si vacío se arma desde plantilla Meta + variables)
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        ?string $language = null,
        array $bodyParameters = [],
        array $meta = [],
        array $urlButtonParameters = [],
        int $urlButtonIndex = 0,
        ?string $cuerpoVisible = null,
    ): WhatsappMensaje {
        $telefono = $this->normalizePhone($to);
        $language = $language ?: (string) config('whatsapp.default_template_language', 'es');

        $cuerpo = filled($cuerpoVisible)
            ? trim((string) $cuerpoVisible)
            : $this->renderCuerpoPlantilla($templateName, $language, $bodyParameters);
        if ($cuerpo === '') {
            $cuerpo = $templateName;
        }

        if (! $telefono) {
            return $this->mensajeFallidoLocal('template', $to, $cuerpo, $templateName, $language, 'Teléfono inválido', $meta);
        }

        $components = [];
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }
        if ($urlButtonParameters !== []) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string) $urlButtonIndex,
                'parameters' => $urlButtonParameters,
            ];
        }

        $mensaje = $this->crearSalida([
            'telefono' => $telefono,
            'tipo' => 'template',
            'cuerpo' => $cuerpo,
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
     * Lista plantillas del WABA (Meta Graph), con componentes y parámetros.
     *
     * @return list<array<string, mixed>>
     */
    public function listTemplates(int $limit = 50): array
    {
        $waba = (string) config('whatsapp.business_account_id');
        $token = (string) config('whatsapp.token');
        if ($waba === '' || $token === '') {
            return [];
        }

        $cacheKey = 'whatsapp:templates:v1:'.$waba.':'.$limit;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $version = (string) config('whatsapp.api_version', 'v25.0');
            $response = $this->http()->get("https://graph.facebook.com/{$version}/{$waba}/message_templates", [
                'fields' => 'name,status,language,category,rejected_reason,parameter_format,components',
                'limit' => $limit,
            ]);

            if (! $response->successful()) {
                Log::warning('[WhatsApp] No se pudieron listar plantillas', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return is_array($cached) ? $cached : [];
            }

            $list = collect($response->json('data', []))
                ->map(fn (array $t) => $this->mapTemplateFromMeta($t))
                ->values()
                ->all();

            if ($list !== []) {
                Cache::put($cacheKey, $list, now()->addMinutes(10));
            }

            return $list;
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Excepción listando plantillas: '.$e->getMessage());

            return is_array($cached) ? $cached : [];
        }
    }

    /**
     * Texto legible de una plantilla con variables aplicadas (para panel/chat).
     *
     * @param  list<array{type?: string, text?: string, parameter_name?: string}>  $bodyParameters
     */
    public function renderCuerpoPlantilla(string $templateName, ?string $language, array $bodyParameters = []): string
    {
        $templateName = trim($templateName);
        $values = $this->valoresParametrosCuerpo($bodyParameters);
        $bodyText = $this->textoCuerpoPlantilla($templateName, $language);

        if (filled($bodyText)) {
            return $this->aplicarVariablesPlantilla($bodyText, $values);
        }

        if ($values === []) {
            return $templateName;
        }

        $ordered = [];
        for ($i = 1; isset($values[(string) $i]); $i++) {
            $ordered[] = $values[(string) $i];
        }
        if ($ordered === []) {
            $ordered = array_values($values);
        }

        return $templateName."\n".implode(' · ', $ordered);
    }

    /**
     * Cuerpo a mostrar en UI: si es plantilla antigua (cuerpo = nombre), rehidrata desde Meta + payload.
     */
    public function cuerpoVisibleMensaje(WhatsappMensaje $mensaje): string
    {
        $cuerpo = trim((string) ($mensaje->cuerpo ?? ''));
        $tpl = trim((string) ($mensaje->template_name ?? ''));

        if ($mensaje->tipo !== 'template' || $tpl === '') {
            return $cuerpo !== '' ? $cuerpo : ($tpl !== '' ? $tpl : '—');
        }

        if ($cuerpo !== '' && strcasecmp($cuerpo, $tpl) !== 0) {
            return $cuerpo;
        }

        $params = $this->extraerParamsPlantilla($mensaje);
        $rendered = $this->renderCuerpoPlantilla($tpl, $mensaje->template_language, $params);

        return $rendered !== '' ? $rendered : ($cuerpo !== '' ? $cuerpo : $tpl);
    }

    private function textoCuerpoPlantilla(string $templateName, ?string $language): ?string
    {
        if ($templateName === '') {
            return null;
        }

        $preferido = strtolower((string) ($language ?: ''));
        $candidatas = array_values(array_filter(
            $this->listTemplates(100),
            static fn (array $t) => ($t['name'] ?? '') === $templateName && filled($t['body_text'] ?? null)
        ));

        if ($candidatas === []) {
            return null;
        }

        if ($preferido !== '') {
            foreach ($candidatas as $t) {
                $lang = strtolower((string) ($t['language'] ?? ''));
                if ($lang === $preferido
                    || str_starts_with($lang, $preferido.'_')
                    || str_starts_with($preferido, $lang.'_')) {
                    return (string) $t['body_text'];
                }
            }
        }

        return (string) ($candidatas[0]['body_text'] ?? null);
    }

    /**
     * @param  list<array{type?: string, text?: string, parameter_name?: string}>  $bodyParameters
     * @return array<string, string>
     */
    private function valoresParametrosCuerpo(array $bodyParameters): array
    {
        $values = [];
        $pos = 1;
        foreach ($bodyParameters as $p) {
            if (! is_array($p)) {
                continue;
            }
            $text = trim((string) ($p['text'] ?? ''));
            $name = trim((string) ($p['parameter_name'] ?? ''));
            if ($name !== '') {
                $values[$name] = $text;
            }
            $values[(string) $pos] = $text;
            $pos++;
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function aplicarVariablesPlantilla(string $bodyText, array $values): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u',
            static function (array $m) use ($values): string {
                $key = $m[1];
                if (array_key_exists($key, $values)) {
                    return $values[$key];
                }

                return $m[0];
            },
            $bodyText
        );

        return is_string($rendered) ? $rendered : $bodyText;
    }

    /**
     * Solo plantillas APPROVED (útiles para envío).
     *
     * @return list<array<string, mixed>>
     */
    public function listApprovedTemplates(int $limit = 50): array
    {
        return array_values(array_filter(
            $this->listTemplates($limit),
            static fn (array $t) => strtoupper((string) ($t['status'] ?? '')) === 'APPROVED'
        ));
    }

    /**
     * Resuelve el idioma real de una plantilla APPROVED en Meta.
     * Prioridad: preferido exacto → mismo prefijo (es → es_AR) → es_AR → es → primero APPROVED.
     */
    public function resolverIdiomaPlantilla(string $templateName, ?string $preferido = null): ?string
    {
        $templateName = trim($templateName);
        if ($templateName === '') {
            return null;
        }

        $preferido = $preferido !== null && $preferido !== ''
            ? $preferido
            : (string) config('whatsapp.default_template_language', 'es');

        $candidatas = array_values(array_filter(
            $this->listApprovedTemplates(),
            static fn (array $t) => ($t['name'] ?? '') === $templateName
        ));

        if ($candidatas === []) {
            return $preferido !== '' ? $preferido : null;
        }

        $langs = array_values(array_unique(array_map(
            static fn (array $t) => (string) ($t['language'] ?? ''),
            $candidatas
        )));
        $langs = array_values(array_filter($langs));

        foreach ($langs as $lang) {
            if (strcasecmp($lang, $preferido) === 0) {
                return $lang;
            }
        }

        $pref = strtolower($preferido);
        foreach ($langs as $lang) {
            $l = strtolower($lang);
            if (str_starts_with($l, $pref.'_') || str_starts_with($pref, $l.'_')) {
                return $lang;
            }
        }

        foreach (['es_AR', 'es', 'es_ES', 'en_US', 'en'] as $fallback) {
            foreach ($langs as $lang) {
                if (strcasecmp($lang, $fallback) === 0) {
                    return $lang;
                }
            }
        }

        return $langs[0] ?? $preferido;
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function mapTemplateFromMeta(array $t): array
    {
        $components = is_array($t['components'] ?? null) ? $t['components'] : [];
        $parsed = $this->parseTemplateComponents($components);

        return [
            'name' => (string) ($t['name'] ?? '-'),
            'status' => (string) ($t['status'] ?? '-'),
            'language' => (string) ($t['language'] ?? '-'),
            'category' => (string) ($t['category'] ?? '-'),
            'rejected' => (string) ($t['rejected_reason'] ?? 'NONE'),
            'parameter_format' => strtoupper((string) ($t['parameter_format'] ?? 'POSITIONAL')),
            'header_format' => $parsed['header_format'],
            'header_text' => $parsed['header_text'],
            'body_text' => $parsed['body_text'],
            'footer_text' => $parsed['footer_text'],
            'buttons' => $parsed['buttons'],
            'params' => $parsed['params'],
            'params_body_count' => count(array_filter(
                $parsed['params'],
                static fn (array $p) => ($p['component'] ?? '') === 'body'
            )),
            'params_header_count' => count(array_filter(
                $parsed['params'],
                static fn (array $p) => ($p['component'] ?? '') === 'header'
            )),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array{
     *     header_format: string|null,
     *     header_text: string|null,
     *     body_text: string|null,
     *     footer_text: string|null,
     *     buttons: list<string>,
     *     params: list<array{component: string, key: string, label: string, example: string}>
     * }
     */
    private function parseTemplateComponents(array $components): array
    {
        $headerFormat = null;
        $headerText = null;
        $bodyText = null;
        $footerText = null;
        $buttons = [];
        $params = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $headerFormat = strtoupper((string) ($component['format'] ?? 'TEXT'));
                $headerText = isset($component['text']) ? (string) $component['text'] : null;
                if ($headerFormat === 'TEXT' && filled($headerText)) {
                    foreach ($this->extractPlaceholders((string) $headerText, $component['example'] ?? [], 'header') as $p) {
                        $params[] = $p;
                    }
                } elseif (in_array($headerFormat, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                    $params[] = [
                        'component' => 'header',
                        'key' => 'media',
                        'label' => 'Header '.$headerFormat,
                        'example' => '',
                    ];
                }
            }

            if ($type === 'BODY') {
                $bodyText = isset($component['text']) ? (string) $component['text'] : null;
                if (filled($bodyText)) {
                    foreach ($this->extractPlaceholders((string) $bodyText, $component['example'] ?? [], 'body') as $p) {
                        $params[] = $p;
                    }
                }
            }

            if ($type === 'FOOTER') {
                $footerText = isset($component['text']) ? (string) $component['text'] : null;
            }

            if ($type === 'BUTTONS' && is_array($component['buttons'] ?? null)) {
                foreach ($component['buttons'] as $btn) {
                    if (! is_array($btn)) {
                        continue;
                    }
                    $btnType = (string) ($btn['type'] ?? '');
                    $btnText = (string) ($btn['text'] ?? $btnType);
                    $buttons[] = trim($btnType.($btnText !== '' ? ': '.$btnText : ''));
                }
            }
        }

        return [
            'header_format' => $headerFormat,
            'header_text' => $headerText,
            'body_text' => $bodyText,
            'footer_text' => $footerText,
            'buttons' => $buttons,
            'params' => $params,
        ];
    }

    /**
     * @param  array<string, mixed>  $example
     * @return list<array{component: string, key: string, label: string, example: string}>
     */
    private function extractPlaceholders(string $text, array $example, string $component): array
    {
        if (! preg_match_all('/\{\{\s*([^}]+?)\s*\}\}/u', $text, $matches)) {
            return [];
        }

        $keys = array_values(array_unique(array_map(
            static fn ($k) => trim((string) $k),
            $matches[1] ?? []
        )));

        $examplesByKey = [];
        if ($component === 'body') {
            if (! empty($example['body_text_named_params']) && is_array($example['body_text_named_params'])) {
                foreach ($example['body_text_named_params'] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $name = (string) ($row['param_name'] ?? '');
                    if ($name !== '') {
                        $examplesByKey[$name] = (string) ($row['example'] ?? '');
                    }
                }
            } elseif (! empty($example['body_text'][0]) && is_array($example['body_text'][0])) {
                foreach (array_values($example['body_text'][0]) as $i => $val) {
                    $examplesByKey[(string) ($i + 1)] = (string) $val;
                }
            }
        }

        if ($component === 'header') {
            if (! empty($example['header_text_named_params']) && is_array($example['header_text_named_params'])) {
                foreach ($example['header_text_named_params'] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $name = (string) ($row['param_name'] ?? '');
                    if ($name !== '') {
                        $examplesByKey[$name] = (string) ($row['example'] ?? '');
                    }
                }
            } elseif (! empty($example['header_text']) && is_array($example['header_text'])) {
                foreach (array_values($example['header_text']) as $i => $val) {
                    $examplesByKey[(string) ($i + 1)] = (string) $val;
                }
            }
        }

        $out = [];
        foreach ($keys as $key) {
            $out[] = [
                'component' => $component,
                'key' => $key,
                'label' => '{{'.$key.'}}',
                'example' => $examplesByKey[$key] ?? '',
            ];
        }

        return $out;
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
            $cuerpoVisible = trim((string) ($original->cuerpo ?? ''));
            if ($cuerpoVisible === '' || strcasecmp($cuerpoVisible, (string) $original->template_name) === 0) {
                $cuerpoVisible = null;
            }

            return $this->sendTemplate(
                (string) $original->telefono,
                (string) $original->template_name,
                $original->template_language,
                $params,
                $meta,
                [],
                0,
                $cuerpoVisible,
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
        $localPath = is_array($local) ? (string) ($local['path'] ?? '') : '';
        if ($localPath !== '' && \Illuminate\Support\Facades\Storage::disk('local')->exists($localPath)) {
            if ($tipo !== 'image' || $this->archivoLocalEsImagen($localPath)) {
                return $mensaje;
            }
            \Illuminate\Support\Facades\Storage::disk('local')->delete($localPath);
        }

        $mediaId = (string) data_get($payload, "{$tipo}.id", '');
        if ($mediaId === '') {
            return $mensaje;
        }

        $descarga = $this->descargarMediaBinario($mediaId);
        if (! $descarga) {
            return $mensaje;
        }
        if ($tipo === 'image' && ! self::esImagenBinario($descarga['binario'])) {
            Log::warning('[WhatsApp] Media descargado no es imagen', [
                'mensaje_id' => $mensaje->id,
                'media_id' => $mediaId,
                'mime' => $descarga['mime'],
                'size' => $descarga['size'],
            ]);

            return $mensaje;
        }

        $ext = $this->extensionDesdeMime($descarga['mime'], $tipo);
        $relative = "whatsapp-media/{$mensaje->id}/{$mediaId}.{$ext}";
        \Illuminate\Support\Facades\Storage::disk('local')->put($relative, $descarga['binario']);

        $mimeLocal = self::mimeDesdeBinario($descarga['binario'], $descarga['mime']);
        $payload['_local'] = [
            'path' => $relative,
            'mime' => $mimeLocal,
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
     * Borra un mensaje de Infinity (DB + archivo local). No llama a Meta.
     */
    public function borrarMensajeLocal(WhatsappMensaje $mensaje): void
    {
        $this->borrarArchivosMediaLocal($mensaje);
        $mensaje->delete();
    }

    /**
     * Borra el hilo completo de Infinity. No llama a Meta.
     *
     * @return int cantidad de mensajes eliminados
     */
    public function borrarChatLocal(?string $telefono): int
    {
        $tel = $this->normalizePhone($telefono)
            ?? (preg_replace('/\D+/', '', (string) $telefono) ?: '');
        if ($tel === '') {
            return 0;
        }

        $mensajes = WhatsappMensaje::query()->where('telefono', $tel)->get();
        foreach ($mensajes as $mensaje) {
            $this->borrarArchivosMediaLocal($mensaje);
        }
        $borrados = WhatsappMensaje::query()->where('telefono', $tel)->delete();
        WhatsappContacto::query()->where('telefono', $tel)->delete();

        return (int) $borrados;
    }

    private function borrarArchivosMediaLocal(WhatsappMensaje $mensaje): void
    {
        $dir = 'whatsapp-media/'.$mensaje->id;
        try {
            if (Storage::disk('local')->exists($dir)) {
                Storage::disk('local')->deleteDirectory($dir);
            }
            $path = data_get($mensaje->payload, '_local.path');
            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        } catch (\Throwable $e) {
            Log::notice('[WhatsApp] No se pudo borrar media local', [
                'mensaje_id' => $mensaje->id,
                'error' => $e->getMessage(),
            ]);
        }
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

            $bin = $this->httpDescargarMedia($url);

            if (! $bin->successful()) {
                Log::warning('[WhatsApp] Descarga de media falló', [
                    'media_id' => $mediaId,
                    'status' => $bin->status(),
                ]);

                return null;
            }

            $body = $bin->body();
            $mimeRespuesta = strtolower((string) ($bin->header('Content-Type') ?: $mime));
            if ($body === '' || str_contains($mimeRespuesta, 'text/html') || str_starts_with(ltrim($body), '<')) {
                Log::warning('[WhatsApp] Descarga de media no es binario', [
                    'media_id' => $mediaId,
                    'mime' => $mimeRespuesta,
                    'size' => strlen($body),
                ]);

                return null;
            }

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

    public static function mimeDesdeBinario(string $binario, string $fallback = 'application/octet-stream'): string
    {
        $head = substr($binario, 0, 16);
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            return 'image/gif';
        }
        if (strlen($binario) >= 12 && str_starts_with($head, 'RIFF') && substr($binario, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        $fallback = strtolower(trim(explode(';', $fallback)[0]));

        return $fallback !== '' ? $fallback : 'application/octet-stream';
    }

    public function mimeMediaLocal(WhatsappMensaje $mensaje, string $absolutePath): string
    {
        $guardado = (string) data_get($mensaje->payload, '_local.mime', '');
        $head = '';
        try {
            $fh = fopen($absolutePath, 'rb');
            if ($fh) {
                $head = (string) fread($fh, 16);
                fclose($fh);
            }
        } catch (\Throwable) {
            $head = '';
        }

        $sniffed = $head !== '' ? self::mimeDesdeBinario($head, $guardado) : $guardado;

        return $sniffed !== '' ? $sniffed : 'application/octet-stream';
    }

    public static function esImagenBinario(string $binario): bool
    {
        return str_starts_with(self::mimeDesdeBinario($binario, ''), 'image/');
    }

    private function archivoLocalEsImagen(string $relative): bool
    {
        try {
            $abs = \Illuminate\Support\Facades\Storage::disk('local')->path($relative);
            if (! is_file($abs) || filesize($abs) < 24) {
                return false;
            }
            $fh = fopen($abs, 'rb');
            if (! $fh) {
                return false;
            }
            $head = (string) fread($fh, 16);
            fclose($fh);

            return self::esImagenBinario($head);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Guzzle quita Authorization en redirects cross-host (lookaside.fbsbx.com).
     * Hay que seguir Location a mano conservando el Bearer.
     */
    private function httpDescargarMedia(string $url): Response
    {
        $token = (string) config('whatsapp.token');
        $timeout = max(30, (int) config('whatsapp.timeout', 30));
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'User-Agent' => 'Infinity-WhatsApp/1.0',
        ];
        $options = ['allow_redirects' => false];

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->withOptions($options)
            ->get($url);

        $hops = 0;
        while ($hops < 5 && in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $hops++;
            $loc = trim((string) $response->header('Location'));
            if ($loc === '') {
                break;
            }
            if (! str_starts_with($loc, 'http://') && ! str_starts_with($loc, 'https://')) {
                $parts = parse_url($url);
                $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
                $loc = str_starts_with($loc, '/') ? $origin.$loc : rtrim($origin, '/').'/'.$loc;
            }
            $url = $loc;
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions($options)
                ->get($url);
        }

        return $response;
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
     * Guardar nombre de contacto WA y/o vincular a un cliente ISP (panel).
     * No modifica clientes.nombre.
     *
     * @return array{
     *   contacto: WhatsappContacto,
     *   nombre: ?string,
     *   cliente_id: ?int,
     *   cliente_nombre: ?string
     * }
     */
    public function guardarContactoManual(string $telefono, ?string $nombre, ?int $clienteId, bool $quitarCliente = false): array
    {
        $tel = $this->normalizePhone($telefono) ?? preg_replace('/\D+/', '', $telefono) ?? '';
        $tel = trim((string) $tel);
        if ($tel === '') {
            throw new \InvalidArgumentException('Teléfono inválido.');
        }

        $nombre = $nombre !== null ? trim($nombre) : null;
        if ($nombre === '') {
            $nombre = null;
        }
        if ($nombre !== null) {
            $nombre = mb_substr($nombre, 0, 200);
        }

        $cliente = null;
        if (! $quitarCliente && $clienteId) {
            $cliente = Cliente::query()->find($clienteId);
            if (! $cliente) {
                throw new \InvalidArgumentException('Cliente no encontrado.');
            }
        }

        $contacto = WhatsappContacto::query()->firstOrNew(['telefono' => $tel]);
        // El panel manda el nombre del form (null/vacío = sin nombre; si hay cliente se usa el ISP).
        $contacto->nombre = $nombre;
        if (! filled($contacto->nombre) && $cliente) {
            $nombreIsp = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
            if ($nombreIsp !== '') {
                $contacto->nombre = mb_substr($nombreIsp, 0, 200);
            }
        }

        if ($quitarCliente) {
            $contacto->cliente_id = null;
        } elseif ($cliente) {
            $contacto->cliente_id = $cliente->cliente_id;
        }

        if (! $contacto->ultimo_visto_at) {
            $contacto->ultimo_visto_at = now();
        }
        $contacto->save();

        if ($quitarCliente) {
            WhatsappMensaje::query()
                ->where('telefono', $tel)
                ->update(['cliente_id' => null]);
        } elseif ($cliente) {
            WhatsappMensaje::query()
                ->where('telefono', $tel)
                ->update(['cliente_id' => $cliente->cliente_id]);
        }

        $contacto->load('cliente:cliente_id,nombre,apellido');
        $clienteNombre = $contacto->cliente
            ? trim(($contacto->cliente->nombre ?? '').' '.($contacto->cliente->apellido ?? ''))
            : null;

        return [
            'contacto' => $contacto,
            'nombre' => $contacto->nombre,
            'cliente_id' => $contacto->cliente_id,
            'cliente_nombre' => $clienteNombre !== '' ? $clienteNombre : null,
        ];
    }

    /**
     * Actualiza el contacto WA por teléfono.
     * No copia el nombre de perfil de WhatsApp: los clientes no ponen su nombre real.
     * Si hay cliente de Infinity, usa ese nombre.
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

        // Nombre visible = cliente de Infinity. No usar el perfil de WhatsApp.
        if ($clienteId) {
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
     * Cuando cambia el teléfono del cliente en Infinity:
     * - desvincula el número viejo (contacto + mensajes de ese cliente)
     * - vincula el número nuevo (si ya hay chat WA, o crea contacto ligero)
     * El historial del número viejo permanece; solo pierde el vínculo cliente_id.
     *
     * @return array{changed: bool, anterior: ?string, nuevo: ?string}
     */
    public function sincronizarTelefonoCliente(
        int $clienteId,
        ?string $telefonoAnterior,
        ?string $telefonoNuevo,
        ?string $nombreIsp = null,
    ): array {
        $normOld = $this->normalizePhone($telefonoAnterior);
        $normNew = $this->normalizePhone($telefonoNuevo);

        if ($normOld === $normNew) {
            if ($normNew) {
                $this->vincularTelefonoACliente($normNew, $clienteId, $nombreIsp, $telefonoNuevo);
            }

            return ['changed' => false, 'anterior' => $normOld, 'nuevo' => $normNew];
        }

        if ($normOld) {
            $this->desvincularTelefonoDeCliente($normOld, $clienteId, $telefonoAnterior);
        }

        if ($normNew) {
            $this->vincularTelefonoACliente($normNew, $clienteId, $nombreIsp, $telefonoNuevo);
        }

        return ['changed' => true, 'anterior' => $normOld, 'nuevo' => $normNew];
    }

    /**
     * @return list<string>
     */
    private function telefonosParaMatch(?string $phone, ?string $normalized = null): array
    {
        $out = $this->phones->variants($phone);
        $norm = $normalized ?? $this->normalizePhone($phone);
        if ($norm) {
            $out[] = $norm;
        }
        $raw = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($raw !== '') {
            $out[] = $raw;
        }

        return array_values(array_unique(array_filter($out)));
    }

    private function desvincularTelefonoDeCliente(string $normalized, int $clienteId, ?string $raw = null): void
    {
        $tels = $this->telefonosParaMatch($raw ?: $normalized, $normalized);
        if ($tels === []) {
            return;
        }

        WhatsappContacto::query()
            ->whereIn('telefono', $tels)
            ->where('cliente_id', $clienteId)
            ->update(['cliente_id' => null]);

        WhatsappMensaje::query()
            ->whereIn('telefono', $tels)
            ->where('cliente_id', $clienteId)
            ->update(['cliente_id' => null]);
    }

    private function vincularTelefonoACliente(
        string $normalized,
        int $clienteId,
        ?string $nombreIsp = null,
        ?string $raw = null,
    ): void {
        $tels = $this->telefonosParaMatch($raw ?: $normalized, $normalized);
        if ($tels === []) {
            return;
        }

        $nombre = $nombreIsp !== null && trim($nombreIsp) !== ''
            ? mb_substr(trim($nombreIsp), 0, 200)
            : null;

        $contactos = WhatsappContacto::query()->whereIn('telefono', $tels)->get();

        if ($contactos->isEmpty()) {
            $tieneMensajes = WhatsappMensaje::query()->whereIn('telefono', $tels)->exists();
            if ($tieneMensajes) {
                $attrs = [
                    'cliente_id' => $clienteId,
                    'ultimo_visto_at' => now(),
                ];
                if ($nombre) {
                    $attrs['nombre'] = $nombre;
                }
                WhatsappContacto::query()->updateOrCreate(
                    ['telefono' => $normalized],
                    $attrs
                );
            }
        } else {
            foreach ($contactos as $contacto) {
                $data = ['cliente_id' => $clienteId];
                if ($nombre && ! filled($contacto->nombre)) {
                    $data['nombre'] = $nombre;
                }
                $contacto->update($data);
            }
        }

        // El número ahora pertenece a este cliente: asociar mensajes del hilo.
        WhatsappMensaje::query()
            ->whereIn('telefono', $tels)
            ->update(['cliente_id' => $clienteId]);
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
