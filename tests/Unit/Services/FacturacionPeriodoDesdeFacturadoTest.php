<?php

namespace Tests\Unit\Services;

use App\Models\Servicio;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Tests\TestCase;

class FacturacionPeriodoDesdeFacturadoTest extends TestCase
{
    public function test_usa_fecha_de_instalacion_cuando_cae_dentro_del_mes(): void
    {
        $servicio = new Servicio;
        $servicio->fecha_instalacion = '2026-08-24';

        $desde = FacturacionService::periodoDesdeFacturado(
            $servicio,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $this->assertSame('2026-08-24', $desde->toDateString());
    }

    public function test_mantiene_inicio_de_mes_si_instalacion_es_anterior(): void
    {
        $servicio = new Servicio;
        $servicio->fecha_instalacion = '2026-07-15';

        $desde = FacturacionService::periodoDesdeFacturado(
            $servicio,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $this->assertSame('2026-08-01', $desde->toDateString());
    }

    public function test_mantiene_inicio_de_mes_si_no_hay_instalacion(): void
    {
        $servicio = new Servicio;
        $servicio->fecha_instalacion = null;

        $desde = FacturacionService::periodoDesdeFacturado(
            $servicio,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $this->assertSame('2026-08-01', $desde->toDateString());
    }
}
