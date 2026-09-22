<?php

namespace Tests\Unit\Models;

use App\Models\FacturacionParametro;
use Carbon\Carbon;
use Tests\TestCase;

class FacturacionParametroCorteEfectivoTest extends TestCase
{
    public function test_mantiene_el_dia_cuando_no_es_domingo(): void
    {
        $fecha = FacturacionParametro::fechaCorteEfectivaParaMes(Carbon::parse('2026-08-01'), 6);

        $this->assertSame('2026-08-06', $fecha->toDateString());
        $this->assertFalse($fecha->isSunday());
    }

    public function test_pasa_al_lunes_si_el_dia_configurado_cae_domingo(): void
    {
        $fecha = FacturacionParametro::fechaCorteEfectivaParaMes(Carbon::parse('2026-09-01'), 6);

        $this->assertSame('2026-09-06', Carbon::parse('2026-09-06')->toDateString());
        $this->assertTrue(Carbon::parse('2026-09-06')->isSunday());
        $this->assertSame('2026-09-07', $fecha->toDateString());
        $this->assertTrue($fecha->isMonday());
    }

    public function test_si_el_ultimo_dia_del_mes_es_domingo_pasa_al_primero_del_siguiente(): void
    {
        $fecha = FacturacionParametro::fechaCorteEfectivaParaMes(Carbon::parse('2026-05-01'), 31);

        $this->assertTrue(Carbon::parse('2026-05-31')->isSunday());
        $this->assertSame('2026-06-01', $fecha->toDateString());
    }

    public function test_no_es_dia_efectivo_el_domingo_configurado(): void
    {
        $this->assertFalse(FacturacionParametro::esDiaCorteEfectivo(Carbon::parse('2026-09-06'), 6));
    }

    public function test_es_dia_efectivo_el_lunes_siguiente(): void
    {
        $this->assertTrue(FacturacionParametro::esDiaCorteEfectivo(Carbon::parse('2026-09-07'), 6));
    }

    public function test_es_dia_efectivo_el_primero_cuando_el_corte_del_mes_anterior_cayo_domingo(): void
    {
        $this->assertFalse(FacturacionParametro::esDiaCorteEfectivo(Carbon::parse('2026-05-31'), 31));
        $this->assertTrue(FacturacionParametro::esDiaCorteEfectivo(Carbon::parse('2026-06-01'), 31));
    }

    public function test_no_corta_un_dia_cualquiera_del_mes(): void
    {
        $this->assertFalse(FacturacionParametro::esDiaCorteEfectivo(Carbon::parse('2026-09-04'), 6));
        $this->assertTrue(FacturacionParametro::esDiaCorteEfectivo(Carbon::parse('2026-08-06'), 6));
    }
}
