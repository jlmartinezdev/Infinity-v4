<?php

namespace Tests\Unit\Models;

use App\Models\Servicio;
use Carbon\Carbon;
use Tests\TestCase;

class ServicioAcuerdoFacturacionTest extends TestCase
{
    public function test_sin_acuerdo_no_excluye_el_periodo(): void
    {
        $servicio = new Servicio;
        $servicio->acuerdo_tipo = Servicio::ACUERDO_TIPO_NINGUNO;

        $this->assertFalse($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        ));
    }

    public function test_internet_libre_excluye_cualquier_periodo(): void
    {
        $servicio = new Servicio;
        $servicio->acuerdo_tipo = Servicio::ACUERDO_TIPO_LIBRE;

        $this->assertTrue($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        ));
    }

    public function test_meses_sin_facturar_cubre_el_rango_desde_acuerdo_desde(): void
    {
        $servicio = new Servicio;
        $servicio->acuerdo_tipo = Servicio::ACUERDO_TIPO_MESES;
        $servicio->acuerdo_meses = 2;
        $servicio->acuerdo_desde = '2026-09-03';

        $this->assertTrue($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        ));
        $this->assertTrue($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31')
        ));
        $this->assertFalse($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30')
        ));
    }

    public function test_meses_sin_facturar_usa_fecha_instalacion_si_no_hay_desde(): void
    {
        $servicio = new Servicio;
        $servicio->acuerdo_tipo = Servicio::ACUERDO_TIPO_MESES;
        $servicio->acuerdo_meses = 1;
        $servicio->acuerdo_desde = null;
        $servicio->fecha_instalacion = '2026-09-03';

        $this->assertTrue($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        ));
        $this->assertFalse($servicio->acuerdoAplicaEnPeriodo(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31')
        ));
    }
}
