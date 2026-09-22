<?php

namespace Tests\Unit\Support;

use App\Support\FacturaLimiteMes;
use Tests\TestCase;

class FacturaLimiteMesTest extends TestCase
{
    public function test_sin_tope_siempre_permite(): void
    {
        $r = FacturaLimiteMes::resultado(9_000_000, null, 40_000_000);

        $this->assertTrue($r['ok']);
        $this->assertNull($r['limite']);
        $this->assertNull($r['restante']);
        $this->assertNull($r['message']);
    }

    public function test_permite_si_cabe_en_el_restante(): void
    {
        $r = FacturaLimiteMes::resultado(5_000_000, 50_000_000, 40_000_000);

        $this->assertTrue($r['ok']);
        $this->assertSame(10_000_000.0, $r['restante']);
        $this->assertSame(80.0, $r['porcentaje']);
    }

    public function test_bloquea_si_supera_el_tope(): void
    {
        $r = FacturaLimiteMes::resultado(15_000_000, 50_000_000, 40_000_000);

        $this->assertFalse($r['ok']);
        $this->assertNotNull($r['message']);
        $this->assertStringContainsString('50.000.000', $r['message']);
        $this->assertStringContainsString('10.000.000', $r['message']);
    }

    public function test_permite_si_completa_exactamente_el_tope(): void
    {
        $r = FacturaLimiteMes::resultado(10_000_000, 50_000_000, 40_000_000);

        $this->assertTrue($r['ok']);
        $this->assertSame(10_000_000.0, $r['restante']);
    }
}
