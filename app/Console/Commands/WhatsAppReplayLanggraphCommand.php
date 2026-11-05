<?php

namespace App\Console\Commands;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\Soporte\SoporteTexto;
use App\Services\WhatsApp\WhatsAppAgentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Replay de mensajes reales contra LangGraph (sin Meta).
 */
class WhatsAppReplayLanggraphCommand extends Command
{
    protected $signature = 'whatsapp:replay-langgraph
                            {--meses=5 : Meses hacia atrás}
                            {--muestra=80 : Turnos a evaluar}
                            {--out= : JSON de salida}';

    protected $description = 'Replay corpus histórico contra LangGraph; no envía a clientes';

    public function handle(WhatsAppAgentService $agent): int
    {
        $url = (string) config('whatsapp.agent.langgraph_url');
        if ($url === '') {
            $this->error('WA_AGENT_LANGGRAPH_URL vacío');

            return self::FAILURE;
        }

        $meses = max(1, (int) $this->option('meses'));
        $muestra = max(10, (int) $this->option('muestra'));
        $desde = now()->subMonths($meses)->startOfDay();
        $out = (string) ($this->option('out') ?: storage_path('app/wa-langgraph-replay-'.now()->format('Ymd-His').'.json'));

        $base = WhatsappMensaje::query()
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->where('created_at', '>=', $desde)
            ->where('telefono', 'not like', WhatsappMensaje::TELEFONO_SANDBOX_PREFIX.'%')
            ->where('tipo', 'text')
            ->whereNotNull('cuerpo')
            ->where('cuerpo', '!=', '');

        $ids = (clone $base)->inRandomOrder()->limit($muestra)->pluck('id');
        $mensajes = WhatsappMensaje::query()->whereIn('id', $ids)->orderBy('id')->get();

        $secret = (string) config('whatsapp.agent.secret');
        $toolsBase = rtrim((string) config('app.url'), '/').'/api/v1/wa-agent/tools';
        $timeout = max(5, (int) ceil(((int) config('whatsapp.agent.langgraph_timeout_ms', 35000)) / 1000));

        $okReply = 0;
        $loopId = 0;
        $pagoPegado = 0;
        $latencias = [];
        $fallos = [];
        $buenos = [];
        $n = 0;

        foreach ($mensajes as $m) {
            $texto = trim((string) $m->cuerpo);
            if ($texto === '' || mb_strlen($texto) > 400) {
                continue;
            }
            $n++;
            $sandboxTel = WhatsAppAgentService::telefonoSandbox((string) $m->telefono);
            $body = [
                'wa_id' => $sandboxTel,
                'mensaje' => $texto,
                'mensaje_hilo_reciente' => $texto,
                'historial_texto' => '',
                'sandbox' => true,
                'tools_base_url' => $toolsBase,
                'parece_cedula' => WhatsAppAgentService::textoPareceCedula($texto),
                'parece_pago_texto' => WhatsAppAgentService::textoParecePago($texto),
            ];

            $started = microtime(true);
            try {
                $resp = Http::timeout($timeout)
                    ->withHeaders(array_filter([
                        'X-Interplus-Secret' => $secret !== '' ? $secret : null,
                    ]))
                    ->post($url, $body);
                $json = $resp->json();
            } catch (\Throwable $e) {
                $fallos[] = [
                    'texto' => mb_substr($texto, 0, 160),
                    'error' => $e->getMessage(),
                ];
                continue;
            }
            $ms = (int) round((microtime(true) - $started) * 1000);
            $latencias[] = $ms;
            if (! is_array($json)) {
                $fallos[] = ['texto' => mb_substr($texto, 0, 160), 'error' => 'json_invalido', 'http' => $resp->status()];
                continue;
            }

            if (! $resp->successful()) {
                $fallos[] = [
                    'texto' => mb_substr($texto, 0, 160),
                    'error' => 'http_'.$resp->status(),
                    'detail' => mb_substr((string) ($json['detail'] ?? $resp->body()), 0, 220),
                ];
                continue;
            }

            $reply = trim((string) ($json['reply'] ?? ''));
            if ($reply !== '') {
                $okReply++;
            } else {
                $fallos[] = [
                    'texto' => mb_substr($texto, 0, 160),
                    'error' => 'sin_reply',
                    'intent' => $json['intent'] ?? null,
                ];
            }

            $pideId = (bool) preg_match('/c[eé]dula|whatsapp registrado|nombre completo/iu', $reply);
            $esId = SoporteTexto::esSoloIdentificador($texto)
                || WhatsAppAgentService::textoPareceCedula($texto)
                || (bool) SoporteTexto::telefonoEnTexto($texto);
            $temaCatalogo = SoporteTexto::pareceConsultaCatalogo($texto)
                || (bool) preg_match('/\bplanes?\b/iu', $texto);
            $meta = (bool) preg_match('/por\s*qu[eé]\s+me\s+dijo|sra\.?|se[nñ]ora/iu', $texto);

            if ($pideId && ! $esId && ($temaCatalogo || $meta || preg_match('/qu[eé]\s+plan\s+tengo/iu', $texto))) {
                $loopId++;
                $fallos[] = [
                    'texto' => mb_substr($texto, 0, 160),
                    'reply' => mb_substr($reply, 0, 220),
                    'riesgo' => 'loop_identificacion',
                    'intent' => $json['intent'] ?? null,
                ];
            } elseif ($reply !== '' && count($buenos) < 8) {
                $buenos[] = [
                    'texto' => mb_substr($texto, 0, 120),
                    'intent' => $json['intent'] ?? null,
                    'reply' => mb_substr($reply, 0, 180),
                ];
            }

            if (($json['proceso_activo'] ?? null) === 'pago' && $temaCatalogo && ! $esId) {
                $pagoPegado++;
            }
        }

        sort($latencias);
        $p50 = $latencias !== [] ? $latencias[(int) floor(count($latencias) * 0.5)] : null;
        $p95 = $latencias !== [] ? $latencias[min(count($latencias) - 1, (int) floor(count($latencias) * 0.95))] : null;

        $reporte = [
            'desde' => (string) $desde,
            'n' => $n,
            'pct_reply' => $n > 0 ? round(100 * $okReply / $n, 1) : 0,
            'pct_sin_loop_id' => $n > 0 ? round(100 * (1 - $loopId / $n), 1) : 0,
            'loops_identificacion' => $loopId,
            'pago_pegado_sospecha' => $pagoPegado,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'buenos' => $buenos,
            'fallos' => array_slice($fallos, 0, 25),
        ];

        File::put($out, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Replay n={$n} reply={$reporte['pct_reply']}% sin_loop={$reporte['pct_sin_loop_id']}% p50={$p50}ms → {$out}");

        return self::SUCCESS;
    }
}
