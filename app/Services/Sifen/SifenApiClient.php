<?php

namespace App\Services\Sifen;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SifenApiClient
{
    private string $baseUrl;

    private string $token;

    private int $timeout;

    public function __construct()
    {
        $url = rtrim((string) config('sifen.api.url', ''), '/');
        if ($url !== '' && ! str_ends_with($url, '/api/v1')) {
            $url .= '/api/v1';
        }
        $this->baseUrl = $url;
        $this->token = (string) config('sifen.api.token', '');
        $this->timeout = max(30, (int) config('sifen.api.timeout', 120));
    }

    public function isConfigured(): bool
    {
        return config('sifen.api.enabled')
            && $this->baseUrl !== ''
            && $this->token !== '';
    }

    /**
     * @return array{ok: bool, mensaje: string, http_code?: int, latencia_ms?: int, status?: array<string, mixed>}
     */
    public function probarConexion(): array
    {
        if (! config('sifen.api.enabled')) {
            return [
                'ok' => false,
                'mensaje' => 'Modo API desactivado. Defina SIFEN_API_ENABLED=true en .env',
            ];
        }

        if ($this->baseUrl === '') {
            return ['ok' => false, 'mensaje' => 'Falta SIFEN_API_URL en .env'];
        }

        if ($this->token === '') {
            return ['ok' => false, 'mensaje' => 'Falta SIFEN_API_TOKEN en .env'];
        }

        $inicio = microtime(true);

        try {
            $status = $this->status();
            $latencia = (int) round((microtime(true) - $inicio) * 1000);

            return [
                'ok' => true,
                'mensaje' => 'Conexión con sifen-api OK.',
                'latencia_ms' => $latencia,
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'latencia_ms' => (int) round((microtime(true) - $inicio) * 1000),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function probarTlsRemoto(): array
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->baseUrl.'/sifen/test/tls');

            $json = $response->json();
            $resultado = is_array($json['resultado'] ?? null) ? $json['resultado'] : [];
            $sifenHttp = $resultado['httpCode'] ?? $resultado['http_code'] ?? null;

            if ($response->successful()) {
                return array_merge([
                    'ok' => true,
                    'mensaje' => $json['message'] ?? 'mTLS OK',
                    'api_http_code' => $response->status(),
                    'sifen_http_code' => $sifenHttp,
                ], $resultado);
            }

            $mensaje = $json['message'] ?? 'SIFEN rechazó mTLS vía API';
            if ($sifenHttp) {
                $mensaje .= ' (SIFEN HTTP '.$sifenHttp.')';
            }

            return array_merge([
                'ok' => false,
                'mensaje' => $mensaje,
                'api_http_code' => $response->status(),
                'sifen_http_code' => $sifenHttp,
            ], $resultado);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar con sifen-api: '.$e->getMessage()];
        }
    }

    public function urlPanelConfiguracion(): string
    {
        $raiz = preg_replace('#/api/v1$#', '', $this->baseUrl) ?? $this->baseUrl;

        return $raiz.'/configuracion/sifen';
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $respuesta = $this->json('GET', '/sifen/status');

        return is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function crearDocumento(array $payload): array
    {
        return $this->json('POST', '/documentos', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerDocumento(int $documentoId): array
    {
        $respuesta = $this->json('GET', '/documentos/'.$documentoId);

        return is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function preparar(int $documentoId): array
    {
        return $this->json('POST', '/documentos/'.$documentoId.'/preparar');
    }

    /**
     * @return array<string, mixed>
     */
    public function emitir(int $documentoId, bool $enviarSifen = true): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('API SIFEN no configurada (SIFEN_API_ENABLED, URL y TOKEN).');
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->baseUrl.'/documentos/'.$documentoId.'/emitir', [
                    'enviar_sifen' => $enviarSifen,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con sifen-api: '.$e->getMessage(), 0, $e);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Respuesta inválida de sifen-api (HTTP '.$response->status().').');
        }

        if ($response->failed() && ! isset($json['data'])) {
            throw new RuntimeException($json['message'] ?? 'Error en sifen-api (HTTP '.$response->status().').');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarPorReferencia(string $referencia): ?array
    {
        $respuesta = $this->json('GET', '/documentos', [
            'referencia_externa' => $referencia,
            'per_page' => 1,
        ]);

        $bloque = $respuesta['data'] ?? [];

        if (isset($bloque['data']) && is_array($bloque['data']) && isset($bloque['data'][0])) {
            return $bloque['data'][0];
        }

        if (is_array($bloque) && isset($bloque[0]) && is_array($bloque[0])) {
            return $bloque[0];
        }

        return null;
    }

    public function descargarXml(int $documentoId): string
    {
        return $this->descargarBinario('/documentos/'.$documentoId.'/xml');
    }

    public function descargarKude(int $documentoId): string
    {
        return $this->descargarBinario('/documentos/'.$documentoId.'/kude');
    }

    /**
     * @param  array<string, mixed>|null  $query
     * @return array<string, mixed>
     */
    private function json(string $method, string $path, ?array $body = null, ?array $query = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('API SIFEN no configurada (SIFEN_API_ENABLED, URL y TOKEN).');
        }

        $url = $this->baseUrl.$path;
        $request = Http::withToken($this->token)
            ->timeout($this->timeout)
            ->acceptJson();

        try {
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $query ?? []),
                'POST' => $request->post($url, $body ?? []),
                'PUT' => $request->put($url, $body ?? []),
                'DELETE' => $request->delete($url, $body ?? []),
                default => throw new RuntimeException('Método HTTP no soportado: '.$method),
            };
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con sifen-api en '.$url.': '.$e->getMessage(), 0, $e);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException(
                'Respuesta inválida de sifen-api (HTTP '.$response->status().').'
            );
        }

        if ($response->failed()) {
            $mensaje = $json['message'] ?? 'Error en sifen-api (HTTP '.$response->status().').';
            throw new RuntimeException($mensaje);
        }

        return $json;
    }

    private function descargarBinario(string $path): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('API SIFEN no configurada.');
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->get($this->baseUrl.$path);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo descargar desde sifen-api: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Descarga fallida (HTTP '.$response->status().').');
        }

        $contenido = $response->body();
        if ($contenido === '') {
            throw new RuntimeException('Archivo vacío recibido desde sifen-api.');
        }

        return $contenido;
    }
}
