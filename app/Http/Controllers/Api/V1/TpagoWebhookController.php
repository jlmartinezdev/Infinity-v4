<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Tpago\TpagoCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Confirmación de pagos TPago / Bancard.
 *
 * POST /api/v1/webhooks/tpago
 * Respuesta requerida: { "status": "success", "messages": [...] }
 */
class TpagoWebhookController extends ApiController
{
    public function __construct(
        private readonly TpagoCallbackService $callbacks,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        $auth = $this->resolverAuth($request);
        $pareceTpago = $this->pareceConfirmacionTpago($payload);

        $this->logConfirmacion($request, $payload, $auth, $pareceTpago);

        if ($request->isMethod('get') || $request->isMethod('head')) {
            return $this->tpagoResponse('success', 'Ok', 'Webhook TPago activo');
        }

        if (! $auth['ok'] && ! $pareceTpago) {
            return $this->tpagoResponse('error', 'ConfirmedError', 'Unauthorized', 401)
                ->header('WWW-Authenticate', 'Basic realm="TPago callback"');
        }

        if (config('tpago.verify_ip') && ! $this->ipPermitida($request)) {
            $this->logTpago('warning', 'IP no permitida', [
                'ip' => $request->ip(),
                'cf_ip' => $request->header('CF-Connecting-IP'),
            ]);

            return $this->tpagoResponse('error', 'ConfirmedError', 'Forbidden', 403);
        }

        try {
            $this->callbacks->handle($payload);
        } catch (Throwable $e) {
            $this->logTpago('error', 'Fallo procesando confirmación: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->tpagoResponse(
                'error',
                'ConfirmedError',
                'No se pudo procesar la confirmacion',
                500
            );
        }

        $this->logTpago('info', 'Confirmación aceptada', [
            'auth_via' => $auth['via'],
            'auth_ok' => $auth['ok'],
            'aceptado_sin_auth' => ! $auth['ok'] && $pareceTpago,
        ]);

        return $this->tpagoResponse('success', 'Confirmed', 'Pago recibido con exito');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->all();
        if ($payload === []) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $payload;
    }

    /**
     * @return array{ok: bool, via: string, user: string, pass_len: int, has_header: bool}
     */
    private function resolverAuth(Request $request): array
    {
        $givenUser = (string) $request->getUser();
        $givenPass = (string) $request->getPassword();
        $hasHeader = $request->headers->has('Authorization')
            || filled($request->server('HTTP_AUTHORIZATION'))
            || filled($request->server('REDIRECT_HTTP_AUTHORIZATION'));

        if ($givenUser === '' && $givenPass === '') {
            $this->hidratarBasicDesdeHeader($request, $givenUser, $givenPass, $hasHeader);
        }

        $via = 'none';
        $ok = false;

        $callbackUser = trim((string) config('tpago.callback_user'));
        $callbackPass = (string) config('tpago.callback_password');
        if ($callbackUser !== '' && $callbackPass !== ''
            && hash_equals($callbackUser, $givenUser) && hash_equals($callbackPass, $givenPass)) {
            $ok = true;
            $via = 'callback';
        }

        $public = trim((string) config('tpago.public_key'));
        $private = (string) config('tpago.private_key');
        $publicSinPrefijo = str_starts_with($public, 'apps/') ? substr($public, 5) : $public;
        if (! $ok && $public !== '' && $private !== '') {
            $users = array_values(array_unique(array_filter([$public, $publicSinPrefijo])));
            foreach ($users as $user) {
                if (hash_equals($user, $givenUser) && hash_equals($private, $givenPass)) {
                    $ok = true;
                    $via = 'keys';
                    break;
                }
            }
        }

        // TPago producción llega con Basic user="" (Apache lo registra como "").
        if (! $ok && $givenUser === '' && $givenPass === '') {
            $ok = false;
            $via = $hasHeader ? 'empty' : 'missing';
        }

        return [
            'ok' => $ok,
            'via' => $via,
            'user' => $givenUser,
            'pass_len' => strlen($givenPass),
            'has_header' => $hasHeader,
        ];
    }

    private function hidratarBasicDesdeHeader(Request $request, string &$user, string &$pass, bool &$hasHeader): void
    {
        $header = (string) (
            $request->header('Authorization')
            ?: $request->server('HTTP_AUTHORIZATION')
            ?: $request->server('REDIRECT_HTTP_AUTHORIZATION')
            ?: ''
        );
        if ($header === '' || ! str_starts_with(strtolower($header), 'basic ')) {
            return;
        }

        $hasHeader = true;
        $decoded = base64_decode(trim(substr($header, 6)), true);
        if ($decoded === false || ! str_contains($decoded, ':')) {
            return;
        }

        [$user, $pass] = explode(':', $decoded, 2);
    }

    /** @param  array<string, mixed>  $payload */
    private function pareceConfirmacionTpago(array $payload): bool
    {
        if (isset($payload['payment']) && is_array($payload['payment'])) {
            return true;
        }

        foreach (['hook_alias', 'link_alias', 'ticket_number', 'response_code'] as $key) {
            if (filled($payload[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{ok: bool, via: string, user: string, pass_len: int, has_header: bool}  $auth
     */
    private function logConfirmacion(Request $request, array $payload, array $auth, bool $pareceTpago): void
    {
        $this->logTpago('info', 'Confirmación recibida en la URL', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'cf_ip' => $request->header('CF-Connecting-IP'),
            'ua' => substr((string) $request->userAgent(), 0, 120),
            'auth_header' => $auth['has_header'],
            'auth_user' => $auth['user'] === '' ? '(vacio)' : $auth['user'],
            'auth_pass_len' => $auth['pass_len'],
            'auth_ok' => $auth['ok'],
            'auth_via' => $auth['via'],
            'parece_tpago' => $pareceTpago,
            'alias' => $payload['link_alias']
                ?? $payload['hook_alias']
                ?? data_get($payload, 'payment.link_alias')
                ?? data_get($payload, 'payment.hook_alias'),
            'ticket' => $payload['ticket_number'] ?? data_get($payload, 'payment.ticket_number'),
            'amount' => $payload['amount'] ?? data_get($payload, 'payment.amount'),
            'response_code' => $payload['response_code'] ?? data_get($payload, 'payment.response_code'),
            'status' => $payload['status'] ?? data_get($payload, 'payment.status'),
            'payload_keys' => array_keys($payload),
        ]);
    }

    /** @param  array<string, mixed>  $context */
    private function logTpago(string $level, string $message, array $context = []): void
    {
        Log::{$level}('[TPago webhook] '.$message, $context);
        Log::channel('tpago')->{$level}($message, $context);
    }

    private function tpagoResponse(string $status, string $key, string $description, int $http = 200): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'messages' => [[
                'level' => $status === 'success' ? 'success' : 'error',
                'key' => $key,
                'description' => $description,
            ]],
        ], $http);
    }

    private function ipPermitida(Request $request): bool
    {
        $allowed = config('tpago.allowed_ips', []);
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $ips = array_values(array_filter([
            (string) $request->ip(),
            (string) $request->header('CF-Connecting-IP'),
        ]));

        return array_intersect($ips, $allowed) !== [];
    }
}
