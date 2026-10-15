<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrabajoRedGuestTest extends TestCase
{
    public function test_invitado_no_entra_al_listado(): void
    {
        $this->get('/trabajos-red')->assertRedirect();
    }

    public function test_invitado_no_crea(): void
    {
        $this->get('/trabajos-red/create')->assertRedirect();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/trabajos-red', [])
            ->assertRedirect();
    }
}
