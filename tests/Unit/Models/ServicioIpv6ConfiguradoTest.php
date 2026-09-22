<?php

namespace Tests\Unit\Models;

use App\Models\Servicio;
use Tests\TestCase;

class ServicioIpv6ConfiguradoTest extends TestCase
{
    public function test_se_castea_a_boolean(): void
    {
        $servicio = new Servicio(['ipv6_configurado' => 1]);
        $this->assertTrue($servicio->ipv6_configurado);

        $servicio->ipv6_configurado = 0;
        $this->assertFalse($servicio->ipv6_configurado);
    }

    public function test_esta_en_fillable(): void
    {
        $this->assertContains('ipv6_configurado', (new Servicio)->getFillable());
    }
}
