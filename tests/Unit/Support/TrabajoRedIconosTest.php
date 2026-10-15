<?php

namespace Tests\Unit\Support;

use App\Support\TrabajoRedIconos;
use PHPUnit\Framework\TestCase;

class TrabajoRedIconosTest extends TestCase
{
    public function test_tipo_ampliacion_usa_icono_de_plan(): void
    {
        $this->assertSame('plan', TrabajoRedIconos::claveTipo('ampliacion'));
        $svg = TrabajoRedIconos::svgTipo('ampliacion');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('currentColor', $svg);
        $this->assertStringNotContainsString('📈', $svg);
    }

    public function test_activo_salida_pon_usa_fibra(): void
    {
        $this->assertSame('fibra', TrabajoRedIconos::claveActivo('salida_pon'));
        $this->assertStringContainsString('<svg', TrabajoRedIconos::svgActivo('salida_pon'));
    }

    public function test_pin_y_flag_son_lineales(): void
    {
        foreach (['pin', 'flag', 'map', 'alerta'] as $clave) {
            $svg = TrabajoRedIconos::svg($clave);
            $this->assertStringContainsString('stroke="currentColor"', $svg);
        }
    }
}
