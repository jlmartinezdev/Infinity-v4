<?php

namespace Tests\Unit\Services;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Services\ServicioImportarService;
use PHPUnit\Framework\TestCase;

class ServicioImportarServiceTest extends TestCase
{
    public function test_prioriza_el_gps_del_servicio(): void
    {
        $servicio = new Servicio(['url_ubicacion' => 'https://maps.google.com/?q=-25.3,-57.6']);
        $servicio->setRelation('pedido', new Pedido(['maps_gps' => 'https://maps.app.goo.gl/pedido']));
        $origen = new Cliente(['url_ubicacion' => 'https://maps.app.goo.gl/cliente']);

        $this->assertSame(
            'https://maps.google.com/?q=-25.3,-57.6',
            ServicioImportarService::resolverUrlUbicacion($servicio, $origen)
        );
    }

    public function test_usa_el_gps_del_pedido_si_el_servicio_no_tiene(): void
    {
        $servicio = new Servicio(['url_ubicacion' => null]);
        $servicio->setRelation('pedido', new Pedido(['maps_gps' => 'https://maps.app.goo.gl/pedido']));
        $origen = new Cliente(['url_ubicacion' => 'https://maps.app.goo.gl/cliente']);

        $this->assertSame(
            'https://maps.app.goo.gl/pedido',
            ServicioImportarService::resolverUrlUbicacion($servicio, $origen)
        );
    }

    public function test_cae_al_gps_del_cliente_de_origen(): void
    {
        $servicio = new Servicio(['url_ubicacion' => '']);
        $servicio->setRelation('pedido', new Pedido(['maps_gps' => '']));
        $origen = new Cliente(['url_ubicacion' => 'https://maps.app.goo.gl/cliente']);

        $this->assertSame(
            'https://maps.app.goo.gl/cliente',
            ServicioImportarService::resolverUrlUbicacion($servicio, $origen)
        );
    }

    public function test_sin_ubicacion_devuelve_nulo(): void
    {
        $servicio = new Servicio(['url_ubicacion' => null]);
        $servicio->setRelation('pedido', null);
        $servicio->setRelation('cliente', null);
        $origen = new Cliente(['url_ubicacion' => null]);

        $this->assertNull(ServicioImportarService::resolverUrlUbicacion($servicio, $origen));
    }
}
