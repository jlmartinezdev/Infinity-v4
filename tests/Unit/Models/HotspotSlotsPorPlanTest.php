<?php

namespace Tests\Unit\Models;

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use App\Services\RadiusHotspotSyncService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class HotspotSlotsPorPlanTest extends TestCase
{
    private function servicio(?string $plan, string $estado = Servicio::ESTADO_ACTIVO): Servicio
    {
        $servicio = new Servicio;
        $servicio->forceFill(['estado' => $estado]);
        $servicio->setRelation('plan', $plan === null ? null : new Plan(['nombre' => $plan]));

        return $servicio;
    }

    /**
     * @param  list<Servicio>  $servicios
     */
    private function cliente(array $servicios): Cliente
    {
        $cliente = new Cliente;
        $cliente->setRelation('servicios', new Collection($servicios));

        return $cliente;
    }

    public function test_nivel_sale_del_nombre_del_plan(): void
    {
        $this->assertSame('basico', Plan::nivelDesdeNombre('Fibra Básico 100 Mbps'));
        $this->assertSame('estandar', Plan::nivelDesdeNombre('PLAN ESTANDAR 20'));
        $this->assertSame('estandar', Plan::nivelDesdeNombre('Antena Estándar'));
        $this->assertSame('premium', Plan::nivelDesdeNombre('Fibra Premium 300'));
        $this->assertSame('', Plan::nivelDesdeNombre('Plan 10 Mbps'));
        $this->assertSame('', Plan::nivelDesdeNombre(null));
    }

    public function test_cada_nivel_da_sus_slots_y_sin_nivel_cuenta_como_basico(): void
    {
        $this->assertSame(1, Plan::slotsHotspotDeNivel('basico'));
        $this->assertSame(2, Plan::slotsHotspotDeNivel('estandar'));
        $this->assertSame(3, Plan::slotsHotspotDeNivel('premium'));
        $this->assertSame(1, Plan::slotsHotspotDeNivel(''));
    }

    public function test_nivel_que_habilita_cada_slot(): void
    {
        $this->assertSame('basico', Plan::nivelParaSlot(1));
        $this->assertSame('estandar', Plan::nivelParaSlot(2));
        $this->assertSame('premium', Plan::nivelParaSlot(3));
    }

    public function test_el_cliente_toma_el_plan_de_mayor_nivel_y_omite_cancelados(): void
    {
        $cliente = $this->cliente([
            $this->servicio('Fibra Básico 100'),
            $this->servicio('Fibra Estándar 200'),
            $this->servicio('Fibra Premium 300', Servicio::ESTADO_CANCELADO),
        ]);

        $plan = $cliente->planHotspot();

        $this->assertSame('estandar', $plan['nivel']);
        $this->assertSame('Estándar', $plan['etiqueta']);
        $this->assertSame(2, $plan['slots']);
        $this->assertSame('Fibra Estándar 200', $plan['plan']);
    }

    public function test_sin_servicios_o_sin_nivel_da_un_slot(): void
    {
        $this->assertSame(1, $this->cliente([])->slotsHotspotPermitidos());
        $this->assertSame(1, $this->cliente([$this->servicio('Plan 10 Mbps')])->slotsHotspotPermitidos());
        $this->assertSame('Sin nivel', $this->cliente([$this->servicio('Plan 10 Mbps')])->planHotspot()['etiqueta']);
    }

    public function test_slots_libres_respetan_el_plan(): void
    {
        $this->assertSame([1], ServicioHotspot::slotsDisponibles(1));
        $this->assertSame([2], ServicioHotspot::slotsLibresDe([1], 2));
        $this->assertSame([], ServicioHotspot::slotsLibresDe([1], 1));
        $this->assertSame([2, 3], ServicioHotspot::slotsLibresDe([1]));
    }

    public function test_cuenta_en_slot_que_el_plan_no_incluye_queda_fuera_del_plan(): void
    {
        $cliente = $this->cliente([$this->servicio('Fibra Básico 100')]);

        $slot1 = new ServicioHotspot(['slot_numero' => 1]);
        $slot1->setRelation('cliente', $cliente);
        $slot2 = new ServicioHotspot(['slot_numero' => 2]);
        $slot2->setRelation('cliente', $cliente);

        $this->assertFalse($slot1->fueraDelPlan());
        $this->assertTrue($slot2->fueraDelPlan());
        $this->assertFalse($slot2->fueraDelPlan(2));
    }

    public function test_cuenta_sin_cliente_no_se_marca_fuera_del_plan(): void
    {
        $this->assertFalse((new ServicioHotspot(['slot_numero' => 3]))->fueraDelPlan());
    }

    public function test_radius_rechaza_la_cuenta_fuera_del_plan(): void
    {
        $cliente = $this->cliente([$this->servicio('Antena Estándar 20')]);

        $hotspot = new ServicioHotspot(['username' => '123-3', 'password' => '1234', 'slot_numero' => 3]);
        $hotspot->setRelation('servicio', $this->servicio('Antena Estándar 20'));
        $hotspot->setRelation('hotspotPerfil', null);
        $hotspot->setRelation('cliente', $cliente);

        $check = (new RadiusHotspotSyncService)->atributosCheck($hotspot);
        $this->assertContains(['Auth-Type', ':=', 'Reject'], $check);

        $hotspot->slot_numero = 2;
        $check = (new RadiusHotspotSyncService)->atributosCheck($hotspot);
        $this->assertNotContains(['Auth-Type', ':=', 'Reject'], $check);
    }
}
