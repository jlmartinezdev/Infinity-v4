<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push FCM vía HTTP v1 (API legacy /fcm/send está descontinuada → 404).
 *
 * Requiere JSON de cuenta de servicio Firebase:
 *   FCM_SERVICE_ACCOUNT_PATH=storage/app/firebase-service-account.json
 *   (opcional) FCM_PROJECT_ID=...  — si no, se lee del JSON
 *
 * El payload incluye "notification" + sound para despertar Android.
 *
 * Importante: el token FCM del dispositivo debe pertenecer al mismo
 * proyecto Firebase que la cuenta de servicio (hoy: isp-staff-panel).
 * Si la app cliente usa otro proyecto, hay que configurar su JSON aparte.
 */
class FcmPushService
{
    public function notifyStaff(string $title, string $body, array $data = []): void
    {
        $ctx = $this->contextoEnvio();
        if ($ctx === null) {
            Log::info('FCM omitido (sin cuenta de servicio HTTP v1)', compact('title', 'body', 'data'));

            return;
        }

        [$projectId, $accessToken] = $ctx;
        $channel = config('services.fcm.android_channel_id', 'staff');
        $messageBase = $this->messageBase($title, $body, $data, $channel);

        $topic = config('services.fcm.staff_topic') ?: env('FCM_STAFF_TOPIC', 'staff');
        // Solo topic: la app staff se suscribe a "staff". Enviar también a push_token
        // duplica la notificación en el mismo dispositivo.
        $this->enviarV1($projectId, $accessToken, array_merge($messageBase, [
            'topic' => $topic,
        ]), 'topic:'.$topic);
    }

    /**
     * Push a un usuario concreto (staff o cliente portal) por su push_token guardado.
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): bool
    {
        $token = trim((string) ($user->push_token ?? ''));
        if ($token === '') {
            Log::info('FCM omitido (usuario sin push_token)', [
                'usuario_id' => $user->usuario_id,
                'cliente_id' => $user->cliente_id,
                'title' => $title,
            ]);

            return false;
        }

        return $this->notifyToken($token, $title, $body, $data, $user->esClientePortal() ? 'clientes' : null);
    }

    /**
     * Push al usuario portal vinculado a un cliente_id (si existe y tiene token).
     */
    public function notifyCliente(int $clienteId, string $title, string $body, array $data = []): bool
    {
        $user = User::query()
            ->where('cliente_id', $clienteId)
            ->activos()
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '')
            ->orderByDesc('ultimo_acceso_at')
            ->first();

        if (! $user) {
            Log::info('FCM omitido (cliente sin usuario/token)', [
                'cliente_id' => $clienteId,
                'title' => $title,
            ]);

            return false;
        }

        return $this->notifyUser($user, $title, $body, $data);
    }

    /**
     * Envío directo a un token FCM (pruebas / uso interno).
     */
    public function notifyToken(string $token, string $title, string $body, array $data = [], ?string $channelOverride = null): bool
    {
        $ctx = $this->contextoEnvio();
        if ($ctx === null) {
            Log::info('FCM omitido (sin cuenta de servicio HTTP v1)', compact('title', 'body'));

            return false;
        }

        [$projectId, $accessToken] = $ctx;
        $channel = $channelOverride
            ?: config('services.fcm.client_android_channel_id', 'clientes');
        $message = array_merge(
            $this->messageBase($title, $body, $data, $channel),
            ['token' => $token]
        );

        return $this->enviarV1($projectId, $accessToken, $message, 'token:direct');
    }

    /**
     * @return array{0: string, 1: string}|null  [projectId, accessToken]
     */
    private function contextoEnvio(): ?array
    {
        $credentials = $this->loadCredentials();
        if ($credentials === null) {
            return null;
        }

        $accessToken = $this->accessToken($credentials);
        if ($accessToken === null) {
            return null;
        }

        $projectId = config('services.fcm.project_id') ?: ($credentials['project_id'] ?? null);
        if (! $projectId) {
            Log::warning('FCM: falta project_id');

            return null;
        }

        return [$projectId, $accessToken];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function messageBase(string $title, string $body, array $data, string $channel): array
    {
        $dataPayload = [];
        foreach (array_merge($data, ['title' => $title, 'body' => $body]) as $k => $v) {
            $dataPayload[(string) $k] = is_scalar($v) || $v === null ? (string) $v : json_encode($v);
        }

        return [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => $channel,
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
            'data' => $dataPayload,
        ];
    }

    /**
     * @return array{project_id?: string, client_email: string, private_key: string}|null
     */
    private function loadCredentials(): ?array
    {
        $path = config('services.fcm.service_account_path') ?: env('FCM_SERVICE_ACCOUNT_PATH');
        if (! $path) {
            return null;
        }

        $full = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
            ? $path
            : base_path($path);

        if (! is_readable($full)) {
            Log::warning('FCM: no se puede leer service account', ['path' => $full]);

            return null;
        }

        $json = json_decode((string) file_get_contents($full), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            Log::warning('FCM: JSON de service account inválido', ['path' => $full]);

            return null;
        }

        return $json;
    }

    /**
     * @param  array{client_email: string, private_key: string}  $credentials
     */
    private function accessToken(array $credentials): ?string
    {
        $cacheKey = 'fcm_access_token_'.sha1($credentials['client_email']);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $now = time();
            $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->b64url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claims;

            $key = openssl_pkey_get_private($credentials['private_key']);
            if ($key === false) {
                Log::warning('FCM: private_key inválida');

                return null;
            }

            $signature = '';
            if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
                Log::warning('FCM: no se pudo firmar JWT');

                return null;
            }

            $jwt = $unsigned.'.'.$this->b64url($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('FCM OAuth falló', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $token = (string) $response->json('access_token');
            $expiresIn = (int) ($response->json('expires_in') ?: 3600);
            Cache::put($cacheKey, $token, max(60, $expiresIn - 120));

            return $token;
        } catch (\Throwable $e) {
            Log::warning('FCM OAuth excepción: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function enviarV1(string $projectId, string $accessToken, array $message, string $destino = ''): bool
    {
        $url = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($url, ['message' => $message]);

            if (! $response->successful()) {
                Log::warning('FCM falló', [
                    'destino' => $destino,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info('FCM OK', [
                'destino' => $destino,
                'name' => $response->json('name'),
                'title' => $message['notification']['title'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('FCM excepción: '.$e->getMessage(), ['destino' => $destino]);

            return false;
        }
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
