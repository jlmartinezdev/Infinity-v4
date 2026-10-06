<?php

namespace Tests\Unit\Support;

use App\Support\FacturaFechaTopeEmision;
use Carbon\Carbon;
use Tests\TestCase;

class FacturaFechaTopeEmisionTest extends TestCase
{
    public function test_sin_tope_siempre_permite(): void
    {
        $r = FacturaFechaTopeEmision::resultado(null, Carbon::parse('2026-10-20'));

        $this->assertTrue($r['ok']);
        $this->assertNull($r['message']);
        $this->assertFalse($r['vencida']);
    }

    public function test_permite_si_fecha_es_el_tope(): void
    {
        $r = FacturaFechaTopeEmision::resultado(
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-05')
        );

        $this->assertTrue($r['ok']);
        $this->assertNull($r['message']);
    }

    public function test_permite_si_fecha_es_anterior_al_tope(): void
    {
        $r = FacturaFechaTopeEmision::resultado(
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-01')
        );

        $this->assertTrue($r['ok']);
    }

    public function test_bloquea_si_fecha_es_posterior_al_tope(): void
    {
        $r = FacturaFechaTopeEmision::resultado(
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-06')
        );

        $this->assertFalse($r['ok']);
        $this->assertNotNull($r['message']);
        $this->assertStringContainsString('05/10/2026', $r['message']);
        $this->assertStringContainsString('06/10/2026', $r['message']);
        $this->assertStringContainsString('SIFEN', $r['message']);
        $this->assertStringContainsString('igual o anterior', $r['message']);
    }

    public function test_marca_vencida_si_hoy_paso_el_tope(): void
    {
        $r = FacturaFechaTopeEmision::resultado(
            Carbon::parse('2026-09-30'),
            Carbon::parse('2026-09-30'),
            Carbon::parse('2026-10-01')
        );

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['vencida']);
    }

    public function test_normalizar_vacio_es_null(): void
    {
        $this->assertNull(FacturaFechaTopeEmision::normalizar(''));
        $this->assertNull(FacturaFechaTopeEmision::normalizar('   '));
        $this->assertSame('2026-10-01', FacturaFechaTopeEmision::normalizar('2026-10-01'));
    }

    public function test_fecha_formulario_usa_el_menor_entre_hoy_y_tope(): void
    {
        $this->assertSame('2026-10-01', FacturaFechaTopeEmision::menorEntreHoyYTope('2026-10-01', null));
        $this->assertSame('2026-10-01', FacturaFechaTopeEmision::menorEntreHoyYTope('2026-10-01', ''));
        $this->assertSame('2026-10-01', FacturaFechaTopeEmision::menorEntreHoyYTope('2026-10-01', '2026-10-05'));
        $this->assertSame('2026-09-30', FacturaFechaTopeEmision::menorEntreHoyYTope('2026-10-01', '2026-09-30'));
        $this->assertSame('2026-10-01', FacturaFechaTopeEmision::menorEntreHoyYTope('2026-10-01', '2026-10-01'));
    }
}
