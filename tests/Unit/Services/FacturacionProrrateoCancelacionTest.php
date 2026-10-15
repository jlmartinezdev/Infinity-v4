<?php

namespace Tests\Unit\Services;

use App\Services\FacturacionService;
use Carbon\Carbon;
use Tests\TestCase;

class FacturacionProrrateoCancelacionTest extends TestCase
{
    public function test_si_instalacion_es_este_mes_cuenta_dias_usados_desde_esa_fecha(): void
    {
        $detalle = FacturacionService::detalleProrrateoCancelacionMes(
            Carbon::parse('2026-10-20'),
            310000,
            Carbon::parse('2026-10-07')
        );

        $this->assertSame(14, $detalle['dias_usados']);
        $this->assertSame(31, $detalle['dias_en_mes']);
        $this->assertSame('2026-10-07', $detalle['periodo_desde']->toDateString());
        $this->assertSame(140000.0, $detalle['monto']);
    }

    public function test_instalacion_y_cancelacion_el_mismo_dia_cobra_un_dia(): void
    {
        $monto = FacturacionService::calcularMontoProrrateoCancelacionMes(
            Carbon::parse('2026-10-07'),
            310000,
            Carbon::parse('2026-10-07')
        );

        $this->assertSame(10000.0, $monto);
    }

    public function test_instalacion_de_otro_mes_cuenta_desde_el_dia_1(): void
    {
        $detalle = FacturacionService::detalleProrrateoCancelacionMes(
            Carbon::parse('2026-10-07'),
            310000,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame(7, $detalle['dias_usados']);
        $this->assertSame('2026-10-01', $detalle['periodo_desde']->toDateString());
        $this->assertSame(70000.0, $detalle['monto']);
    }

    public function test_sin_fecha_de_instalacion_cuenta_desde_el_dia_1(): void
    {
        $monto = FacturacionService::calcularMontoProrrateoCancelacionMes(
            Carbon::parse('2026-10-07'),
            310000
        );

        $this->assertSame(70000.0, $monto);
    }
}
