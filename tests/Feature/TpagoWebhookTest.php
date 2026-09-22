<?php

namespace Tests\Feature;

use App\Services\Tpago\TpagoCallbackService;
use Tests\TestCase;

class TpagoWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tpago.enabled' => true,
            'tpago.verify_ip' => false,
            'tpago.callback_user' => 'tpago_callback',
            'tpago.callback_password' => 'secret-callback',
            'tpago.public_key' => 'apps/publicTestKey',
            'tpago.private_key' => 'private$TestKey',
        ]);

        $mock = \Mockery::mock(TpagoCallbackService::class);
        $mock->shouldReceive('handle')->andReturn([
            'handled' => true,
            'link_id' => null,
            'cobro_id' => null,
            'message' => 'ok',
        ]);
        $this->app->instance(TpagoCallbackService::class, $mock);
        $this->app->forgetInstance(\App\Http\Controllers\Api\V1\TpagoWebhookController::class);
    }

    public function test_sin_auth_rechaza(): void
    {
        $this->postJson('/api/v1/webhooks/tpago', ['status' => 'confirmed'])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('messages.0.key', 'ConfirmedError');
    }

    public function test_acepta_basic_auth_del_panel(): void
    {
        $this->withBasicAuth('tpago_callback', 'secret-callback')
            ->postJson('/api/v1/webhooks/tpago', [
                'payment' => [
                    'hook_alias' => 'XXXX',
                    'status' => 'confirmed',
                    'response_code' => '00',
                    'amount' => 5000,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('messages.0.key', 'Confirmed');
    }

    public function test_acepta_basic_auth_con_claves_tpago(): void
    {
        $this->withBasicAuth('apps/publicTestKey', 'private$TestKey')
            ->postJson('/api/v1/webhooks/tpago', [
                'payment' => [
                    'hook_alias' => 'XXXX',
                    'status' => 'confirmed',
                    'response_code' => '00',
                    'amount' => 5000,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_auth_incorrecta_rechaza(): void
    {
        $this->withBasicAuth('apps/otra', 'clave')
            ->postJson('/api/v1/webhooks/tpago', ['status' => 'confirmed'])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }

    public function test_sin_auth_acepta_cuerpo_tpago(): void
    {
        $this->postJson('/api/v1/webhooks/tpago', [
            'payment' => [
                'hook_alias' => 'PSHLA13790',
                'status' => 'confirmed',
                'response_code' => '00',
                'amount' => 5000,
                'ticket_number' => '5723103463',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('messages.0.key', 'Confirmed');
    }

    public function test_get_deja_log_y_responde_ok(): void
    {
        $this->getJson('/api/v1/webhooks/tpago')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
