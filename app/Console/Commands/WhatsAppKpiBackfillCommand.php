<?php

namespace App\Console\Commands;

use App\Models\WaAgentEvento;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WaAgentAnalyticsService;
use App\Services\WhatsApp\WhatsAppAgentService;
use Illuminate\Console\Command;

class WhatsAppKpiBackfillCommand extends Command
{
    protected $signature = 'whatsapp:kpi-backfill
                            {--limite=5000 : Máximo de mensajes a revisar}
                            {--dry-run : Solo cuenta, no escribe}';

    protected $description = 'Backfill de wa_agent_eventos desde payload.wa_agent (sin sandbox)';

    public function handle(WaAgentAnalyticsService $analytics): int
    {
        $limite = max(100, (int) $this->option('limite'));
        $dry = (bool) $this->option('dry-run');

        $ya = WaAgentEvento::query()
            ->where('tipo', WaAgentEvento::TIPO_RESPUESTA)
            ->whereNotNull('mensaje_id')
            ->pluck('mensaje_id')
            ->flip();

        // IDs recientes con wa_agent en payload (MySQL JSON / LIKE).
        $ids = WhatsappMensaje::query()
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->where('telefono', 'not like', '595000%')
            ->where(function ($q) {
                $q->whereNull('contexto_tipo')
                    ->orWhere('contexto_tipo', '!=', WhatsappMensaje::CONTEXTO_TEST_N8N);
            })
            ->where(function ($q) {
                $q->where('payload', 'like', '%"wa_agent"%')
                    ->orWhere('payload', 'like', '%wa_agent%');
            })
            ->orderByDesc('id')
            ->limit($limite)
            ->pluck('id');

        $procesados = 0;
        $omitidos = 0;
        $escritos = 0;

        foreach ($ids->chunk(200) as $chunkIds) {
            $mensajes = WhatsappMensaje::query()
                ->whereIn('id', $chunkIds->all())
                ->get();

            foreach ($mensajes as $mensaje) {
                $procesados++;
                $wa = data_get($mensaje->payload, 'wa_agent');
                if (! is_array($wa) || $wa === []) {
                    $omitidos++;

                    continue;
                }
                if (WhatsAppAgentService::esTelefonoTest((string) $mensaje->telefono)) {
                    $omitidos++;

                    continue;
                }
                if ($ya->has($mensaje->id)) {
                    $omitidos++;

                    continue;
                }

                $consulta = [
                    'modo' => $wa['modo'] ?? 'n8n',
                    'cliente_id' => $wa['cliente_id'] ?? $mensaje->cliente_id,
                    'motivo_escalado' => $wa['motivo_escalado'] ?? null,
                    'n8n_latency_ms' => $wa['n8n_latency_ms'] ?? 0,
                    'cobertura_aprobada' => data_get($mensaje->payload, 'cobertura_aprobada'),
                    'parece_instalacion' => data_get($mensaje->payload, 'parece_instalacion'),
                ];
                $reply = is_string($wa['reply'] ?? null) ? $wa['reply'] : null;
                $escalate = (bool) ($wa['escalate'] ?? false);
                $error = is_string($wa['error'] ?? null) ? $wa['error'] : null;
                $ticketId = isset($wa['ticket_id']) ? (int) $wa['ticket_id'] : null;
                if ($ticketId === 0) {
                    $ticketId = null;
                }

                if ($dry) {
                    $escritos++;

                    continue;
                }

                $prevCsat = config('whatsapp.agent.kpi.csat_enabled');
                config(['whatsapp.agent.kpi.csat_enabled' => false]);
                try {
                    $analytics->desdeAuditoriaAgente($mensaje, $consulta, $reply, $escalate, $error, $ticketId);
                    $escritos++;
                    $ya[$mensaje->id] = true;
                } finally {
                    config(['whatsapp.agent.kpi.csat_enabled' => $prevCsat]);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Candidatos: {$ids->count()}. Revisados: {$procesados}. Escritura: {$escritos}. Omitidos: {$omitidos}.");

        return self::SUCCESS;
    }
}
