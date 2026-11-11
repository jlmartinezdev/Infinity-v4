<?php

namespace Tests\Feature;

use Tests\TestCase;

class HotspotCuentaMacTest extends TestCase
{
    public function test_una_mac_invalida_no_devuelve_cuenta(): void
    {
        $respuesta = $this->getJson('/api/hotspot/cuenta-por-mac?mac=no-es-mac');

        $respuesta->assertOk();
        $respuesta->assertJson(['username' => null]);
        $respuesta->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_sin_radius_una_mac_valida_queda_sin_cuenta(): void
    {
        $respuesta = $this->getJson('/api/hotspot/cuenta-por-mac?mac=F6:AB:C8:90:99:85');

        $respuesta->assertOk();
        $respuesta->assertJson(['username' => null]);
    }
}
