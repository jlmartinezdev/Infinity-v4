<?php

namespace Tests\Unit\Models;

use App\Models\HotspotPerfil;
use Tests\TestCase;

class HotspotPerfilTest extends TestCase
{
    public function test_etiqueta_incluye_cuota(): void
    {
        $sinCuota = new HotspotPerfil(['nombre' => 'wifi-casa']);
        $conCuota = new HotspotPerfil(['nombre' => 'wifi-casa', 'cuota_gb' => 10]);

        $this->assertSame('wifi-casa', $sinCuota->etiqueta());
        $this->assertSame('wifi-casa · 10 GB', $conCuota->etiqueta());
        $this->assertTrue($conCuota->tieneCuota());
        $this->assertFalse($sinCuota->tieneCuota());
    }
}
