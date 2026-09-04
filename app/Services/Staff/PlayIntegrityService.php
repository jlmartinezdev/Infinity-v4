<?php

namespace App\Services\Staff;

use App\Models\IntegrityNonce;
use App\Models\IntegrityVerdict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlayIntegrityService
{
    /**
     * @return array{nonce: string, expires_in: int}
     */
    public function emitirNonce(?string $ip = null): array
    {
        $ttl = max(30, (int) config('integrity.nonce_ttl_seconds', 120));
        $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        IntegrityNonce::create([
            'nonce' => $nonce,
            'ip' => $ip,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        IntegrityNonce::query()
            ->where('expires_at', '<', now()->subHour())
            ->limit(200)
            ->delete();

        return [
            'nonce' => $nonce,
            'expires_in' => $ttl,
        ];
    }

    /**
     * Alias compat Staff.
     */
    public function verificarLoginStaff(
        ?string $deviceName,
        ?string $integrityToken,
        ?string $integrityNonce,
        ?string $ip = null,
    ): ?string {
        return $this->verificarLogin('staff', $deviceName, $integrityToken, $integrityNonce, $ip);
    }

    /**
     * Valida Integrity en login. Devuelve null si OK / no aplica;
     * string con mensaje si debe rechazarse (solo cuando INTEGRITY_ENFORCE=true).
     *
     * @param  'staff'|'cliente'  $tipo
     */
    public function verificarLogin(
        string $tipo,
        ?string $deviceName,
        ?string $integrityToken,
        ?string $integrityNonce,
        ?string $ip = null,
    ): ?string {
        $tipo = $tipo === 'cliente' ? 'cliente' : 'staff';
        $expectedPackage = (string) config("integrity.packages.{$tipo}", $tipo === 'cliente' ? 'com.isp.clientes' : 'com.isp.staff');
        $expectedDevice = (string) config("integrity.device_names.{$tipo}", '');
        $esAppConocida = $expectedDevice !== ''
            && $deviceName !== null
            && strcasecmp(trim($deviceName), $expectedDevice) === 0;

        $tieneToken = filled($integrityToken) && filled($integrityNonce);
        $enforce = (bool) config('integrity.enforce', false);

        // Staff: solo si device_name de la app Staff (o si mandó token).
        // Clientes: si mandó token/nonce (release Play) se verifica siempre (log-only).
        // Con enforce, también exige token cuando device_name coincide.
        if ($tipo === 'staff' && ! $esAppConocida && ! $tieneToken) {
            return null;
        }
        if ($tipo === 'cliente' && ! $tieneToken && ! $esAppConocida) {
            return null;
        }

        if (! $tieneToken) {
            $this->persistirVeredicto([
                'device_name' => $deviceName,
                'package_name' => $expectedPackage,
                'ok' => false,
                'error' => 'sin_token_o_nonce',
                'enforced' => $enforce,
                'blocked' => $enforce && $esAppConocida,
                'ip' => $ip,
                'payload_summary' => ['tipo' => $tipo],
            ]);
            Log::info('[Integrity] login sin token/nonce', [
                'tipo' => $tipo,
                'device_name' => $deviceName,
                'enforce' => $enforce,
            ]);

            return ($enforce && $esAppConocida) ? 'Se requiere verificación Play Integrity.' : null;
        }

        $nonceConsumed = false;
        try {
            $nonceConsumed = DB::transaction(function () use ($integrityNonce) {
                $nonceRow = IntegrityNonce::query()
                    ->where('nonce', $integrityNonce)
                    ->lockForUpdate()
                    ->first();

                if (! $nonceRow || ! $nonceRow->estaVigente()) {
                    return false;
                }

                $nonceRow->used_at = now();
                $nonceRow->save();

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('[Integrity] error consumiendo nonce', ['error' => $e->getMessage()]);
            $nonceConsumed = false;
        }

        if (! $nonceConsumed) {
            $this->persistirVeredicto([
                'device_name' => $deviceName,
                'nonce' => (string) $integrityNonce,
                'package_name' => $expectedPackage,
                'ok' => false,
                'error' => 'nonce_invalido_o_reusado',
                'enforced' => $enforce,
                'blocked' => $enforce,
                'ip' => $ip,
                'payload_summary' => ['tipo' => $tipo],
            ]);
            Log::warning('[Integrity] nonce inválido o vencido', [
                'tipo' => $tipo,
                'nonce' => Str::limit((string) $integrityNonce, 16, '…'),
            ]);

            return $enforce ? 'Nonce Integrity inválido o vencido.' : null;
        }

        $decoded = $this->decodeToken((string) $integrityToken, $expectedPackage);
        if ($decoded === null) {
            $this->persistirVeredicto([
                'device_name' => $deviceName,
                'nonce' => (string) $integrityNonce,
                'package_name' => $expectedPackage,
                'ok' => false,
                'error' => 'decode_omitido_o_fallo',
                'enforced' => $enforce,
                'blocked' => $enforce,
                'ip' => $ip,
                'payload_summary' => ['tipo' => $tipo],
            ]);
            Log::info('[Integrity] decode omitido o falló (credenciales / API)', [
                'tipo' => $tipo,
                'enforce' => $enforce,
            ]);

            return $enforce ? 'No se pudo verificar el token Play Integrity.' : null;
        }

        $error = $this->validarPayload($decoded, (string) $integrityNonce, $expectedPackage);
        $summary = $this->resumenPayload($decoded);
        $summary['tipo'] = $tipo;
        $blocked = $error !== null && $enforce;

        $this->persistirVeredicto([
            'device_name' => $deviceName,
            'nonce' => (string) $integrityNonce,
            'package_name' => $summary['package'] ?? $expectedPackage,
            'app_recognition_verdict' => $summary['app_verdict'] ?? null,
            'device_recognition_verdict' => is_array($summary['device_verdict'] ?? null)
                ? implode(',', $summary['device_verdict'])
                : ($summary['device_verdict'] ?? null),
            'app_licensing_verdict' => $summary['license_verdict'] ?? null,
            'ok' => $error === null,
            'error' => $error,
            'enforced' => $enforce,
            'blocked' => $blocked,
            'ip' => $ip,
            'payload_summary' => $summary,
        ]);

        if ($error !== null) {
            Log::warning('[Integrity] veredicto rechazado', [
                'tipo' => $tipo,
                'error' => $error,
                'payload_summary' => $summary,
                'enforce' => $enforce,
            ]);

            return $enforce ? $error : null;
        }

        Log::info('[Integrity] OK', ['tipo' => $tipo, 'summary' => $summary]);

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeToken(string $token, string $package): ?array
    {
        $projectNumber = trim((string) config('integrity.cloud_project_number', ''));
        $credentials = trim((string) config('integrity.credentials', ''));

        if ($credentials === '') {
            return null;
        }

        try {
            $accessToken = $this->obtenerAccessToken($credentials);
            if (! $accessToken) {
                return null;
            }

            $url = sprintf(
                'https://playintegrity.googleapis.com/v1/%s:decodeIntegrityToken',
                rawurlencode($package)
            );

            $request = Http::withToken($accessToken)->timeout(12);
            if ($projectNumber !== '') {
                $request = $request->withHeaders([
                    'X-Goog-User-Project' => $projectNumber,
                ]);
            }

            $resp = $request->post($url, [
                'integrity_token' => $token,
            ]);

            if (! $resp->successful()) {
                Log::warning('[Integrity] API error', [
                    'package' => $package,
                    'status' => $resp->status(),
                    'body' => Str::limit($resp->body(), 500),
                ]);

                return null;
            }

            return $resp->json('tokenPayloadExternal') ?? $resp->json();
        } catch (\Throwable $e) {
            Log::warning('[Integrity] exception decode', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function obtenerAccessToken(string $credentials): ?string
    {
        $json = null;
        if (is_file($credentials)) {
            $json = json_decode((string) file_get_contents($credentials), true);
        } else {
            $json = json_decode($credentials, true);
        }
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        $now = time();
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->b64url(json_encode([
            'iss' => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/playintegrity',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header.'.'.$claim;
        $key = openssl_pkey_get_private($json['private_key']);
        if (! $key) {
            return null;
        }
        openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);
        $jwt = $unsigned.'.'.$this->b64url($signature);

        $resp = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $resp->successful()) {
            return null;
        }

        return $resp->json('access_token');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarPayload(array $payload, string $nonce, string $expectedPackage): ?string
    {
        $requestDetails = $payload['requestDetails'] ?? [];
        $appIntegrity = $payload['appIntegrity'] ?? [];
        $deviceIntegrity = $payload['deviceIntegrity'] ?? [];
        $accountDetails = $payload['accountDetails'] ?? [];

        $package = (string) ($requestDetails['requestPackageName'] ?? $appIntegrity['packageName'] ?? '');
        if ($package !== '' && strcasecmp($package, $expectedPackage) !== 0) {
            return 'packageName Integrity no coincide.';
        }

        $tokenNonce = (string) ($requestDetails['nonce'] ?? '');
        if ($tokenNonce !== '' && ! $this->nonceCoincide($nonce, $tokenNonce)) {
            Log::info('[Integrity] nonce mismatch', [
                'expected' => Str::limit($nonce, 12, '…'),
                'got' => Str::limit($tokenNonce, 12, '…'),
            ]);

            return 'Nonce Integrity no coincide con el token.';
        }

        $verdict = (string) ($appIntegrity['appRecognitionVerdict'] ?? '');
        if ($verdict !== '' && $verdict !== 'PLAY_RECOGNIZED') {
            return 'appRecognitionVerdict no es PLAY_RECOGNIZED.';
        }

        $deviceVerdicts = $deviceIntegrity['deviceRecognitionVerdict'] ?? [];
        if (is_string($deviceVerdicts)) {
            $deviceVerdicts = [$deviceVerdicts];
        }
        if (is_array($deviceVerdicts) && $deviceVerdicts !== [] && ! in_array('MEETS_DEVICE_INTEGRITY', $deviceVerdicts, true)) {
            return 'El dispositivo no cumple MEETS_DEVICE_INTEGRITY.';
        }

        $license = (string) ($accountDetails['appLicensingVerdict'] ?? '');
        if ($license !== '' && $license !== 'LICENSED') {
            return 'appLicensingVerdict no es LICENSED.';
        }

        $allowedByPackage = config('integrity.allowed_cert_sha256_by_package', []);
        $allowedCerts = [];
        if (is_array($allowedByPackage)) {
            $allowedCerts = $allowedByPackage[$expectedPackage]
                ?? $allowedByPackage[strtolower($expectedPackage)]
                ?? [];
        }
        if (! is_array($allowedCerts) || $allowedCerts === []) {
            // Fallback legacy solo para staff
            if ($expectedPackage === (string) config('integrity.packages.staff', 'com.isp.staff')) {
                $allowedCerts = config('integrity.allowed_cert_sha256', []);
            }
        }

        $certs = $appIntegrity['certificateSha256Digest'] ?? [];
        if (is_string($certs)) {
            $certs = [$certs];
        }
        if (is_array($allowedCerts) && $allowedCerts !== [] && is_array($certs) && $certs !== []) {
            $ok = false;
            foreach ($certs as $cert) {
                foreach ($allowedCerts as $allowed) {
                    if (strcasecmp(trim((string) $cert), trim((string) $allowed)) === 0) {
                        $ok = true;
                        break 2;
                    }
                }
            }
            if (! $ok) {
                return 'Certificado de la app no autorizado.';
            }
        }

        return null;
    }

    private function nonceCoincide(string $expected, string $got): bool
    {
        if (hash_equals($expected, $got)) {
            return true;
        }

        $decoded = base64_decode(strtr($got, '-_', '+/'), true);
        if ($decoded !== false && hash_equals($expected, $this->b64url($decoded))) {
            return true;
        }

        $decodedExpected = base64_decode(strtr($expected, '-_', '+/'), true);
        if ($decodedExpected !== false && hash_equals($this->b64url($decodedExpected), $got)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resumenPayload(array $payload): array
    {
        return [
            'package' => $payload['requestDetails']['requestPackageName']
                ?? $payload['appIntegrity']['packageName']
                ?? null,
            'app_verdict' => $payload['appIntegrity']['appRecognitionVerdict'] ?? null,
            'device_verdict' => $payload['deviceIntegrity']['deviceRecognitionVerdict'] ?? null,
            'license_verdict' => $payload['accountDetails']['appLicensingVerdict'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirVeredicto(array $data): void
    {
        try {
            IntegrityVerdict::create($data);
        } catch (\Throwable $e) {
            Log::warning('[Integrity] no se pudo persistir veredicto', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
