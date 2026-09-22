<?php

namespace App\Services\Cloudflare;

use App\Models\FacturacionParametro;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloudflareDnsService
{
    /**
     * @return array{ok: bool, configured: bool, zone: string, origin_tigo: string, origin_ufinet: string, last_ip: ?string, last_error: ?string, last_at: ?string}
     */
    public function snapshot(): array
    {
        $estado = $this->estadoGuardado();

        return [
            'ok' => (bool) ($estado['ok'] ?? false),
            'configured' => $this->token() !== '',
            'zone' => $this->zone(),
            'origin_tigo' => $this->originTigo(),
            'origin_ufinet' => $this->originUfinet(),
            'last_ip' => isset($estado['ip']) ? (string) $estado['ip'] : null,
            'last_error' => isset($estado['error']) ? (string) $estado['error'] : null,
            'last_at' => isset($estado['at']) ? (string) $estado['at'] : null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, ip?: string, updated?: int}
     */
    public function apuntar(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return ['ok' => false, 'message' => 'IP de origen inválida.'];
        }

        if ($this->token() === '') {
            $this->guardarEstado(false, $ip, 'Falta CLOUDFLARE_API_TOKEN en .env');

            return ['ok' => false, 'message' => 'Falta CLOUDFLARE_API_TOKEN en .env. El NAT cambió, el DNS no.'];
        }

        try {
            $zoneId = $this->zoneId();
            if ($zoneId === '') {
                $this->guardarEstado(false, $ip, 'No se encontró la zona '.$this->zone());

                return ['ok' => false, 'message' => 'Cloudflare no encontró la zona '.$this->zone().'.'];
            }

            $records = $this->registrosA($zoneId);
            $objetivos = $this->nombres();
            $origenes = [$this->originTigo(), $this->originUfinet()];
            $cambiados = 0;

            foreach ($records as $row) {
                $name = strtolower((string) ($row['name'] ?? ''));
                $content = (string) ($row['content'] ?? '');
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || $name === '') {
                    continue;
                }
                if ($objetivos !== []) {
                    if (! in_array($name, $objetivos, true)) {
                        continue;
                    }
                } elseif (! in_array($content, $origenes, true)) {
                    continue;
                }
                if ($content === $ip) {
                    continue;
                }

                $this->patchRecord($zoneId, $id, $ip, (bool) ($row['proxied'] ?? true), (int) ($row['ttl'] ?? 1));
                $cambiados++;
            }

            if ($cambiados === 0 && $objetivos !== []) {
                $this->guardarEstado(false, $ip, 'No hay registros A con esos nombres');

                return ['ok' => false, 'message' => 'Cloudflare no tiene registros A para '.implode(', ', $objetivos).'.'];
            }

            $this->guardarEstado(true, $ip, null);

            return [
                'ok' => true,
                'ip' => $ip,
                'updated' => $cambiados,
                'message' => $cambiados === 0
                    ? 'Cloudflare ya apuntaba a '.$ip.'.'
                    : 'Cloudflare: '.$cambiados.' registro(s) A → '.$ip.'.',
            ];
        } catch (Throwable $e) {
            Log::warning('[cloudflare-dns] error', ['error' => $e->getMessage()]);
            $this->guardarEstado(false, $ip, $e->getMessage());

            return ['ok' => false, 'message' => 'Cloudflare: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok?: bool, ip?: string, error?: string, at?: string}
     */
    private function estadoGuardado(): array
    {
        $raw = FacturacionParametro::obtener('server_cf_dns_estado', '');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function guardarEstado(bool $ok, string $ip, ?string $error): void
    {
        try {
            FacturacionParametro::establecer(
                'server_cf_dns_estado',
                json_encode([
                    'ok' => $ok,
                    'ip' => $ip,
                    'error' => $error,
                    'at' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'Último cambio de origen Cloudflare del PC Server'
            );
        } catch (Throwable $e) {
            Log::warning('[cloudflare-dns] no se pudo guardar estado', ['error' => $e->getMessage()]);
        }
    }

    private function token(): string
    {
        return trim((string) config('services.cloudflare.token'));
    }

    private function accountId(): string
    {
        return trim((string) config('services.cloudflare.account_id'));
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token())
            ->acceptJson()
            ->timeout(20)
            ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]]);
    }

    private function zone(): string
    {
        return trim((string) config('services.cloudflare.zone', 'infinityispro.net'));
    }

    public function originTigo(): string
    {
        return trim((string) config('services.cloudflare.origin_tigo', '200.26.179.94'));
    }

    public function originUfinet(): string
    {
        return trim((string) config('services.cloudflare.origin_ufinet', '186.33.34.14'));
    }

    /**
     * @return list<string>
     */
    private function nombres(): array
    {
        $raw = strtolower(trim((string) config('services.cloudflare.record_names', '')));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function zoneId(): string
    {
        $resp = $this->http()->get('https://api.cloudflare.com/client/v4/zones', array_filter([
            'name' => $this->zone(),
            'account.id' => $this->accountId() !== '' ? $this->accountId() : null,
        ]));

        if (! $resp->successful()) {
            throw new \RuntimeException('HTTP '.$resp->status().' al leer zonas');
        }

        $id = (string) data_get($resp->json(), 'result.0.id', '');
        if ($id === '' || ! data_get($resp->json(), 'success')) {
            $msg = (string) data_get($resp->json(), 'errors.0.message', 'zona no encontrada');
            throw new \RuntimeException($msg);
        }

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrosA(string $zoneId): array
    {
        $resp = $this->http()->get('https://api.cloudflare.com/client/v4/zones/'.$zoneId.'/dns_records', [
            'type' => 'A',
            'per_page' => 100,
        ]);

        if (! $resp->successful() || ! data_get($resp->json(), 'success')) {
            $msg = (string) data_get($resp->json(), 'errors.0.message', 'HTTP '.$resp->status());
            throw new \RuntimeException($msg);
        }

        $rows = data_get($resp->json(), 'result', []);

        return is_array($rows) ? $rows : [];
    }

    private function patchRecord(string $zoneId, string $id, string $ip, bool $proxied, int $ttl): void
    {
        $resp = $this->http()->patch('https://api.cloudflare.com/client/v4/zones/'.$zoneId.'/dns_records/'.$id, [
                'type' => 'A',
                'content' => $ip,
                'proxied' => $proxied,
                'ttl' => $proxied ? 1 : max(1, $ttl),
            ]);

        if (! $resp->successful() || ! data_get($resp->json(), 'success')) {
            $msg = (string) data_get($resp->json(), 'errors.0.message', 'HTTP '.$resp->status());
            throw new \RuntimeException($msg);
        }
    }
}
