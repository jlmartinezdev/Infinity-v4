<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FacturaInterna;
use App\Models\ServicioHotspot;
use App\Services\Portal\PortalAppConfigService;
use App\Services\Portal\PortalCambioClaveService;
use App\Services\Portal\PortalCpeDhcpService;
use App\Services\Portal\PortalCpeWifiService;
use App\Services\Portal\PortalFeatureFlagsService;
use App\Services\Portal\PortalHotspotSlotsService;
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
        private readonly PortalCpeWifiService $cpeWifi,
        private readonly PortalCambioClaveService $cambioClave,
        private readonly PortalHotspotSlotsService $hotspotSlots,
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

    /**
     * GET Wi‑Fi del CPE (SSID, si se puede cambiar clave). Nunca incluye la clave actual.
     * Query opcional: servicio_id
     */
    public function cpeWifi(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        $servicioId = (int) $request->query('servicio_id', 0);
        $data = $this->cpeWifi->estado(
            $cliente,
            $servicioId > 0 ? $servicioId : null
        );

        $message = ($data['can_change'] ?? false)
            ? 'Wi‑Fi del CPE'
            : (string) ($data['hint'] ?? 'No se puede cambiar la clave desde la app.');

        return $this->ok($data, $message);
    }

    /**
     * POST clave y/o nombre Wi‑Fi (TR-069). Body: password y/o ssid, wifi_id opcional, servicio_id opcional.
     */
    public function cpeWifiCambiar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:63', 'required_without:ssid'],
            'ssid' => ['nullable', 'string', 'min:1', 'max:32', 'required_without:password'],
            'wifi_id' => ['nullable', 'string', 'max:64'],
            'servicio_id' => ['nullable', 'integer'],
        ]);

        $cliente = $request->user()->cliente()->firstOrFail();
        $servicioId = (int) ($validated['servicio_id'] ?? $request->query('servicio_id', 0));
        $wifiId = isset($validated['wifi_id']) ? trim((string) $validated['wifi_id']) : null;
        $wifiId = $wifiId !== '' ? $wifiId : null;
        $password = isset($validated['password']) ? (string) $validated['password'] : null;
        $ssid = isset($validated['ssid']) ? trim((string) $validated['ssid']) : null;

        $result = $this->cpeWifi->cambiar(
            $cliente,
            $password !== '' ? $password : null,
            $wifiId,
            $servicioId > 0 ? $servicioId : null,
            $ssid !== '' ? $ssid : null
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ], (int) ($result['http'] ?? 422));
        }

        return $this->ok($result['data'], $result['message']);
    }

    /**
     * GET preferencias de la app (push cambio clave, debe cambiar clave, etc.).
     */
    public function preferencias(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        return $this->ok($this->cambioClave->preferencias($cliente));
    }

    /**
     * PATCH preferencias. Body: { "push_cambio_clave": true|false }
     */
    public function preferenciasActualizar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_cambio_clave' => ['required', 'boolean'],
        ]);

        $cliente = $request->user()->cliente()->firstOrFail();
        $data = $this->cambioClave->actualizarPreferencias($cliente, $validated);

        return $this->ok($data, 'Preferencias actualizadas.');
    }

    /**
     * POST cambio de clave de ingreso a la app.
     * Body: clave_actual, clave_nueva, clave_nueva_confirmation
     */
    public function cambiarClave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clave_actual' => ['required', 'string', 'max:128'],
            'clave_nueva' => [
                'required',
                'string',
                'min:'.PortalCambioClaveService::CLAVE_MIN,
                'max:'.PortalCambioClaveService::CLAVE_MAX,
                'confirmed',
            ],
        ]);

        try {
            $data = $this->cambioClave->cambiar(
                $request->user(),
                (string) $validated['clave_actual'],
                (string) $validated['clave_nueva']
            );
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo cambiar la clave.';

            return $this->fail((string) $msg, 422, $e->errors());
        }

        return $this->ok($data, 'Clave actualizada.');
    }

    /**
     * GET slots hotspot del cliente (máx. 3).
     */
    public function hotspotSlots(Request $request): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        try {
            $data = $this->hotspotSlots->listar($cliente);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Hotspot no disponible.';

            return $this->fail((string) $msg, 403, $e->errors());
        }

        return $this->ok($data, 'Slots hotspot');
    }

    /**
     * POST crear slot hotspot.
     * Body: servicio_id?, slot_numero?, password? (PIN 4 dígitos)
     */
    public function hotspotSlotCrear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'servicio_id' => ['nullable', 'integer', 'min:1'],
            'slot_numero' => ['nullable', 'integer', 'min:1', 'max:'.ServicioHotspot::MAX_POR_CLIENTE],
            'password' => ServicioHotspot::reglasPin(false),
        ], [
            'password.digits' => ServicioHotspot::mensajePin(),
        ]);

        $cliente = $request->user()->cliente()->firstOrFail();

        try {
            $data = $this->hotspotSlots->crear($cliente, $validated);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo crear el slot.';
            $status = str_contains(strtolower((string) $msg), 'no está disponible') ? 403 : 422;

            return $this->fail((string) $msg, $status, $e->errors());
        }

        return $this->ok($data, 'Usuario hotspot creado.', 201);
    }

    /**
     * PATCH PIN de un slot.
     * Body: { "password": "1234" }
     */
    public function hotspotSlotActualizar(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'password' => ServicioHotspot::reglasPin(true),
        ], [
            'password.digits' => ServicioHotspot::mensajePin(),
        ]);

        $cliente = $request->user()->cliente()->firstOrFail();
        $slot = ServicioHotspot::query()->findOrFail($id);

        try {
            $data = $this->hotspotSlots->actualizarPassword($cliente, $slot, $validated);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo actualizar el PIN.';
            $status = str_contains(strtolower((string) $msg), 'no está disponible') ? 403 : 422;

            return $this->fail((string) $msg, $status, $e->errors());
        }

        return $this->ok($data, 'PIN hotspot actualizado.');
    }

    /**
     * DELETE slot hotspot.
     */
    public function hotspotSlotEliminar(Request $request, int $id): JsonResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        $slot = ServicioHotspot::query()->findOrFail($id);

        try {
            $this->hotspotSlots->eliminar($cliente, $slot);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo eliminar el slot.';
            $status = str_contains(strtolower((string) $msg), 'no está disponible') ? 403 : 422;

            return $this->fail((string) $msg, $status, $e->errors());
        }

        return $this->ok(null, 'Usuario hotspot eliminado.');
    }
}
