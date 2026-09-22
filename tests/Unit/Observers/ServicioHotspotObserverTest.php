<?php

namespace Tests\Unit\Observers;

use App\Models\ServicioHotspot;
use App\Observers\ServicioHotspotObserver;
use App\Services\RadiusHotspotSyncService;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Mockery;
use Tests\TestCase;

class ServicioHotspotObserverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_created_sincroniza_despues_de_responder(): void
    {
        $hotspot = new ServicioHotspot(['username' => '5263907-3', 'password' => '123456']);
        $radius = Mockery::mock(RadiusHotspotSyncService::class);
        $radius->shouldReceive('sync')->once()->with($hotspot);

        (new ServicioHotspotObserver($radius))->created($hotspot);

        $this->app->make(DeferredCallbackCollection::class)->invoke();
        $this->addToAssertionCount(1);
    }

    public function test_deleted_olvida_despues_de_responder(): void
    {
        $hotspot = new ServicioHotspot(['username' => '5263907-3']);
        $radius = Mockery::mock(RadiusHotspotSyncService::class);
        $radius->shouldReceive('forget')->once()->with('5263907-3');

        (new ServicioHotspotObserver($radius))->deleted($hotspot);

        $this->app->make(DeferredCallbackCollection::class)->invoke();
        $this->addToAssertionCount(1);
    }
}
