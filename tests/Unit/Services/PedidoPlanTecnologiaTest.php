<?php

namespace Tests\Unit\Services;

use App\Services\PedidoNodoOpcionesService;
use Tests\TestCase;

class PedidoPlanTecnologiaTest extends TestCase
{
    public function test_al_pasar_a_gpon_no_conserva_el_plan_de_antena(): void
    {
        $servicio = new PedidoNodoOpcionesService();

        $resuelto = $servicio->resolverPlanAlAprobar(
            null,
            1,
            1,
            null,
            2,
            false,
        );

        $this->assertNull($resuelto['error']);
        $this->assertNull($resuelto['plan_id']);
    }

    public function test_confirmar_plan_exige_uno_de_la_tecnologia_del_nodo(): void
    {
        $servicio = new PedidoNodoOpcionesService();

        $resuelto = $servicio->resolverPlanAlAprobar(null, 1, 1, null, 2, true);

        $this->assertNull($resuelto['plan_id']);
        $this->assertNotNull($resuelto['error']);
    }

    public function test_acepta_el_plan_de_la_misma_tecnologia(): void
    {
        $servicio = new PedidoNodoOpcionesService();

        $resuelto = $servicio->resolverPlanAlAprobar(2, 1, 1, 1, 2, true);

        $this->assertSame(2, $resuelto['plan_id']);
        $this->assertNull($resuelto['error']);
    }

    public function test_rechaza_un_plan_elegido_de_otra_tecnologia(): void
    {
        $servicio = new PedidoNodoOpcionesService();

        $resuelto = $servicio->resolverPlanAlAprobar(1, null, 1, 2, null, true);

        $this->assertNull($resuelto['plan_id']);
        $this->assertNotNull($resuelto['error']);
    }
}
