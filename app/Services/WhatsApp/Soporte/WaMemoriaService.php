<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\FacturacionParametro;
use App\Models\WaMemoria;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaMemoriaService
{
    public const CLAVE_TRANSFERENCIA = 'wa_canned_transferencia';

    public const CLAVE_BENEFICIOS = 'wa_canned_beneficios';

    public const CLAVE_CONDICIONES = 'wa_canned_condiciones';

    /**
     * @return array{beneficios: string, transferencia: string, condiciones: string}
     */
    public function cannedEfectivo(): array
    {
        $base = [
            'beneficios' => trim((string) config('whatsapp.agent.canned.beneficios', '')),
            'transferencia' => trim((string) config('whatsapp.agent.canned.transferencia', '')),
            'condiciones' => trim((string) config('whatsapp.agent.canned.condiciones', '')),
        ];

        foreach ([
            'beneficios' => self::CLAVE_BENEFICIOS,
            'transferencia' => self::CLAVE_TRANSFERENCIA,
            'condiciones' => self::CLAVE_CONDICIONES,
        ] as $k => $clave) {
            try {
                $override = trim((string) FacturacionParametro::obtener($clave, ''));
            } catch (\Throwable) {
                $override = '';
            }
            if ($override !== '') {
                $base[$k] = $override;
            }
        }

        return $base;
    }

    public function guardarCanned(string $clave, string $texto): void
    {
        $map = [
            'transferencia' => [self::CLAVE_TRANSFERENCIA, 'WA canned transferencia (editable panel)'],
            'beneficios' => [self::CLAVE_BENEFICIOS, 'WA canned beneficios (editable panel)'],
            'condiciones' => [self::CLAVE_CONDICIONES, 'WA canned condiciones (editable panel)'],
        ];
        if (! isset($map[$clave])) {
            throw new \InvalidArgumentException('canned_invalido');
        }
        FacturacionParametro::establecer($map[$clave][0], trim($texto), $map[$clave][1]);
    }

    /**
     * Bloque de memoria para el LLM (docs activos + match por palabras del mensaje).
     */
    public function textoParaAgente(?string $mensaje = null, int $maxChars = 4500): string
    {
        try {
            $rows = WaMemoria::query()
                ->where('activo', true)
                ->orderByDesc('updated_at')
                ->limit(40)
                ->get();
        } catch (\Throwable) {
            return '';
        }

        if ($rows->isEmpty()) {
            return '';
        }

        $msg = mb_strtolower(trim((string) $mensaje));
        $priorizados = [];
        $resto = [];
        foreach ($rows as $m) {
            $hit = false;
            if ($msg !== '') {
                $pals = array_filter(array_map('trim', explode(',', mb_strtolower((string) $m->palabras))));
                foreach ($pals as $p) {
                    if ($p !== '' && str_contains($msg, $p)) {
                        $hit = true;
                        break;
                    }
                }
                if (! $hit && $m->tipo !== 'instruccion' && str_contains($msg, mb_strtolower((string) $m->tipo))) {
                    $hit = true;
                }
            }
            if ($hit) {
                $priorizados[] = $m;
            } else {
                $resto[] = $m;
            }
        }

        $orden = array_merge($priorizados, $resto);
        $bloques = [];
        $len = 0;
        foreach ($orden as $m) {
            $pieza = '['.$m->tipo.'] '.$m->titulo."\n".trim((string) $m->cuerpo);
            $add = mb_strlen($pieza) + 2;
            if ($len + $add > $maxChars) {
                break;
            }
            $bloques[] = $pieza;
            $len += $add;
            try {
                $m->forceFill(['consumido_at' => now()])->saveQuietly();
            } catch (\Throwable) {
            }
        }

        return implode("\n\n---\n\n", $bloques);
    }

    /**
     * Reescribe un documento de memoria con Gemini (borrador mejorado).
     */
    public function mejorarConIa(WaMemoria $memoria): string
    {
        $apiKey = trim((string) (
            config('whatsapp.agent.gemini_api_key')
            ?: env('WA_AGENT_GEMINI_API_KEY', '')
        ));
        if ($apiKey === '') {
            throw new \RuntimeException('gemini_api_key_vacia');
        }
        $modelo = trim((string) (config('whatsapp.agent.gemini_model') ?: env('WA_AGENT_GEMINI_MODEL', 'gemini-3.6-flash')));
        $modelo = str_starts_with($modelo, 'models/') ? substr($modelo, 7) : $modelo;

        $system = 'Sos editor de instrucciones internas para el agente WhatsApp Interplus (Paraguay). '
            .'Mejorá claridad, trato de usted, hechos concretos. No inventes cuentas bancarias ni precios. '
            .'Respondé SOLO el texto mejorado, sin markdown de explicación.';
        $user = "Tipo: {$memoria->tipo}\nTítulo: {$memoria->titulo}\n\nTexto actual:\n".$memoria->cuerpo;

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .$modelo.':generateContent?key='.urlencode($apiKey);
        $resp = Http::timeout(45)->post($url, [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 2048],
        ]);
        if (! $resp->successful()) {
            Log::notice('[WA memoria] Gemini HTTP '.$resp->status(), ['body' => mb_substr($resp->body(), 0, 300)]);
            throw new \RuntimeException('gemini_http_'.$resp->status());
        }
        $parts = data_get($resp->json(), 'candidates.0.content.parts', []);
        $texts = [];
        foreach ($parts as $p) {
            if (is_array($p) && isset($p['text'])) {
                $texts[] = (string) $p['text'];
            }
        }
        $out = trim(implode("\n", $texts));
        if ($out === '') {
            throw new \RuntimeException('gemini_sin_texto');
        }

        return $out;
    }
}
