<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FacturaInterna;
use App\Services\Portal\PortalAppConfigService;
use App\Services\Portal\PortalCpeDhcpService;
use App\Services\Portal\PortalFeatureFlagsService;
use App\Services\Portal\PortalInsightsService;
use App\Services\Portal\PortalReferidosService;
use App\Services\Tpago\TpagoClient;
use App\Services\Tpago\TpagoPaymentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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
        private readonly PortalCpeDhcpService $cpeDhcp,
        private readonly TpagoPaymentLinkService $tpagoLinks,
        private readonly TpagoClient $tpago,
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

    /**
     * GET/POST pago online.
     *
     * - Con TPago configurado: genera (o reusa) link por factura.
     * - Body `{ "amount": N }` solo → link de saldo a favor (sin factura).
     * - Query/body: factura_interna_id (opcional; si falta y no hay amount, usa la primera pendiente).
     * - force_new=1 para forzar un link nuevo.
     * - Fallback legacy: template PORTAL_PAGO_ONLINE_CHECKOUT_URL.
     */
    public function pagoOnline(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        $pago = $this->appConfig->pagoOnline();
        $providerCfg = (string) ($pago['provider'] ?? 'bancard');

        if ($this->tpagoLinks->disponible()) {
            try {
                $facturaId = (int) (
                    $request->input('factura_interna_id')
                    ?? $request->query('factura_interna_id')
                    ?? 0
                );
                $amountRaw = $request->input('amount', $request->query('amount'));
                $soloMonto = $facturaId <= 0
                    && $amountRaw !== null
                    && $amountRaw !== '';

                if ($soloMonto) {
                    $amount = (int) round((float) $amountRaw);
                    $result = $this->tpagoLinks->paraMonto(
                        $cliente,
                        $amount,
                        $request->boolean('force_new')
                    );

                    return $this->ok([
                        'checkout_url' => $result['checkout_url'],
                        'url' => $result['checkout_url'],
                        'payment_url' => $result['checkout_url'],
                        'provider' => 'tpago',
                        'factura_interna_id' => null,
                        'amount' => $result['amount'],
                        'link_alias' => $result['link_alias'],
                        'expires_at' => $result['expires_at'],
                        'reused' => $result['reused'],
                        'purpose' => 'saldo_favor',
                    ]);
                }

                $factura = $this->resolverFacturaPago($request, (int) $cliente->cliente_id);
                $result = $this->tpagoLinks->paraFactura(
                    $factura,
                    $request->boolean('force_new')
                );

                return $this->ok([
                    'checkout_url' => $result['checkout_url'],
                    'url' => $result['checkout_url'],
                    'payment_url' => $result['checkout_url'],
                    'provider' => 'tpago',
                    'factura_interna_id' => $factura->id,
                    'amount' => $result['amount'],
                    'link_alias' => $result['link_alias'],
                    'expires_at' => $result['expires_at'],
                    'reused' => $result['reused'],
                    'purpose' => 'factura',
                ]);
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo crear el link de pago.';

                return $this->fail((string) $msg, 422, $e->errors());
            } catch (Throwable $e) {
                return $this->fail(
                    $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Error al generar link TPago.',
                    502
                );
            }
        }

        $template = trim((string) ($pago['checkout_url'] ?? ''));
        $url = null;
        if ($template !== '' && str_starts_with($template, 'http')) {
            $token = $request->bearerToken() ?? '';
            $url = str_replace(
                ['{cliente_id}', '{cedula}', '{token}'],
                [(string) $cliente->cliente_id, (string) ($cliente->cedula ?? ''), $token],
                $template
            );
        }

        $missing = $this->tpago->hasCredentials() ? $this->tpago->missingConfig() : [];

        return $this->ok([
            'checkout_url' => $url,
            'url' => $url,
            'payment_url' => $url,
            'provider' => $providerCfg,
            'tpago_ready' => false,
            'tpago_missing' => $missing,
        ]);
    }

    private function resolverFacturaPago(Request $request, int $clienteId): FacturaInterna
    {
        $facturaId = (int) (
            $request->input('factura_interna_id')
            ?? $request->query('factura_interna_id')
            ?? 0
        );

        if ($facturaId > 0) {
            $factura = FacturaInterna::query()
                ->where('id', $facturaId)
                ->where('cliente_id', $clienteId)
                ->first();

            if (! $factura) {
                throw ValidationException::withMessages([
                    'factura_interna_id' => ['Factura no encontrada.'],
                ]);
            }

            return $factura;
        }

        $factura = $this->tpagoLinks->primeraFacturaPendiente($clienteId);
        if (! $factura) {
            throw ValidationException::withMessages([
                'factura_interna_id' => ['No hay facturas con saldo pendiente.'],
            ]);
        }

        return $factura;
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

    /**
     * GET clientes DHCP del CPE (LAN) del cliente logueado.
     * Soft-fail: 200 + clients [] si no hay antena/IP o SSH falla (app sigue con escaneo local).
     *
     * Query opcional: servicio_id
     */
    public function cpeDhcpClients(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        $servicioId = (int) $request->query('servicio_id', 0);
        $data = $this->cpeDhcp->forCliente(
            $cliente,
            $servicioId > 0 ? $servicioId : null
        );

        $count = count($data['clients'] ?? []);
        $message = $count > 0
            ? ($count === 1 ? '1 dispositivo DHCP' : "{$count} dispositivos DHCP")
            : 'Sin clientes DHCP del CPE (soft-fail / vacío)';

        return $this->ok($data, $message);
    }
}
