<?php

namespace Tests\Unit\Services\Red;

use App\Services\Red\EquipoObservacionInforme;
use PHPUnit\Framework\TestCase;

class EquipoObservacionInformeTest extends TestCase
{
    public function test_un_reclamo_o_dos_cortes_marcan_que_volvio_la_falla(): void
    {
        $this->assertSame(
            EquipoObservacionInforme::VEREDICTO_REINCIDIO,
            EquipoObservacionInforme::veredicto(0, null, null, 1, false)
        );
        $this->assertSame(
            EquipoObservacionInforme::VEREDICTO_REINCIDIO,
            EquipoObservacionInforme::veredicto(2, -20.0, null, 0, false)
        );
        $this->assertSame(
            EquipoObservacionInforme::VEREDICTO_REINCIDIO,
            EquipoObservacionInforme::veredicto(0, -28.4, null, 0, false)
        );
        $this->assertSame(
            EquipoObservacionInforme::VEREDICTO_REINCIDIO,
            EquipoObservacionInforme::veredicto(0, null, -80.0, 0, false)
        );
    }

    public function test_sin_falla_sigue_en_curso_y_al_vencer_queda_estable(): void
    {
        $this->assertSame(
            EquipoObservacionInforme::VEREDICTO_EN_CURSO,
            EquipoObservacionInforme::veredicto(1, -22.0, -60.0, 0, false)
        );
        $this->assertSame(
            EquipoObservacionInforme::VEREDICTO_ESTABLE,
            EquipoObservacionInforme::veredicto(1, -22.0, -60.0, 0, true)
        );
    }
}
