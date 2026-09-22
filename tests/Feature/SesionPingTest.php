<?php

namespace Tests\Feature;

use Tests\TestCase;

class SesionPingTest extends TestCase
{
    public function test_invitado_json_recibe_401(): void
    {
        $this->getJson('/sesion/ping')->assertUnauthorized();
    }
}
