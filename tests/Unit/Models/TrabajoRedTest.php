<?php

namespace Tests\Unit\Models;

use App\Models\Nodo;
use App\Models\Olt;
use App\Models\Router;
use App\Models\TrabajoRed;
use PHPUnit\Framework\TestCase;

class TrabajoRedTest extends TestCase
{
    public function test_tipos_incluye_los_cuatro_trabajos(): void
    {
        $this->assertSame([
            'mantenimiento' => 'Mantenimiento',
            'mejora' => 'Mejora',
            'ampliacion' => 'Ampliación',
            'reparo' => 'Reparo',
        ], TrabajoRed::tipos());
    }

    public function test_estados_siguen_el_flujo_de_tickets(): void
    {
        $this->assertArrayHasKey('pendiente', TrabajoRed::estados());
        $this->assertArrayHasKey('en_camino', TrabajoRed::estados());
        $this->assertArrayHasKey('en_proceso', TrabajoRed::estados());
        $this->assertArrayHasKey('resuelto', TrabajoRed::estados());
        $this->assertContains('pendiente', TrabajoRed::estadosAbiertos());
        $this->assertContains('resuelto', TrabajoRed::estadosCerrados());
    }

    public function test_nodo_id_desde_olt(): void
    {
        $olt = new Olt(['nodo_id' => 6, 'codigo' => 'OLT-UA']);
        $this->assertSame(6, TrabajoRed::nodoIdDesdeModelo($olt));
        $this->assertSame('OLT-UA', TrabajoRed::etiquetaDeModelo($olt, 'olt'));
    }

    public function test_nodo_id_desde_nodo(): void
    {
        $nodo = new Nodo(['descripcion' => 'N6- Union Agricola']);
        $nodo->nodo_id = 6;
        $this->assertSame(6, TrabajoRed::nodoIdDesdeModelo($nodo));
        $this->assertSame('N6- Union Agricola', TrabajoRed::etiquetaDeModelo($nodo, 'nodo'));
    }

    public function test_etiqueta_router(): void
    {
        $router = new Router(['nombre' => 'MK-N6-R1', 'ip' => '172.16.0.60', 'nodo_id' => 6]);
        $this->assertSame('MK-N6-R1', TrabajoRed::etiquetaDeModelo($router, 'router'));
        $this->assertSame(6, TrabajoRed::nodoIdDesdeModelo($router));
    }

    public function test_activo_tipos_cubre_la_red(): void
    {
        $this->assertSame(
            ['nodo', 'olt', 'router', 'caja_nap', 'salida_pon', 'ap'],
            array_keys(TrabajoRed::activoTipos())
        );
    }

    public function test_trazado_desde_hasta(): void
    {
        $trabajo = new TrabajoRed([
            'ubicacion_desde' => 'NAP-12',
            'url_ubicacion_desde' => 'https://maps.google.com/?q=-25.3,-57.6',
            'ubicacion_hasta' => 'NAP-20',
            'url_ubicacion_hasta' => '-25.4,-57.7',
        ]);
        $this->assertTrue($trabajo->tieneTrazado());
        $this->assertSame('NAP-12', $trabajo->etiquetaUbicacionDesde());
        $this->assertSame('NAP-20', $trabajo->etiquetaUbicacionHasta());
        $this->assertNotNull($trabajo->mapsUrlDesde());
        $this->assertNotNull($trabajo->mapsUrlHasta());
        $this->assertNotNull($trabajo->urlRutaMaps());
    }

    public function test_sin_trazado(): void
    {
        $trabajo = new TrabajoRed([]);
        $this->assertFalse($trabajo->tieneTrazado());
        $this->assertNull($trabajo->mapsUrlDesde());
        $this->assertNull($trabajo->urlRutaMaps());
    }
}
