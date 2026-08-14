<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Tpago\TpagoCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Confirmación de pagos TPago (Bancard).
 *
 * POST /api/v1/webhooks/tpago
 * Respuesta requerida por Bancard: { "status": "success" }
 */
class TpagoWebhookController extends ApiController
{
    public function __construct(
        private readonly TpagoCallbackService $callbacks,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->callbackAuthValida($request)) {
            Log::warning('[TPago webhook] Basic Auth inválida', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401)
                ->header('WWW-Authenticate', 'Basic realm="TPago callback"');
        }

        if (config('tpago.verify_ip') && ! $this->ipPermitida($request)) {
            Log::warning('[TPago webhook] IP no permitida', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $payload = $request->all();
        if ($payload === []) {
            $raw = (string) $request->getContent();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        try {
            $this->callbacks->handle($payload);
        } catch (Throwable $e) {
            Log::error('[TPago webhook] fallo: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            // Sin status success Bancard revierte el pago al cliente.
            return response()->json([
                'status' => 'error',
                'message' => 'Error procesando confirmación',
            ], 500);
        }

        return response()->json(['status' => 'success']);
    }

    private function callbackAuthValida(Request $request): bool
    {
        $user = trim((string) config('tpago.callback_user'));
        $pass = (string) config('tpago.callback_password');

        // Sin credenciales configuradas: aceptar (dev / compat).
        if ($user === '' || $pass === '') {
            return true;
        }

        $givenUser = (string) $request->getUser();
        $givenPass = (string) $request->getPassword();

        return hash_equals($user, $givenUser) && hash_equals($pass, $givenPass);
    }

    private function ipPermitida(Request $request): bool
    {
        $allowed = config('tpago.allowed_ips', []);
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $ip = (string) $request->ip();

        return in_array($ip, $allowed, true);
    }
}
