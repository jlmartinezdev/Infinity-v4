<?php

namespace Tests\Unit\Support;

use App\Support\StaffRecorridoGeometria;
use PHPUnit\Framework\TestCase;

class StaffRecorridoGeometriaTest extends TestCase
{
    public function test_un_milesimo_de_grado_de_latitud_ronda_ciento_once_metros(): void
    {
        $metros = StaffRecorridoGeometria::distanciaMetros(-25.2867, -57.6470, -25.2877, -57.6470);

        $this->assertEqualsWithDelta(111.2, $metros, 1.0);
    }

    public function test_no_guarda_si_se_movio_menos_del_minimo_o_el_gps_es_malo(): void
    {
        $ultimo = ['lat' => -25.2867, 'lng' => -57.6470];

        $this->assertTrue(StaffRecorridoGeometria::debeGuardar(null, -25.2867, -57.6470, null));
        $this->assertFalse(StaffRecorridoGeometria::debeGuardar($ultimo, -25.28675, -57.6470, 12));
        $this->assertTrue(StaffRecorridoGeometria::debeGuardar($ultimo, -25.2872, -57.6470, 20));
        $this->assertFalse(StaffRecorridoGeometria::debeGuardar(null, -25.2867, -57.6470, 180));
        $this->assertFalse(StaffRecorridoGeometria::debeGuardar(null, 0.0, 0.0, 5));
    }

    public function test_resumen_suma_tramos_visitas_y_recorta_el_trazo(): void
    {
        $puntos = [
            ['lat' => -25.2867, 'lng' => -57.6470, 'at' => '2026-11-11T08:00:00-03:00', 'visita_id' => 10],
            ['lat' => -25.2877, 'lng' => -57.6470, 'at' => '2026-11-11T08:10:00-03:00', 'visita_id' => 10],
            ['lat' => -25.2887, 'lng' => -57.6470, 'at' => '2026-11-11T08:20:00-03:00', 'visita_id' => 11],
            ['lat' => -25.2897, 'lng' => -57.6470, 'at' => '2026-11-11T08:30:00-03:00', 'visita_id' => null],
        ];

        $resumen = StaffRecorridoGeometria::armarResumen($puntos, true);

        $this->assertNotNull($resumen);
        $this->assertEqualsWithDelta(333.6, $resumen['distancia_m'], 3.0);
        $this->assertSame(4, $resumen['puntos_n']);
        $this->assertSame(2, $resumen['visitas']);
        $this->assertSame('2026-11-11T08:00:00-03:00', $resumen['inicio']);
        $this->assertSame('2026-11-11T08:30:00-03:00', $resumen['fin']);
        $this->assertCount(2, $resumen['traza']);
        $this->assertSame(-25.2867, $resumen['traza'][0]['lat']);
        $this->assertSame(-25.2897, $resumen['traza'][1]['lat']);
    }

    public function test_un_salto_imposible_no_infla_los_kilometros(): void
    {
        $resumen = StaffRecorridoGeometria::armarResumen([
            ['lat' => -25.2867, 'lng' => -57.6470, 'at' => '2026-11-11T08:00:00-03:00'],
            ['lat' => -26.2867, 'lng' => -57.6470, 'at' => '2026-11-11T08:01:00-03:00'],
        ], false);

        $this->assertNotNull($resumen);
        $this->assertSame(0.0, $resumen['distancia_m']);
        $this->assertCount(2, $resumen['traza']);
    }
}
