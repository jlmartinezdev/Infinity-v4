<?php

namespace App\Services\Tpago;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TpagoClient
{
    /** Keys presentes (aunque falte commerce/branch). */
    public function hasCredentials(): bool
    {
        return filled(config('tpago.public_key')) && filled(config('tpago.private_key'));
    }

    /** Listo para generar links de pago. */
    public function enabled(): bool
    {
        return (bool) config('tpago.enabled')
            && $this->hasCredentials()
            && filled(config('tpago.commerce_code'))
            && filled(config('tpago.branch_code'));
    }

    /** @return list<string> */
    public function missingConfig(): array
    {
        $missing = [];
        if (! config('tpago.enabled')) {
            $missing[] = 'TPAGO_ENABLED';
        }
        if (! filled(config('tpago.public_key'))) {
            $missing[] = 'TPAGO_PUBLIC_KEY';
        }
        if (! filled(config('tpago.private_key'))) {
            $missing[] = 'TPAGO_PRIVATE_KEY';
        }
        if (! filled(config('tpago.commerce_code'))) {
            $missing[] = 'TPAGO_COMMERCE_CODE';
        }
        if (! filled(config('tpago.branch_code'))) {
            $missing[] = 'TPAGO_BRANCH_CODE';
        }

        return $missing;
    }

    public function callbackUrl(): string
    {
        $configured = trim((string) config('tpago.callback_url'));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/api/v1/webhooks/tpago';
    }

    /**
     * @param  array{amount:int, description:string, reference_id?:string, require_user_data?:bool}  $payload
     * @return array<string, mixed>
     */
    public function generatePaymentLink(array $payload): array
    {
        $commerce = rawurlencode(trim((string) config('tpago.commerce_code')));
        $branch = rawurlencode(trim((string) config('tpago.branch_code')));

        $path = "/external-commerce/api/0.1/commerces/{$commerce}/branches/{$branch}/links/generate-payment-link";

        $response = $this->request('post', $path, [
            'amount' => (int) $payload['amount'],
            'description' => (string) $payload['description'],
            'reference_id' => (string) ($payload['reference_id'] ?? ''),
            'require_user_data' => (bool) ($payload['require_user_data'] ?? false),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'TPago generate-payment-link falló: HTTP '.$response->status().' '.$response->body()
            );
        }

        $json = $response->json() ?? [];
        if (($json['status'] ?? '') !== 'success' || empty($json['payment_link'])) {
            throw new RuntimeException('TPago respuesta inválida: '.$response->body());
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentsByAlias(string $alias): array
    {
        $commerce = rawurlencode(trim((string) config('tpago.commerce_code')));
        $branch = rawurlencode(trim((string) config('tpago.branch_code')));
        $alias = rawurlencode($alias);

        $path = "/external-commerce/api/0.1/commerces/{$commerce}/branches/{$branch}/links/{$alias}/payments";

        $response = $this->request('get', $path);
        if (! $response->successful()) {
            throw new RuntimeException(
                'TPago get payments falló: HTTP '.$response->status().' '.$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function request(string $method, string $path, array $body = []): Response
    {
        $base = rtrim((string) config('tpago.base_url'), '/');
        $public = (string) config('tpago.public_key');
        $private = (string) config('tpago.private_key');

        if ($public === '' || $private === '') {
            throw new RuntimeException('Credenciales TPago no configuradas.');
        }

        $http = Http::timeout((int) config('tpago.http_timeout', 30))
            ->withBasicAuth($public, $private)
            ->acceptJson()
            ->asJson();

        return $method === 'get'
            ? $http->get($base.$path)
            : $http->{$method}($base.$path, $body);
    }
}
