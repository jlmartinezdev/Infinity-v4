<?php

namespace Tests\Feature;

use App\Services\WhatsApp\WhatsAppService;
use Tests\TestCase;

class McpWhatsappTest extends TestCase
{
    private const TOKEN = 'token-mcp-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mcp.whatsapp.token' => self::TOKEN]);
    }

    public function test_sin_token_no_entra(): void
    {
        $this->postJson('/api/mcp/whatsapp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
        ])->assertStatus(401);
    }

    public function test_lista_las_herramientas(): void
    {
        $nombres = $this->postJson('/api/mcp/whatsapp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertOk()
            ->json('result.tools');

        $this->assertSame(
            ['whatsapp_pendientes', 'whatsapp_hilo', 'whatsapp_contexto', 'whatsapp_enviar'],
            array_column($nombres, 'name')
        );
    }

    public function test_initialize_pide_confirmacion_antes_de_enviar(): void
    {
        $instrucciones = $this->postJson('/api/mcp/whatsapp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-03-26'],
        ], ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertOk()
            ->assertHeader('Mcp-Session-Id')
            ->json('result.instructions');

        $this->assertStringContainsString('confirmar=true', $instrucciones);
    }

    public function test_enviar_sin_confirmar_no_llama_a_whatsapp(): void
    {
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldNotReceive('sendText');
        });

        $texto = $this->postJson('/api/mcp/whatsapp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'whatsapp_enviar',
                'arguments' => [
                    'telefono' => '595981000000',
                    'texto' => 'Hola, le escribimos de Interplus.',
                    'confirmar' => false,
                ],
            ],
        ], ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertOk()
            ->json('result.content.0.text');

        $this->assertStringContainsString('sin_confirmacion', $texto);
        $this->assertStringContainsString('No se envió', $texto);
    }
}
