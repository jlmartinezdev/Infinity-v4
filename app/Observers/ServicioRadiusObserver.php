<?php

namespace App\Observers;

use App\Models\Servicio;
use App\Services\RadiusHotspotSyncService;

class ServicioRadiusObserver
{
    public function __construct(
        private readonly RadiusHotspotSyncService $radius
    ) {}

    public function updated(Servicio $servicio): void
    {
        if (! $this->radius->enabled() || ! $servicio->wasChanged('estado')) {
            return;
        }

        $servicio->loadMissing('servicioHotspots');
        foreach ($servicio->servicioHotspots as $hotspot) {
            $this->radius->sync($hotspot);
        }
    }
}
