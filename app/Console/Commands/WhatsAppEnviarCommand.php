<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppEnviarCommand extends Command
{
    protected $signature = 'whatsapp:enviar
                            {telefono : Número destino (ej. 0981123456)}
                            {--texto= : Texto libre (solo ventana 24h)}
                            {--plantilla= : Nombre de plantilla aprobada}
                            {--lang= : Idioma de plantilla (default config)}
                            {--param=* : Parámetros de body de plantilla (texto)}';

    protected $description = 'Envía un mensaje WhatsApp (texto o plantilla) vía Meta Cloud API';

    public function handle(WhatsAppService $whatsapp): int
    {
        if (! $whatsapp->isConfigured()) {
            $this->error('WhatsApp no configurado (WHATSAPP_ENABLED / TOKEN / PHONE_NUMBER_ID).');

            return self::FAILURE;
        }

        $telefono = (string) $this->argument('telefono');
        $texto = $this->option('texto');
        $plantilla = $this->option('plantilla');

        if (! $texto && ! $plantilla) {
            $this->error('Indicá --texto=... o --plantilla=...');

            return self::FAILURE;
        }

        if ($plantilla) {
            $params = array_map(
                static fn (string $p) => ['type' => 'text', 'text' => $p],
                $this->option('param') ?: []
            );
            $mensaje = $whatsapp->sendTemplate(
                $telefono,
                (string) $plantilla,
                $this->option('lang') ?: null,
                $params,
            );
        } else {
            $mensaje = $whatsapp->sendText($telefono, (string) $texto);
        }

        $this->table(
            ['id', 'telefono', 'tipo', 'estado', 'wamid', 'error'],
            [[
                $mensaje->id,
                $mensaje->telefono,
                $mensaje->tipo,
                $mensaje->estado,
                $mensaje->wamid ?? '-',
                $mensaje->error_message ?? '-',
            ]]
        );

        return $mensaje->estado === 'fallido' ? self::FAILURE : self::SUCCESS;
    }
}
