<?php

namespace Tests\Feature;

use Tests\TestCase;

class PedidoEliminarEstadoTest extends TestCase
{
    public function test_ruta_y_controlador_quedan_restringidos_a_admin(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PedidoController.php'));

        $this->assertIsString($routes);
        $this->assertIsString($controller);
        $this->assertStringContainsString("->name('pedidos.eliminar-estado')", $routes ?: '');
        $this->assertStringContainsString('function eliminarEstado', $controller ?: '');
        $this->assertStringContainsString('esAdministrador()', $controller ?: '');
        $this->assertStringContainsString('No se puede eliminar el primer estado del pedido.', $controller ?: '');
    }

    public function test_el_boton_en_la_lista_es_solo_para_admin(): void
    {
        $vue = file_get_contents(resource_path('js/components/PedidosList.vue'));
        $index = file_get_contents(resource_path('views/pedidos/index.blade.php'));

        $this->assertIsString($vue);
        $this->assertIsString($index);
        $this->assertStringContainsString('puedeEliminarEstado(pedido, detalle)', $vue ?: '');
        $this->assertStringContainsString('if (!props.esAdmin || !props.eliminarEstadoUrl) return false', $vue ?: '');
        $this->assertStringContainsString("auth()->user()?->esAdministrador()", $index ?: '');
        $this->assertStringContainsString("'eliminarEstadoUrl'", $index ?: '');
    }

    public function test_pedido_sin_estado_tiene_boton_para_agregar(): void
    {
        $vue = file_get_contents(resource_path('js/components/PedidosList.vue'));
        $index = file_get_contents(resource_path('views/pedidos/index.blade.php'));

        $this->assertIsString($vue);
        $this->assertIsString($index);
        $this->assertStringContainsString('agregarEstadoInicial(pedido)', $vue ?: '');
        $this->assertStringContainsString('Agregar estado', $vue ?: '');
        $this->assertStringContainsString("'agregarEstadoUrl'", $index ?: '');
        $this->assertStringContainsString("pedidos.agregar-estado", $index ?: '');
    }
}
