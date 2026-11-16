<?php

namespace Tests\Unit\Support;

use App\Support\ClaveOnuDescripcion;
use Tests\TestCase;

class ClaveOnuDescripcionTest extends TestCase
{
    public function test_el_guion_y_el_guion_bajo_dan_la_misma_clave(): void
    {
        $this->assertSame(
            ClaveOnuDescripcion::de('GILBERTO_MENDOZA_AGUIRRE'),
            ClaveOnuDescripcion::de('GILBERTO-MENDOZA-AGUIRRE')
        );
    }

    public function test_un_guion_al_final_tambien_busca_sin_ese_agregado(): void
    {
        $claves = ClaveOnuDescripcion::claves('GILBERTO_MENDOZA_AGUIRRE-ESCUELA');

        $this->assertContains('GILBERTOMENDOZAAGUIRRE', $claves);
        $this->assertContains('GILBERTOMENDOZAAGUIRREESCUELA', $claves);
    }

    public function test_un_guion_en_el_medio_del_nombre_se_conserva(): void
    {
        $claves = ClaveOnuDescripcion::claves('ESCUELA_GASORY-ROSA_PORTILLO');

        $this->assertSame(['ESCUELAGASORYROSAPORTILLO'], $claves);
    }

    public function test_un_nombre_corto_con_guion_no_pierde_el_apellido(): void
    {
        $claves = ClaveOnuDescripcion::claves('JUAN-PEREZ');

        $this->assertSame(['JUANPEREZ'], $claves);
    }

    public function test_escuela_casa_y_yuty_al_final_no_separan_al_cliente(): void
    {
        $this->assertContains(
            'ADDOCESARBENITEZVILLAR',
            ClaveOnuDescripcion::claves('ADDO_CESAR_BENITEZ_VILLAR_ESCUELA')
        );
        $this->assertContains(
            'EVERGUSTAVOCIBILS',
            ClaveOnuDescripcion::claves('EVER_GUSTAVO_CIBILS_CASA')
        );
        $this->assertContains(
            'CELSOROTELA',
            ClaveOnuDescripcion::claves('CELSO_ROTELA_-YUTY')
        );
    }
}
