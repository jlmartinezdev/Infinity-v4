<?php

namespace App\Observers;

use App\Models\HotspotPerfil;
use App\Services\RadiusHotspotSyncService;

class HotspotPerfilObserver
{
    public function __construct(
        private readonly RadiusHotspotSyncService $radius
    ) {}

    public function updated(HotspotPerfil $perfil): void
    {
        if (! $this->radius->enabled()) {
            return;
        }
        if (! $perfil->wasChanged(['rate_limit', 'cuota_gb', 'shared_users', 'idle_timeout', 'session_timeout'])) {
            return;
        }

        foreach ($perfil->servicioHotspots as $hotspot) {
            $this->radius->sync($hotspot);
        }
    }
}
