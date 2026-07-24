<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Suscribe la app Meta a la WABA + campos de webhook (incl. ecos de la app móvil).
 */
class WhatsAppSuscribirCommand extends Command
{
    protected $signature = 'whatsapp:suscribir';

    protected $description = 'Suscribe la app a WABA y al webhook smb_message_echoes (respuestas desde el celular)';

    public function handle(): int
    {
        $token = (string) config('whatsapp.token');
        $waba = (string) config('whatsapp.business_account_id');
        $secret = (string) config('whatsapp.app_secret');
        $verify = (string) config('whatsapp.verify_token');
        $version = (string) config('whatsapp.api_version', 'v25.0');
        $base = rtrim((string) config('whatsapp.graph_base_url', 'https://graph.facebook.com'), '/');

        if ($token === '' || $waba === '') {
            $this->error('Falta WHATSAPP_TOKEN o WHATSAPP_BUSINESS_ACCOUNT_ID.');

            return self::FAILURE;
        }

        $callback = (string) config('whatsapp.webhook_url');
        if ($callback === '') {
            // Preferir el callback ya configurado en Meta (evita pisar prod con APP_URL local).
            $phoneId = (string) config('whatsapp.phone_number_id');
            if ($phoneId !== '') {
                $phoneInfo = Http::withToken($token)
                    ->timeout((int) config('whatsapp.timeout', 30))
                    ->get("{$base}/{$version}/{$phoneId}", [
                        'fields' => 'webhook_configuration',
                    ]);
                $callback = (string) data_get($phoneInfo->json(), 'webhook_configuration.application', '');
            }
        }
        if ($callback === '') {
            $callback = rtrim((string) config('app.url'), '/').'/api/v1/webhooks/whatsapp';
        }

        // 1) WABA → apps suscritas (messages + ecos)
        $wabaFields = ['messages', 'smb_message_echoes', 'history', 'smb_app_state_sync'];
        $subscribe = Http::withToken($token)
            ->timeout((int) config('whatsapp.timeout', 30))
            ->asJson()
            ->post("{$base}/{$version}/{$waba}/subscribed_apps", [
                'subscribed_fields' => $wabaFields,
            ]);

        if (! $subscribe->successful()) {
            $this->warn('Suscripción WABA completa falló: '.$subscribe->body());
            $subscribe = Http::withToken($token)
                ->timeout((int) config('whatsapp.timeout', 30))
                ->asJson()
                ->post("{$base}/{$version}/{$waba}/subscribed_apps", [
                    'subscribed_fields' => ['messages'],
                ]);

            if (! $subscribe->successful()) {
                $this->error('No se pudo suscribir WABA: '.$subscribe->body());

                return self::FAILURE;
            }

            $this->warn('WABA suscripta solo a "messages".');
        } else {
            $this->info('WABA OK: '.implode(', ', $wabaFields));
        }

        $list = Http::withToken($token)
            ->timeout((int) config('whatsapp.timeout', 30))
            ->get("{$base}/{$version}/{$waba}/subscribed_apps");

        $apps = collect($list->json('data', []))->map(static function (array $row) {
            $app = $row['whatsapp_business_api_data'] ?? [];

            return [
                $app['id'] ?? '-',
                $app['name'] ?? '-',
            ];
        })->all();

        $this->table(['app_id', 'name'], $apps);

        // 2) App Dashboard webhook fields — sin esto Meta NUNCA envía smb_message_echoes
        $appId = (string) (
            config('whatsapp.app_id')
            ?: collect($apps)->first(fn ($row) => ($row[1] ?? '') !== 'Business Agent')[0]
            ?? ''
        );

        if ($appId === '' || $appId === '-') {
            $this->warn('No se pudo determinar APP_ID; configurá WHATSAPP_APP_ID.');

            return self::SUCCESS;
        }

        if ($secret === '') {
            $this->warn('Falta WHATSAPP_APP_SECRET: no se pueden suscribir campos del webhook de la app.');
            $this->line('En Meta Developers → App → WhatsApp → Configuration → Webhook, suscribí: smb_message_echoes');

            return self::SUCCESS;
        }

        $appToken = "{$appId}|{$secret}";
        $webhookFields = 'messages,message_template_status_update,smb_message_echoes,history,smb_app_state_sync,account_update';

        $before = Http::withToken($appToken)
            ->timeout((int) config('whatsapp.timeout', 30))
            ->get("{$base}/{$version}/{$appId}/subscriptions");

        $fieldsBefore = collect($before->json('data.0.fields', []))->pluck('name')->all();
        $this->line('Webhook fields antes: '.($fieldsBefore === [] ? '(ninguno / error)' : implode(', ', $fieldsBefore)));

        $hook = Http::asForm()
            ->timeout((int) config('whatsapp.timeout', 30))
            ->post("{$base}/{$version}/{$appId}/subscriptions", [
                'object' => 'whatsapp_business_account',
                'callback_url' => $callback,
                'fields' => $webhookFields,
                'verify_token' => $verify !== '' ? $verify : 'infinity-wa-verify',
                'access_token' => $appToken,
            ]);

        if (! $hook->successful()) {
            $this->error('No se pudieron suscribir campos del webhook: '.$hook->body());
            $this->line("Callback usado: {$callback}");
            $this->line('Revisá en Meta Developers que el callback coincida y esté verificado.');

            return self::FAILURE;
        }

        $after = Http::withToken($appToken)
            ->timeout((int) config('whatsapp.timeout', 30))
            ->get("{$base}/{$version}/{$appId}/subscriptions");

        $fieldsAfter = collect($after->json('data.0.fields', []))->pluck('name')->all();
        $this->info('Webhook fields ahora: '.implode(', ', $fieldsAfter));

        if (! in_array('smb_message_echoes', $fieldsAfter, true)) {
            $this->error('smb_message_echoes NO quedó suscripto. Los mensajes desde el celular seguirán sin aparecer.');

            return self::FAILURE;
        }

        $this->info('Listo. Respondé un mensaje desde WhatsApp Business en el celular: debería llegar a /whatsapp/mensajes con etiqueta «Desde app WhatsApp».');

        return self::SUCCESS;
    }
}
