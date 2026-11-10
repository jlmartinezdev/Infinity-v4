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

    public function test_tiempo_en_linea_legible(): void
    {
        $this->assertSame('45 s', HotspotDuracion::legible('45s'));
        $this->assertSame('30 min 47 s', HotspotDuracion::legible('30m47s'));
        $this->assertSame('30 min', HotspotDuracion::legible('30m'));
        $this->assertSame('2 h 4 min', HotspotDuracion::legible('2h4m10s'));
        $this->assertSame('1 d 3 h', HotspotDuracion::legible('1d3h20m'));
        $this->assertSame('1 d', HotspotDuracion::legible('1d'));
        $this->assertSame('—', HotspotDuracion::legible('—'));
        $this->assertSame('—', HotspotDuracion::legible(''));
    }

    public function test_hace_cuanto_fue_la_ultima_conexion(): void
    {
        $ahora = new \DateTimeImmutable('2026-11-10 12:00:00');

        $this->assertSame('recién', HotspotDuracion::haceCuanto($ahora->modify('-30 seconds'), $ahora));
        $this->assertSame('hace 12 min', HotspotDuracion::haceCuanto($ahora->modify('-12 minutes'), $ahora));
        $this->assertSame('hace 3 h', HotspotDuracion::haceCuanto($ahora->modify('-200 minutes'), $ahora));
        $this->assertSame('hace 2 d', HotspotDuracion::haceCuanto($ahora->modify('-50 hours'), $ahora));
        $this->assertSame('recién', HotspotDuracion::haceCuanto($ahora->modify('+5 minutes'), $ahora));
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
