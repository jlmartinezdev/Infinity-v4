<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use Tests\TestCase;

class PlanEquivalentePorPrecioTest extends TestCase
{
    private function plan(int $id, float $precio): Plan
    {
        $plan = new Plan;
        $plan->plan_id = $id;
        $plan->precio = $precio;

        return $plan;
    }

    public function test_elige_el_precio_mas_cercano(): void
    {
        $elegido = Plan::equivalentePorPrecio(150000, [
            $this->plan(1, 80000),
            $this->plan(2, 160000),
            $this->plan(3, 300000),
        ]);

        $this->assertNotNull($elegido);
        $this->assertSame(2, (int) $elegido->plan_id);
    }

    public function test_empate_de_distancia_elige_el_mas_barato(): void
    {
        $elegido = Plan::equivalentePorPrecio(200000, [
            $this->plan(10, 250000),
            $this->plan(11, 150000),
        ]);

        $this->assertNotNull($elegido);
        $this->assertSame(11, (int) $elegido->plan_id);
    }

    public function test_sin_planes_devuelve_null(): void
    {
        $this->assertNull(Plan::equivalentePorPrecio(100000, []));
    }
}
