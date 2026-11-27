<?php

namespace App\Services\Mcp;

use App\Models\McpOauthClient;
use App\Models\McpOauthCode;
use App\Models\McpOauthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Servidor OAuth 2.1 (código + PKCE) para el MCP de WhatsApp.
 * El token fijo de Claude sigue valiendo aparte.
 */
class McpOAuthService
{
    public function origen(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function recurso(): string
    {
        return $this->origen().'/api/mcp/whatsapp';
    }

    /** @return array<string, mixed> */
    public function metadatosRecurso(): array
    {
        return [
            'resource' => $this->recurso(),
            'authorization_servers' => [$this->origen()],
            'scopes_supported' => ['whatsapp'],
            'bearer_methods_supported' => ['header'],
            'resource_documentation' => $this->origen().'/oauth/protected-resource',
        ];
    }

    /** @return array<string, mixed> */
    public function metadatosServidor(): array
    {
        $origen = $this->origen();

        return [
            'issuer' => $origen,
            'authorization_endpoint' => $origen.'/oauth/authorize',
            'token_endpoint' => $origen.'/api/oauth/token',
            'registration_endpoint' => $origen.'/api/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
            'scopes_supported' => ['whatsapp'],
        ];
    }

    public function cabeceraNoAutorizado(): string
    {
        return 'Bearer realm="infinity-whatsapp", resource_metadata="'.$this->origen().'/oauth/protected-resource"';
    }

    /**
     * @return array{client_id: string, client_secret?: string}
     */
    public function registrar(array $datos): array
    {
        $uris = $this->urisHttps($datos['redirect_uris'] ?? []);
        if ($uris === []) {
            throw new \InvalidArgumentException('redirect_uri');
        }

        $metodo = (string) ($datos['token_endpoint_auth_method'] ?? 'none');
        if (! in_array($metodo, ['none', 'client_secret_post', 'client_secret_basic'], true)) {
            $metodo = 'none';
        }

        $secreto = null;
        $hash = null;
        if ($metodo !== 'none') {
            $secreto = Str::random(48);
            $hash = Hash::make($secreto);
        }

        $nombre = Str::limit(trim(strip_tags((string) ($datos['client_name'] ?? 'Conector'))), 80, '');
        if ($nombre === '') {
            $nombre = 'Conector';
        }

        $clientId = 'inf_cli_'.Str::lower(Str::random(24));
        McpOauthClient::query()->create([
            'client_id' => $clientId,
            'client_secret_hash' => $hash,
            'client_name' => $nombre,
            'redirect_uris' => $uris,
            'token_endpoint_auth_method' => $metodo,
        ]);

        $salida = [
            'client_id' => $clientId,
            'client_id_issued_at' => time(),
            'client_secret_expires_at' => 0,
            'client_name' => $nombre,
            'redirect_uris' => $uris,
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $metodo,
            'scope' => 'whatsapp',
        ];
        if ($secreto !== null) {
            $salida['client_secret'] = $secreto;
        }

        return $salida;
    }

    /**
     * @return array{client_id?: string, nombre?: string, redirect_uri?: string, error?: string}
     */
    public function prepararAutorizacion(string $clientId, string $redirectUri, string $reto, string $metodoReto, ?string $resource = null): array
    {
        if ($metodoReto !== 'S256' || ! $this->retoValido($reto)) {
            return ['error' => 'El conector no envió la verificación PKCE (S256).'];
        }

        $cliente = $this->resolverCliente($clientId);
        if ($cliente === null) {
            return ['error' => 'El conector no está registrado.'];
        }
        if (! $this->redirectPermitido($cliente, $redirectUri)) {
            $host = strtolower((string) parse_url($redirectUri, PHP_URL_HOST));

            return ['error' => 'La dirección de retorno no está permitida'.($host !== '' ? ' ('.$host.').' : '.')];
        }
        if (! $this->resourceAceptado($resource)) {
            return ['error' => 'El recurso pedido no es el WhatsApp de Infinity.'];
        }

        return [
            'client_id' => $cliente['client_id'],
            'nombre' => $cliente['nombre'],
            'redirect_uri' => $redirectUri,
        ];
    }

    public function emitirCodigo(int $usuarioId, string $clientId, string $redirectUri, string $reto, ?string $resource): ?string
    {
        $cliente = $this->resolverCliente($clientId);
        if ($cliente === null || ! $this->redirectPermitido($cliente, $redirectUri) || ! $this->resourceAceptado($resource)) {
            return null;
        }

        $codigo = Str::random(48);
        McpOauthCode::query()->create([
            'code_hash' => hash('sha256', $codigo),
            'client_id' => $clientId,
            'usuario_id' => $usuarioId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $reto,
            'scope' => 'whatsapp',
            'resource' => $resource,
            'expires_at' => now()->addMinutes(2),
        ]);

        return $codigo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function canjear(Request $request): ?array
    {
        $tipo = (string) $request->input('grant_type');
        if ($tipo === 'refresh_token') {
            return $this->renovar((string) $request->input('refresh_token'), $request);
        }
        if ($tipo !== 'authorization_code') {
            return null;
        }

        $codigo = (string) $request->input('code');
        $verifier = (string) $request->input('code_verifier');
        $redirect = (string) $request->input('redirect_uri');
        $clientId = $this->clientIdDe($request);
        if ($codigo === '' || $verifier === '' || $redirect === '' || $clientId === '') {
            return null;
        }

        $cliente = $this->autenticarCliente($request, $clientId);
        if ($cliente === null) {
            return null;
        }

        $fila = null;
        DB::transaction(function () use ($codigo, &$fila) {
            $fila = McpOauthCode::query()
                ->where('code_hash', hash('sha256', $codigo))
                ->lockForUpdate()
                ->first();
            if ($fila) {
                $fila->delete();
            }
        });

        if (! $fila instanceof McpOauthCode || $fila->expires_at->isPast()) {
            return null;
        }
        if (! $this->igual($fila->client_id, $clientId) || ! $this->igual($fila->redirect_uri, $redirect)) {
            return null;
        }
        if (! $this->pkceCoincide($verifier, $fila->code_challenge)) {
            return null;
        }

        return $this->emitirTokens($clientId, (int) $fila->usuario_id);
    }

    public function usuarioIdDeAccessToken(string $token): ?int
    {
        if (! str_starts_with($token, 'inf_mcp_')) {
            return null;
        }

        $fila = McpOauthToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $fila instanceof McpOauthToken || $fila->expires_at->isPast()) {
            return null;
        }

        return (int) $fila->usuario_id;
    }

    /**
     * @return array{client_id: string, nombre: string, publico: bool, secret_hash: ?string, redirect_uris: array<int, string>, hosts: array<int, string>}|null
     */
    private function resolverCliente(string $clientId): ?array
    {
        $fijoId = (string) config('mcp.whatsapp.oauth_client_id');
        $fijoSecreto = (string) config('mcp.whatsapp.oauth_client_secret');
        if ($fijoId !== '' && $this->igual($fijoId, $clientId) && $fijoSecreto !== '') {
            return [
                'client_id' => $fijoId,
                'nombre' => 'ChatGPT o Gemini',
                'publico' => false,
                'secret_hash' => null,
                'redirect_uris' => [],
                'hosts' => $this->hostsPermitidos(),
            ];
        }

        if ($this->esClienteChatgpt($clientId)) {
            $documento = $this->documentoChatgpt($clientId);
            if ($documento === null) {
                return null;
            }

            return [
                'client_id' => $clientId,
                'nombre' => Str::limit((string) ($documento['client_name'] ?? 'ChatGPT'), 80, ''),
                'publico' => true,
                'secret_hash' => null,
                'redirect_uris' => $this->urisHttps($documento['redirect_uris'] ?? []),
                'hosts' => [],
            ];
        }

        $fila = McpOauthClient::query()->where('client_id', $clientId)->first();
        if (! $fila instanceof McpOauthClient) {
            return null;
        }

        return [
            'client_id' => $fila->client_id,
            'nombre' => $fila->client_name,
            'publico' => $fila->token_endpoint_auth_method === 'none',
            'secret_hash' => $fila->client_secret_hash,
            'redirect_uris' => array_values(array_filter((array) $fila->redirect_uris)),
            'hosts' => [],
        ];
    }

    /**
     * @param  array{client_id: string, nombre: string, publico: bool, secret_hash: ?string, redirect_uris: array<int, string>, hosts: array<int, string>}  $cliente
     */
    private function autenticarCliente(Request $request, string $clientId): ?array
    {
        $cliente = $this->resolverCliente($clientId);
        if ($cliente === null) {
            return null;
        }
        if ($cliente['publico']) {
            return $cliente;
        }

        $secreto = $this->secretoDe($request);
        if ($secreto === '') {
            return null;
        }

        $fijoId = (string) config('mcp.whatsapp.oauth_client_id');
        $fijoSecreto = (string) config('mcp.whatsapp.oauth_client_secret');
        if ($fijoId !== '' && $this->igual($fijoId, $clientId)) {
            return $this->igual($fijoSecreto, $secreto) ? $cliente : null;
        }

        if ($cliente['secret_hash'] === null || ! Hash::check($secreto, $cliente['secret_hash'])) {
            return null;
        }

        return $cliente;
    }

    /**
     * @param  array{redirect_uris: array<int, string>, hosts: array<int, string>}  $cliente
     */
    private function redirectPermitido(array $cliente, string $uri): bool
    {
        if (! $this->uriHttps($uri)) {
            return false;
        }
        if (in_array($uri, $cliente['redirect_uris'], true)) {
            return true;
        }

        $host = strtolower((string) parse_url($uri, PHP_URL_HOST));

        return $host !== '' && in_array($host, $cliente['hosts'], true);
    }

    private function resourceAceptado(?string $resource): bool
    {
        if ($resource === null || $resource === '') {
            return true;
        }

        return rtrim($resource, '/') === rtrim($this->recurso(), '/');
    }

    private function igual(string $a, string $b): bool
    {
        return strlen($a) === strlen($b) && hash_equals($a, $b);
    }

    private function pkceCoincide(string $verifier, string $reto): bool
    {
        if (strlen($verifier) < 43 || strlen($verifier) > 128) {
            return false;
        }

        $calculado = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($reto, $calculado);
    }

    private function retoValido(string $reto): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-_]{43,128}$/', $reto);
    }

    /** @return array<string, mixed>|null */
    private function renovar(string $refresh, Request $request): ?array
    {
        if (! str_starts_with($refresh, 'inf_mcr_')) {
            return null;
        }
        $clientId = $this->clientIdDe($request);
        if ($this->autenticarCliente($request, $clientId) === null) {
            return null;
        }

        $fila = null;
        DB::transaction(function () use ($refresh, &$fila) {
            $fila = McpOauthToken::query()
                ->where('refresh_hash', hash('sha256', $refresh))
                ->lockForUpdate()
                ->first();
            if ($fila) {
                $fila->refresh_hash = null;
                $fila->save();
            }
        });

        if (! $fila instanceof McpOauthToken || ! $this->igual($fila->client_id, $clientId)) {
            return null;
        }
        if ($fila->refresh_expires_at === null || $fila->refresh_expires_at->isPast()) {
            return null;
        }

        return $this->emitirTokens($clientId, (int) $fila->usuario_id);
    }

    /** @return array<string, mixed> */
    private function emitirTokens(string $clientId, int $usuarioId): array
    {
        $acceso = 'inf_mcp_'.Str::random(48);
        $refresh = 'inf_mcr_'.Str::random(48);
        McpOauthToken::query()->create([
            'token_hash' => hash('sha256', $acceso),
            'refresh_hash' => hash('sha256', $refresh),
            'client_id' => $clientId,
            'usuario_id' => $usuarioId,
            'scope' => 'whatsapp',
            'expires_at' => now()->addHours(8),
            'refresh_expires_at' => now()->addDays(30),
        ]);

        return [
            'access_token' => $acceso,
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'refresh_token' => $refresh,
            'scope' => 'whatsapp',
        ];
    }

    private function clientIdDe(Request $request): string
    {
        $basico = $this->parBasico($request);

        return $basico['id'] !== '' ? $basico['id'] : (string) $request->input('client_id');
    }

    private function secretoDe(Request $request): string
    {
        $basico = $this->parBasico($request);

        return $basico['secret'] !== '' ? $basico['secret'] : (string) $request->input('client_secret');
    }

    /** @return array{id: string, secret: string} */
    private function parBasico(Request $request): array
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Basic ')) {
            return ['id' => '', 'secret' => ''];
        }
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || ! str_contains($decoded, ':')) {
            return ['id' => '', 'secret' => ''];
        }
        [$id, $secret] = explode(':', $decoded, 2);

        return ['id' => $id, 'secret' => $secret];
    }

    /** @param  mixed  $uris
     * @return array<int, string>
     */
    private function urisHttps(mixed $uris): array
    {
        if (! is_array($uris)) {
            return [];
        }
        $ok = [];
        foreach ($uris as $uri) {
            if (! is_string($uri) || ! $this->uriHttps($uri)) {
                continue;
            }
            $ok[] = $uri;
            if (count($ok) >= 8) {
                break;
            }
        }

        return array_values(array_unique($ok));
    }

    private function uriHttps(string $uri): bool
    {
        if (strlen($uri) > 500 || str_contains($uri, '#') || str_contains($uri, '\\')) {
            return false;
        }
        $partes = parse_url($uri);

        return is_array($partes)
            && ($partes['scheme'] ?? '') === 'https'
            && ($partes['host'] ?? '') !== ''
            && ! isset($partes['user']);
    }

    /** @return array<int, string> */
    private function hostsPermitidos(): array
    {
        $lista = config('mcp.whatsapp.oauth_redirect_hosts', []);

        return is_array($lista) ? array_values($lista) : [];
    }

    private function esClienteChatgpt(string $clientId): bool
    {
        $partes = parse_url($clientId);
        if (! is_array($partes)) {
            return false;
        }
        $host = strtolower((string) ($partes['host'] ?? ''));
        $path = (string) ($partes['path'] ?? '');

        return ($partes['scheme'] ?? '') === 'https'
            && $host === 'chatgpt.com'
            && str_starts_with($path, '/oauth/')
            && str_ends_with($path, '.json')
            && ! isset($partes['user'])
            && ! isset($partes['query']);
    }

    /** @return array<string, mixed>|null */
    private function documentoChatgpt(string $url): ?array
    {
        return Cache::remember('mcp_cimd_'.hash('sha256', $url), 600, function () use ($url) {
            try {
                $respuesta = Http::timeout(4)
                    ->withOptions(['allow_redirects' => false])
                    ->acceptJson()
                    ->get($url);
            } catch (\Throwable) {
                return null;
            }
            if (! $respuesta->ok()) {
                return null;
            }
            $json = $respuesta->json();

            return is_array($json) ? $json : null;
        });
    }
}
