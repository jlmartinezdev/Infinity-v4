<?php

namespace App\Console\Commands;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WhatsAppEstadoCommand extends Command
{
    protected $signature = 'whatsapp:estado';

    protected $description = 'Muestra configuración WhatsApp, últimos mensajes y plantillas Meta';

    public function handle(WhatsAppService $whatsapp): int
    {
        $this->info('Configuración');
        $this->table(['clave', 'valor'], [
            ['enabled', config('whatsapp.enabled') ? 'true' : 'false'],
            ['configured', $whatsapp->isConfigured() ? 'true' : 'false'],
            ['phone_number_id', (string) config('whatsapp.phone_number_id')],
            ['business_account_id', (string) config('whatsapp.business_account_id')],
            ['api_version', (string) config('whatsapp.api_version')],
            ['inbound_tickets', config('whatsapp.inbound_tickets_enabled') ? 'true' : 'false'],
            ['auto_reply', config('whatsapp.auto_reply_enabled') ? 'true' : 'false'],
            ['event.ticket_asignado', config('whatsapp.events.ticket_asignado') ? 'true' : 'false'],
            ['event.factura', config('whatsapp.events.factura') ? 'true' : 'false'],
            ['event.enlace_caido', config('whatsapp.events.enlace_caido') ? 'true' : 'false'],
            ['token', filled(config('whatsapp.token')) ? '***' : '(vacío)'],
            ['app_secret', filled(config('whatsapp.app_secret')) ? '***' : '(vacío)'],
            ['verify_token', filled(config('whatsapp.verify_token')) ? '***' : '(vacío)'],
        ]);

        $this->info('Últimos mensajes');
        $rows = WhatsappMensaje::query()
            ->latest('id')
            ->limit(10)
            ->get(['id', 'direccion', 'telefono', 'tipo', 'estado', 'cuerpo', 'created_at']);

        if ($rows->isEmpty()) {
            $this->line('(sin registros)');
        } else {
            $this->table(
                ['id', 'dir', 'telefono', 'tipo', 'estado', 'cuerpo', 'fecha'],
                $rows->map(static fn (WhatsappMensaje $m) => [
                    $m->id,
                    $m->direccion,
                    $m->telefono,
                    $m->tipo,
                    $m->estado,
                    mb_strimwidth((string) $m->cuerpo, 0, 40, '…'),
                    (string) $m->created_at,
                ])->all()
            );
        }

        $waba = (string) config('whatsapp.business_account_id');
        $token = (string) config('whatsapp.token');
        if ($waba === '' || $token === '') {
            $this->warn('Sin WABA/token: no se consultan plantillas.');

            return self::SUCCESS;
        }

        $this->info('Plantillas Meta');
        $version = (string) config('whatsapp.api_version', 'v25.0');
        $response = Http::withToken($token)
            ->timeout((int) config('whatsapp.timeout', 30))
            ->get("https://graph.facebook.com/{$version}/{$waba}/message_templates", [
                'fields' => 'name,status,language,category,rejected_reason',
                'limit' => 20,
            ]);

        if (! $response->successful()) {
            $this->error('Error al listar plantillas: '.$response->body());

            return self::FAILURE;
        }

        $templates = collect($response->json('data', []));
        if ($templates->isEmpty()) {
            $this->line('(sin plantillas)');
        } else {
            $this->table(
                ['name', 'status', 'lang', 'category', 'rejected'],
                $templates->map(static fn (array $t) => [
                    $t['name'] ?? '-',
                    $t['status'] ?? '-',
                    $t['language'] ?? '-',
                    $t['category'] ?? '-',
                    $t['rejected_reason'] ?? '-',
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
