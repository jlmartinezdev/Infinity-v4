<?php

namespace Tests\Unit\Services;

use App\Services\FacturacionService;
use Carbon\Carbon;
use Tests\TestCase;

class FacturacionCambioPlanAccionTest extends TestCase
{
    public function test_el_dia_1_no_prorratea(): void
    {
        $this->assertSame(
            'omitir_dia1',
            FacturacionService::accionFacturaCambioPlan(1, false, 100000, 150000, false)
        );
    }

    public function test_baja_con_mes_completo_pago_no_factura(): void
    {
        $this->assertSame(
            'omitir_baja_pagada',
            FacturacionService::accionFacturaCambioPlan(15, true, 150000, 100000, false)
        );
    }

    public function test_suba_con_mes_completo_pago_factura_el_restante(): void
    {
        $this->assertSame(
            'crear_restante',
            FacturacionService::accionFacturaCambioPlan(15, true, 100000, 150000, true)
        );
    }

    public function test_sin_pago_adelantado_crea_o_edita_el_prorrateo(): void
    {
        $this->assertSame(
            'crear',
            FacturacionService::accionFacturaCambioPlan(8, false, 100000, 80000, false)
        );
        $this->assertSame(
            'editar',
            FacturacionService::accionFacturaCambioPlan(8, false, 100000, 150000, true)
        );
    }

    public function test_el_restante_de_una_suba_es_la_diferencia_de_los_dias_que_faltan(): void
    {
        $resto = FacturacionService::calcularMontoRestanteSubaPlan(
            Carbon::parse('2026-11-16'),
            100000,
            150000
        );

        $this->assertSame(15, $resto['dias_plan_nuevo']);
        $this->assertSame(25000.0, $resto['monto']);
    }
}
