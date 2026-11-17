<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\HotspotController;
use App\Models\Router;
use Tests\TestCase;

class HotspotEstadoRouterTest extends TestCase
{
    public function test_estado_del_router_para_el_selector(): void
    {
        $router = new Router(['estado' => Router::ESTADO_CONECTADO]);
        $this->assertSame(['clase' => 'is-ok', 'texto' => 'En línea'], HotspotController::estadoRouterVista($router));

        $router->estado = Router::ESTADO_DESCONECTADO;
        $this->assertSame(['clase' => 'is-down', 'texto' => 'Caído'], HotspotController::estadoRouterVista($router));

        $router->estado = null;
        $this->assertSame(['clase' => '', 'texto' => 'Sin datos'], HotspotController::estadoRouterVista($router));
    }
}
