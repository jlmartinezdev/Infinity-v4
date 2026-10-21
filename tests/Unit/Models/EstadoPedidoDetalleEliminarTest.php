<?php

namespace Tests\Unit\Models;

use App\Models\EstadoPedidoDetalle;
use Tests\TestCase;

class EstadoPedidoDetalleEliminarTest extends TestCase
{
    public function test_borra_el_estado_y_los_posteriores(): void
    {
        $this->assertSame([2, 3], EstadoPedidoDetalle::idsABorrarDesde([1, 2, 3], 2));
        $this->assertSame([3], EstadoPedidoDetalle::idsABorrarDesde([1, 2, 3], 3));
    }

    public function test_no_permite_borrar_el_primer_estado(): void
    {
        $this->assertNull(EstadoPedidoDetalle::idsABorrarDesde([1, 2, 3], 1));
        $this->assertNull(EstadoPedidoDetalle::idsABorrarDesde([1], 1));
    }

    public function test_no_borra_si_el_estado_no_esta_en_el_pedido(): void
    {
        $this->assertNull(EstadoPedidoDetalle::idsABorrarDesde([1, 2], 9));
        $this->assertNull(EstadoPedidoDetalle::idsABorrarDesde([], 2));
    }

    public function test_delete_usa_pedido_y_estado_como_clave(): void
    {
        $detalle = new EstadoPedidoDetalle;
        $detalle->pedido_id = 632;
        $detalle->estado_id = 2;

        $method = new \ReflectionMethod(EstadoPedidoDetalle::class, 'setKeysForSaveQuery');
        $query = EstadoPedidoDetalle::query();
        $method->invoke($detalle, $query);

        $sql = strtolower($query->toSql());
        $this->assertStringContainsString('pedido_id', $sql);
        $this->assertStringContainsString('estado_id', $sql);
    }
}
