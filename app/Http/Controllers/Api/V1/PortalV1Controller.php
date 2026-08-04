<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Portal\PortalAppConfigService;
use App\Services\Portal\PortalFeatureFlagsService;
use App\Services\Portal\PortalInsightsService;
use App\Services\Portal\PortalReferidosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints portal v1 (Home Interplus Clientes 3.2).
 * Prefijo: /api/v1/portal/v1/*
 */
class PortalV1Controller extends ApiController
{
    public function __construct(
        private readonly PortalFeatureFlagsService $flags,
        private readonly PortalInsightsService $insights,
        private readonly PortalReferidosService $referidos,
        private readonly PortalAppConfigService $appConfig,
    ) {}

    public function featureFlags(): JsonResponse
    {
        return $this->ok([
            'flags' => $this->flags->flags(),
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        return $this->ok($this->insights->forCliente($cliente));
    }

    public function referidos(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        return $this->ok($this->referidos->resumen($cliente));
    }

    public function referidosCanjear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:32'],
        ]);

        $cliente = $request->user()->cliente()->firstOrFail();

        try {
            $result = $this->referidos->canjear($cliente, $validated['codigo']);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Código inválido.';

            return $this->fail((string) $msg, 422, $e->errors());
        }

        return $this->ok($result, 'Código de referido registrado.');
    }

    public function pagoOnline(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        $pago = $this->appConfig->pagoOnline();
        $template = trim((string) ($pago['checkout_url'] ?? ''));
        $provider = (string) ($pago['provider'] ?? 'bancard');

        $url = null;
        if ($template !== '' && str_starts_with($template, 'http')) {
            $token = $request->bearerToken() ?? '';
            $url = str_replace(
                ['{cliente_id}', '{cedula}', '{token}'],
                [(string) $cliente->cliente_id, (string) ($cliente->cedula ?? ''), $token],
                $template
            );
        }

        return $this->ok([
            'checkout_url' => $url,
            'url' => $url,
            'payment_url' => $url,
            'provider' => $provider,
        ]);
    }

    public function faqs(Request $request): JsonResponse
    {
        $topics = $this->appConfig->faqsTopics();
        $filter = trim((string) $request->query('topic', ''));

        if ($filter !== '') {
            $topics = array_values(array_filter(
                $topics,
                fn ($t) => ($t['topic'] ?? '') === $filter
            ));
        }

        foreach ($topics as &$topic) {
            $items = $topic['items'] ?? [];
            usort($items, fn ($a, $b) => ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0)));
            $topic['items'] = array_values($items);
        }
        unset($topic);

        return $this->ok([
            'topics' => array_values($topics),
            'updated_at' => now()->utc()->toIso8601String(),
        ]);
    }
}
