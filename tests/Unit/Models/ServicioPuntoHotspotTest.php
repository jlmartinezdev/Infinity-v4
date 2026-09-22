<?php

namespace Tests\Unit\Models;

use App\Models\Servicio;
use Tests\TestCase;

class ServicioPuntoHotspotTest extends TestCase
{
    public function test_se_castea_a_boolean(): void
    {
        $servicio = new Servicio(['punto_hotspot' => 1]);
        $this->assertTrue($servicio->punto_hotspot);

        $servicio->punto_hotspot = 0;
        $this->assertFalse($servicio->punto_hotspot);
    }

    public function test_esta_en_fillable(): void
    {
        $this->assertContains('punto_hotspot', (new Servicio)->getFillable());
    }
}
