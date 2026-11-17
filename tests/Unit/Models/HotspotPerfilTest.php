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

    public function test_velocidad_legible_desde_el_rate_limit(): void
    {
        $this->assertSame(
            ['subida' => '5 Mbps', 'bajada' => '10 Mbps'],
            (new HotspotPerfil(['rate_limit' => '5M/10M']))->velocidad()
        );
        $this->assertSame(
            ['subida' => '512 kbps', 'bajada' => '1,5 Mbps'],
            (new HotspotPerfil(['rate_limit' => '512k/1500k 2M/3M 1M/2M 8/8']))->velocidad()
        );
        $this->assertSame(
            ['subida' => '1 Gbps', 'bajada' => '1 Gbps'],
            (new HotspotPerfil(['rate_limit' => '1G']))->velocidad()
        );
        $this->assertNull((new HotspotPerfil(['rate_limit' => '']))->velocidad());
        $this->assertNull((new HotspotPerfil(['rate_limit' => 'rapido']))->velocidad());
    }
}
