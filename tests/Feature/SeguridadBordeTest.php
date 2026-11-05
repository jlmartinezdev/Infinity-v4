<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeguridadBordeTest extends TestCase
{
    public function test_registro_publico_no_existe(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Ataque',
            'email' => 'ataque@example.com',
            'password' => 'secreto',
            'license' => 'LIC-ISP-2025-ADMIN',
        ])->assertNotFound();
    }

    public function test_login_lleva_cabeceras_que_no_cambian_el_panel(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_whatsapp_sin_secreto_rechaza_el_post(): void
    {
        config(['whatsapp.app_secret' => '']);

        $this->postJson('/api/v1/webhooks/whatsapp', [
            'entry' => [],
        ])->assertForbidden();
    }

    public function test_whatsapp_con_firma_valida_acepta_el_post(): void
    {
        config(['whatsapp.app_secret' => 'secreto-prueba']);
        $body = '{"entry":[]}';
        $firma = 'sha256='.hash_hmac('sha256', $body, 'secreto-prueba');

        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $body)->assertOk();
    }
}
