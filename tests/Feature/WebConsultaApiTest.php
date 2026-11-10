<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebConsultaApiTest extends TestCase
{
    public function test_sin_token_rechaza(): void
    {
        config(['web_consulta.token' => 'token-de-prueba']);

        $this->postJson('/api/v1/web/cuenta', ['documento' => '1234567'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_token_incorrecto_rechaza(): void
    {
        config(['web_consulta.token' => 'token-de-prueba']);

        $this->postJson('/api/v1/web/cuenta', ['documento' => '1234567'], [
            'Authorization' => 'Bearer otro-token',
        ])->assertUnauthorized();
    }

    public function test_documento_vacio_rechaza(): void
    {
        config(['web_consulta.token' => 'token-de-prueba']);

        $this->postJson('/api/v1/web/cuenta', ['documento' => ''], [
            'Authorization' => 'Bearer token-de-prueba',
        ])->assertStatus(422);
    }
}
