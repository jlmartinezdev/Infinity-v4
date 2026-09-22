<?php

namespace Tests\Unit\Observers;

use App\Models\Servicio;
use App\Models\ServicioHotspot;
use App\Observers\ServicioRadiusObserver;
use App\Services\RadiusHotspotSyncService;
use Mockery;
use Tests\TestCase;

class ServicioRadiusObserverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_al_cambiar_estado_sincroniza_todos_los_hotspot_del_servicio(): void
    {
        $h1 = new ServicioHotspot(['username' => 'u1']);
        $h2 = new ServicioHotspot(['username' => 'u2']);

        $servicio = new Servicio;
        $servicio->forceFill(['estado' => Servicio::ESTADO_ACTIVO]);
        $servicio->syncOriginal();
        $servicio->estado = Servicio::ESTADO_SUSPENDIDO;
        $servicio->syncChanges();
        $servicio->setRelation('servicioHotspots', collect([$h1, $h2]));

        $radius = Mockery::mock(RadiusHotspotSyncService::class);
        $radius->shouldReceive('enabled')->once()->andReturn(true);
        $radius->shouldReceive('sync')->once()->with($h1);
        $radius->shouldReceive('sync')->once()->with($h2);

        (new ServicioRadiusObserver($radius))->updated($servicio);

        $this->addToAssertionCount(1);
    }
}
