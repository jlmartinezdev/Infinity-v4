<?php

namespace Tests\Feature;

use App\Services\Mcp\McpOAuthService;
use Tests\TestCase;

class McpOAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mcp.whatsapp.token' => 'token-mcp-de-prueba',
            'mcp.whatsapp.oauth_client_id' => 'infinity-gemini',
            'mcp.whatsapp.oauth_client_secret' => 'secreto-gemini-de-prueba',
            'mcp.whatsapp.oauth_redirect_hosts' => ['chatgpt.com', 'gemini.google.com'],
            'app.url' => 'https://infinityisppro.net',
        ]);
        $migracion = require base_path('database/migrations/2026_11_27_180000_create_mcp_oauth_tables.php');
        $migracion->up();
    }

    public function test_metadatos_y_rechazo_anuncian_oauth(): void
    {
        $recurso = $this->getJson('/oauth/protected-resource');
        $recurso->assertOk();
        $recurso->assertJsonPath('resource', 'https://infinityisppro.net/api/mcp/whatsapp');
        $recurso->assertJsonPath('authorization_servers.0', 'https://infinityisppro.net');

        $servidor = $this->getJson('/oauth/authorization-server');
        $servidor->assertOk();
        $servidor->assertJsonPath('code_challenge_methods_supported.0', 'S256');
        $servidor->assertJsonPath('registration_endpoint', 'https://infinityisppro.net/api/oauth/register');

        $rechazo = $this->postJson('/api/mcp/whatsapp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
        $rechazo->assertStatus(401);
        $this->assertStringContainsString('/oauth/protected-resource', (string) $rechazo->headers->get('WWW-Authenticate'));
    }

    public function test_codigo_pkce_habilita_el_mcp_y_un_verifier_malo_no(): void
    {
        $verifier = str_repeat('a', 50);
        $reto = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $redirect = 'https://gemini.google.com/callback';

        $codigo = app(McpOAuthService::class)->emitirCodigo(7, 'infinity-gemini', $redirect, $reto, null);
        $this->assertNotNull($codigo);

        $canje = $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $codigo,
            'redirect_uri' => $redirect,
            'code_verifier' => $verifier,
            'client_id' => 'infinity-gemini',
            'client_secret' => 'secreto-gemini-de-prueba',
        ]);
        $canje->assertOk();
        $acceso = (string) $canje->json('access_token');
        $this->assertStringStartsWith('inf_mcp_', $acceso);

        $lista = $this->postJson('/api/mcp/whatsapp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], ['Authorization' => 'Bearer '.$acceso]);
        $lista->assertOk();
        $nombres = collect($lista->json('result.tools'))->pluck('name');
        $this->assertTrue($nombres->contains('whatsapp_pendientes'));

        $otro = app(McpOAuthService::class)->emitirCodigo(7, 'infinity-gemini', $redirect, $reto, null);
        $mal = $this->postJson('/api/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $otro,
            'redirect_uri' => $redirect,
            'code_verifier' => str_repeat('b', 50),
            'client_id' => 'infinity-gemini',
            'client_secret' => 'secreto-gemini-de-prueba',
        ]);
        $mal->assertStatus(400);
    }

    public function test_registro_publico_rechaza_http(): void
    {
        $this->postJson('/api/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['http://chatgpt.com/callback'],
            'token_endpoint_auth_method' => 'none',
        ])->assertStatus(400);

        $ok = $this->postJson('/api/oauth/register', [
            'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/callback'],
            'token_endpoint_auth_method' => 'none',
        ]);
        $ok->assertCreated();
        $this->assertStringStartsWith('inf_cli_', (string) $ok->json('client_id'));
    }
}
