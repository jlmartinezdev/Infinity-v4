<?php

namespace App\Console\Commands;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\ClientePorTelefonoService;
use App\Services\WhatsApp\WhatsAppAgentService;
use Illuminate\Console\Command;

class WhatsAppProbarAgenteCommand extends Command
{
    protected $signature = 'whatsapp:probar-agente
                            {--wa-id=595981234567 : Teléfono formato Meta}
                            {--mensaje=Hola, cuanto sale el plan de 20 megas?}
                            {--nombre=Juan}
                            {--lookup : Solo GET por-telefono local, sin llamar N8N}
                            {--enviar : Enviar el reply por WhatsApp (ventana 24h)}';

    protected $description = 'Prueba el plugin N8N (lookup local y/o POST al webhook del agente)';

    public function handle(WhatsAppAgentService $agent, ClientePorTelefonoService $lookup): int
    {
        $waId = (string) $this->option('wa-id');
        $this->table(['clave', 'valor'], [
            ['enabled', config('whatsapp.agent.enabled') ? 'true' : 'false'],
            ['url', (string) config('whatsapp.agent.url')],
            ['secret', filled(config('whatsapp.agent.secret')) ? '***' : '(vacío)'],
            ['timeout_ms', (string) config('whatsapp.agent.timeout_ms')],
        ]);

        $this->info('Lookup local por-telefono');
        $data = $lookup->buscar($waId);
        $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');

        if ($this->option('lookup')) {
            return self::SUCCESS;
        }

        $mensaje = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'telefono' => $waId,
            'contacto_nombre' => (string) $this->option('nombre'),
            'tipo' => 'text',
            'cuerpo' => (string) $this->option('mensaje'),
            'wamid' => 'test-cli-'.now()->timestamp,
            'estado' => WhatsappMensaje::ESTADO_RECIBIDO,
            'cliente_id' => ($data['encontrado'] ?? false) ? ($data['cliente_id'] ?? null) : null,
            'payload' => ['timestamp' => time(), 'origen' => 'artisan'],
        ]);
        $mensaje->save();

        $this->info('POST a N8N (mensaje #'.$mensaje->id.')');
        $resultado = $agent->procesar($mensaje, (bool) $this->option('enviar'));
        $this->line(json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');

        if (! empty($resultado['error'])) {
            $this->warn('N8N no respondió bien: '.$resultado['error']);
            $this->line('Si el workflow no está activo, es esperado. Revisá importar/activar + secret + login.');

            return self::FAILURE;
        }

        $this->info('OK — reply recibido'.($this->option('enviar') ? ' y envío WhatsApp disparado' : ' (no se envió a WhatsApp)'));

        return self::SUCCESS;
    }
}
