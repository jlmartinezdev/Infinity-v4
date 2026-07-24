<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envío asíncrono por Meta Cloud API.
 *
 * Uso:
 *   EnviarWhatsAppMensaje::dispatchText('0981123456', 'Hola');
 *   EnviarWhatsAppMensaje::dispatchTemplate('0981123456', 'aviso_factura', 'es', [
 *       ['type' => 'text', 'text' => 'Juan'],
 *       ['type' => 'text', 'text' => '150000'],
 *   ]);
 */
class EnviarWhatsAppMensaje implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  'text'|'template'  $modo
     * @param  list<array{type: string, text?: string}>  $templateParams
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    public function __construct(
        public string $modo,
        public string $to,
        public ?string $body = null,
        public ?string $templateName = null,
        public ?string $templateLanguage = null,
        public array $templateParams = [],
        public array $meta = [],
    ) {}

    /**
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    public static function dispatchText(string $to, string $body, array $meta = []): void
    {
        static::dispatch('text', $to, $body, null, null, [], $meta);
    }

    /**
     * @param  list<array{type: string, text?: string}>  $bodyParameters
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    public static function dispatchTemplate(
        string $to,
        string $templateName,
        ?string $language = null,
        array $bodyParameters = [],
        array $meta = [],
    ): void {
        static::dispatch('template', $to, null, $templateName, $language, $bodyParameters, $meta);
    }

    public function handle(WhatsAppService $whatsapp): void
    {
        $mensaje = match ($this->modo) {
            'template' => $whatsapp->sendTemplate(
                $this->to,
                (string) $this->templateName,
                $this->templateLanguage,
                $this->templateParams,
                $this->meta,
            ),
            default => $whatsapp->sendText(
                $this->to,
                (string) $this->body,
                $this->meta,
            ),
        };

        Log::info('[WhatsApp job] Procesado', [
            'mensaje_id' => $mensaje->id,
            'estado' => $mensaje->estado,
            'modo' => $this->modo,
        ]);
    }
}
