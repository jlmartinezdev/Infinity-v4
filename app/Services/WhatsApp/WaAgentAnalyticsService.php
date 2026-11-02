<?php

namespace App\Services\WhatsApp;

use App\Models\Plan;
use App\Models\WaAgentEvento;
use App\Models\WaAgentUsoModelo;
use App\Models\WhatsappMensaje;
use Illuminate\Support\Facades\Log;

/**
 * Event log + uso de modelo para el panel KPI del agente.
 */
class WaAgentAnalyticsService
{
    public function esSandbox(?string $telefono, ?string $contextoTipo = null): bool
    {
        if ($contextoTipo === WhatsAppAgentService::CONTEXTO_TEST
            || $contextoTipo === WhatsappMensaje::CONTEXTO_TEST_N8N) {
            return true;
        }

        return WhatsAppAgentService::esTelefonoTest($telefono);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function registrarEvento(
        string $tipo,
        ?string $telefono = null,
        ?string $modo = null,
        array $meta = [],
        ?int $mensajeId = null,
        ?int $clienteId = null,
        ?int $ticketId = null,
        ?int $pedidoId = null,
        ?int $cobroId = null,
        ?int $latencyMs = null,
        ?\DateTimeInterface $ocurridoAt = null,
        ?bool $sandbox = null,
    ): ?WaAgentEvento {
        try {
            $sandbox ??= $this->esSandbox($telefono);

            return WaAgentEvento::query()->create([
                'ocurrido_at' => $ocurridoAt ?? now(),
                'telefono' => $telefono ? (preg_replace('/\D+/', '', $telefono) ?: $telefono) : null,
                'cliente_id' => $clienteId,
                'mensaje_id' => $mensajeId,
                'ticket_id' => $ticketId,
                'pedido_id' => $pedidoId,
                'cobro_id' => $cobroId,
                'tipo' => $tipo,
                'modo' => $modo,
                'latency_ms' => $latencyMs,
                'sandbox' => $sandbox,
                'meta' => $meta !== [] ? $meta : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[wa-kpi] evento', ['tipo' => $tipo, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $consulta  wa_agent audit payload-ish
     */
    public function desdeAuditoriaAgente(WhatsappMensaje $mensaje, array $consulta, ?string $reply, bool $escalate, ?string $error, ?int $ticketId): void
    {
        $tel = (string) $mensaje->telefono;
        $sandbox = $this->esSandbox($tel, (string) $mensaje->contexto_tipo);
        $modo = (string) ($consulta['modo'] ?? 'n8n');
        if ($modo === '') {
            $modo = 'n8n';
        }
        $clienteId = isset($consulta['cliente_id']) ? (int) $consulta['cliente_id'] : ($mensaje->cliente_id ? (int) $mensaje->cliente_id : null);
        $latency = (int) ($consulta['n8n_latency_ms'] ?? 0);
        $ocurrido = $mensaje->created_at ?? now();

        $this->registrarEvento(
            WaAgentEvento::TIPO_RESPUESTA,
            $tel,
            $modo,
            [
                'escalate' => $escalate,
                'error' => $error,
                'reply_len' => mb_strlen((string) $reply),
            ],
            $mensaje->id ? (int) $mensaje->id : null,
            $clienteId,
            $ticketId,
            null,
            null,
            $latency > 0 ? $latency : null,
            $ocurrido,
            $sandbox,
        );

        if ($error) {
            $this->registrarEvento(
                WaAgentEvento::TIPO_ERROR,
                $tel,
                $modo,
                ['error' => $error],
                $mensaje->id ? (int) $mensaje->id : null,
                $clienteId,
                $ticketId,
                null,
                null,
                null,
                $ocurrido,
                $sandbox,
            );
        }

        if ($ticketId) {
            $this->registrarEvento(
                WaAgentEvento::TIPO_ESCALADO_TICKET,
                $tel,
                $modo,
                ['motivo' => $consulta['motivo_escalado'] ?? null],
                $mensaje->id ? (int) $mensaje->id : null,
                $clienteId,
                $ticketId,
                null,
                null,
                null,
                $ocurrido,
                $sandbox,
            );
        }

        if ($modo === 'soporte_comprobante' || str_contains($modo, 'comprobante')) {
            $this->registrarEvento(
                WaAgentEvento::TIPO_COMPROBANTE,
                $tel,
                $modo,
                [],
                $mensaje->id ? (int) $mensaje->id : null,
                $clienteId,
                $ticketId,
                null,
                null,
                null,
                $ocurrido,
                $sandbox,
            );
        }

        if (! $escalate && ! $ticketId && in_array($modo, ['soporte_resuelto', 'soporte_wifi', 'soporte_impresora'], true)) {
            $this->registrarEvento(
                WaAgentEvento::TIPO_RESUELTO_AUTO,
                $tel,
                $modo,
                [],
                $mensaje->id ? (int) $mensaje->id : null,
                $clienteId,
                null,
                null,
                null,
                null,
                $ocurrido,
                $sandbox,
            );
        }

        // Cobertura / instalación flags en payload → pista de venta futura
        if (data_get($mensaje->payload, 'cobertura_aprobada') || data_get($consulta, 'cobertura_aprobada')) {
            $this->registrarEvento(
                WaAgentEvento::TIPO_COBERTURA,
                $tel,
                $modo,
                [],
                $mensaje->id ? (int) $mensaje->id : null,
                $clienteId,
                null,
                null,
                null,
                null,
                $ocurrido,
                $sandbox,
            );
        }
        if (data_get($mensaje->payload, 'parece_instalacion') || data_get($consulta, 'parece_instalacion')) {
            $this->registrarEvento(
                WaAgentEvento::TIPO_INSTALACION,
                $tel,
                $modo,
                [],
                $mensaje->id ? (int) $mensaje->id : null,
                $clienteId,
                null,
                null,
                null,
                null,
                $ocurrido,
                $sandbox,
            );
        }

        if ($latency > 0 && str_starts_with($modo, 'soporte_') === false) {
            $this->registrarUsoModelo(
                'n8n',
                (string) config('whatsapp.agent.gemini_model', 'n8n'),
                0,
                0,
                1,
                (float) config('whatsapp.agent.kpi.n8n_usd_por_llamada', 0.002),
                $mensaje->id ? (int) $mensaje->id : null,
                $sandbox,
                ['latency_ms' => $latency],
            );
        }

        if (! $sandbox && (bool) config('whatsapp.agent.kpi.csat_enabled', true) && $modo === 'soporte_resuelto' && filled($reply)) {
            $this->programarCsatPendiente($tel, $clienteId, $mensaje->id ? (int) $mensaje->id : null);
        }
    }

    public function registrarUsoModelo(
        string $proveedor,
        ?string $modelo,
        int $tokensIn,
        int $tokensOut,
        int $llamadas = 1,
        ?float $costoUsd = null,
        ?int $mensajeId = null,
        bool $sandbox = false,
        array $meta = [],
        ?\DateTimeInterface $ocurridoAt = null,
    ): ?WaAgentUsoModelo {
        try {
            if ($costoUsd === null) {
                $costoUsd = $this->estimarCostoUsd($proveedor, $tokensIn, $tokensOut, $llamadas);
            }

            return WaAgentUsoModelo::query()->create([
                'ocurrido_at' => $ocurridoAt ?? now(),
                'proveedor' => $proveedor,
                'modelo' => $modelo,
                'tokens_in' => max(0, $tokensIn),
                'tokens_out' => max(0, $tokensOut),
                'llamadas' => max(1, $llamadas),
                'costo_usd_estimado' => round($costoUsd, 6),
                'mensaje_id' => $mensajeId,
                'sandbox' => $sandbox,
                'meta' => $meta !== [] ? $meta : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[wa-kpi] uso_modelo', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function estimarCostoUsd(string $proveedor, int $tokensIn, int $tokensOut, int $llamadas = 1): float
    {
        if ($proveedor === 'n8n') {
            return (float) config('whatsapp.agent.kpi.n8n_usd_por_llamada', 0.002) * max(1, $llamadas);
        }
        $in = (float) config('whatsapp.agent.kpi.gemini_usd_por_1m_in', 0.10);
        $out = (float) config('whatsapp.agent.kpi.gemini_usd_por_1m_out', 0.40);

        return ($tokensIn / 1_000_000) * $in + ($tokensOut / 1_000_000) * $out;
    }

    public function registrarVision(?int $mensajeId, ?string $telefono, string $modelo, ?int $tokensIn = null, ?int $tokensOut = null): void
    {
        $est = (int) config('whatsapp.agent.kpi.vision_tokens_estimados', 1200);
        $tin = $tokensIn ?? $est;
        $tout = $tokensOut ?? (int) round($est * 0.15);
        $this->registrarUsoModelo(
            'gemini',
            $modelo,
            $tin,
            $tout,
            1,
            null,
            $mensajeId,
            $this->esSandbox($telefono),
            ['uso' => 'vision'],
        );
    }

    /**
     * @param  list<string>  $tiposSenal
     */
    public function haySenalVentaReciente(string $telefono, ?\DateTimeInterface $antesDe = null): bool
    {
        $tel = preg_replace('/\D+/', '', $telefono) ?: $telefono;
        $horas = (int) config('whatsapp.agent.kpi.venta_ventana_horas', 72);
        $desde = now()->subHours($horas);
        $q = WaAgentEvento::query()
            ->where('sandbox', false)
            ->where('telefono', $tel)
            ->whereIn('tipo', [
                WaAgentEvento::TIPO_COBERTURA,
                WaAgentEvento::TIPO_INSTALACION,
                WaAgentEvento::TIPO_OFERTA_PLAN,
            ])
            ->where('ocurrido_at', '>=', $desde);
        if ($antesDe) {
            $q->where('ocurrido_at', '<=', $antesDe);
        }

        return $q->exists();
    }

    public function atribuirVentaPedido(string $telefono, int $pedidoId, ?int $clienteId, ?int $planId, ?float $montoPlan): ?WaAgentEvento
    {
        if ($this->esSandbox($telefono) || ! $this->haySenalVentaReciente($telefono)) {
            return null;
        }
        $margenMeses = (float) config('whatsapp.agent.kpi.margen_meses_plan', 1);
        $monto = $montoPlan !== null ? round($montoPlan * $margenMeses, 2) : null;

        return $this->registrarEvento(
            WaAgentEvento::TIPO_VENTA_ATRIBUIDA,
            $telefono,
            'venta_asistida',
            [
                'canal' => 'pedido',
                'plan_id' => $planId,
                'monto' => $monto,
                'margen_meses' => $margenMeses,
            ],
            null,
            $clienteId,
            null,
            $pedidoId,
            null,
        );
    }

    public function atribuirVentaCobro(string $telefono, int $cobroId, ?int $clienteId, float $monto): ?WaAgentEvento
    {
        // Cobros no son "venta de plan"; se atribuyen solo si hubo comprobante agente reciente.
        $tel = preg_replace('/\D+/', '', $telefono) ?: $telefono;
        $horas = (int) config('whatsapp.agent.kpi.venta_ventana_horas', 72);
        $hubo = WaAgentEvento::query()
            ->where('sandbox', false)
            ->where('telefono', $tel)
            ->where('tipo', WaAgentEvento::TIPO_COMPROBANTE)
            ->where('ocurrido_at', '>=', now()->subHours($horas))
            ->exists();
        if (! $hubo || $this->esSandbox($telefono)) {
            return null;
        }

        return $this->registrarEvento(
            WaAgentEvento::TIPO_VENTA_ATRIBUIDA,
            $telefono,
            'cobro_asistido',
            ['canal' => 'cobro', 'monto' => $monto],
            null,
            $clienteId,
            null,
            null,
            $cobroId,
        );
    }

    public function programarCsatPendiente(string $telefono, ?int $clienteId, ?int $mensajeId): void
    {
        $tel = preg_replace('/\D+/', '', $telefono) ?: $telefono;
        cache()->put(
            $this->csatCacheKey($tel),
            ['cliente_id' => $clienteId, 'mensaje_id' => $mensajeId, 'at' => now()->timestamp],
            now()->addHours(48)
        );
    }

    public function csatCacheKey(string $telefono): string
    {
        $tel = preg_replace('/\D+/', '', $telefono) ?: $telefono;

        return 'wa_agent.csat_pendiente.'.$tel;
    }

    /**
     * Si el mensaje es solo 1–5 y hay encuesta pendiente, registra CSAT.
     */
    public function capturarCsatSiCorresponde(WhatsappMensaje $mensaje): bool
    {
        if ($mensaje->tipo !== 'text' || ! $mensaje->esEntrada()) {
            return false;
        }
        $cuerpo = trim((string) $mensaje->cuerpo);
        if (! preg_match('/^[1-5]$/', $cuerpo)) {
            return false;
        }
        $tel = preg_replace('/\D+/', '', (string) $mensaje->telefono) ?: (string) $mensaje->telefono;
        if ($this->esSandbox($tel, (string) $mensaje->contexto_tipo)) {
            return false;
        }
        $pend = cache()->pull($this->csatCacheKey($tel));
        if (! is_array($pend)) {
            return false;
        }
        $score = (int) $cuerpo;
        $this->registrarEvento(
            WaAgentEvento::TIPO_CSAT,
            $tel,
            'csat',
            ['score' => $score],
            $mensaje->id ? (int) $mensaje->id : null,
            isset($pend['cliente_id']) ? (int) $pend['cliente_id'] : ($mensaje->cliente_id ? (int) $mensaje->cliente_id : null),
        );

        return true;
    }

    public function enviarPreguntaCsat(string $telefono): void
    {
        if (! (bool) config('whatsapp.agent.kpi.csat_enabled', true)) {
            return;
        }
        if ($this->esSandbox($telefono)) {
            return;
        }
        if (! (bool) config('whatsapp.agent.auto_send', false)) {
            return;
        }
        try {
            $wa = app(WhatsAppService::class);
            if (! $wa->isConfigured()) {
                return;
            }
            $texto = (string) config('whatsapp.agent.kpi.csat_pregunta');
            $wa->sendText($telefono, $texto, [
                'contexto_tipo' => 'wa_agent_csat',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[wa-kpi] csat send', ['error' => $e->getMessage()]);
        }
    }

    public function montoPlan(?int $planId): ?float
    {
        if (! $planId) {
            return null;
        }
        $plan = Plan::query()->find($planId);

        return $plan ? (float) ($plan->precio ?? $plan->monto ?? 0) : null;
    }
}
