<?php

namespace Tests\Unit\Support;

use App\Support\HotspotDuracion;
use Tests\TestCase;

class HotspotDuracionTest extends TestCase
{
    public function test_lee_duracion_de_mikrotik(): void
    {
        $this->assertSame(1157, HotspotDuracion::segundos('19m17s'));
        $this->assertSame(643, HotspotDuracion::segundos('10m43s'));
        $this->assertSame(3723, HotspotDuracion::segundos('1h2m3s'));
        $this->assertSame(1800, HotspotDuracion::segundos('00:30:00'));
        $this->assertNull(HotspotDuracion::segundos(''));
        $this->assertNull(HotspotDuracion::segundos('—'));
    }

    public function test_trial_muestra_lo_que_queda_y_el_arco_de_lo_usado(): void
    {
        $vista = HotspotDuracion::vistaRestante('19m17s', '10m43s');

        $this->assertNotNull($vista);
        $this->assertTrue($vista['tiene_cuota']);
        $this->assertSame('10', $vista['centro_num']);
        $this->assertSame('min', $vista['centro_unit']);
        $this->assertSame('quedan 10 min 43 s', $vista['etiqueta']);
        $this->assertEqualsWithDelta(64.3, $vista['porcentaje'], 0.1);
    }

    public function test_sin_tiempo_restante_no_arma_el_anillo(): void
    {
        $this->assertNull(HotspotDuracion::vistaRestante('5m', ''));
    }

    public function test_ultimo_minuto_queda_en_segundos(): void
    {
        $vista = HotspotDuracion::vistaRestante('29m20s', '40s');

        $this->assertSame('40', $vista['centro_num']);
        $this->assertSame('s', $vista['centro_unit']);
        $this->assertSame('quedan 40 s', $vista['etiqueta']);
    }
}
