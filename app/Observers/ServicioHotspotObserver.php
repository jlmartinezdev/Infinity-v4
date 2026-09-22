<?php

namespace App\Observers;

use App\Models\ServicioHotspot;
use App\Services\RadiusHotspotSyncService;

class ServicioHotspotObserver
{
    public function __construct(
        private readonly RadiusHotspotSyncService $radius
    ) {}

    public function created(ServicioHotspot $servicioHotspot): void
    {
        $this->despuesDeResponder(fn () => $this->radius->sync($servicioHotspot));
    }

    public function updated(ServicioHotspot $servicioHotspot): void
    {
        if (! $servicioHotspot->wasChanged(['username', 'password', 'hotspot_perfil_id', 'router_id', 'servicio_id'])) {
            return;
        }

        $anterior = trim((string) $servicioHotspot->getOriginal('username'));
        $actual = trim((string) $servicioHotspot->username);

        $this->despuesDeResponder(function () use ($servicioHotspot, $anterior, $actual) {
            if ($anterior !== '' && $anterior !== $actual) {
                $this->radius->forget($anterior);
            }

            $this->radius->sync($servicioHotspot);
        });
    }

    public function deleted(ServicioHotspot $servicioHotspot): void
    {
        $username = (string) $servicioHotspot->username;
        $this->despuesDeResponder(fn () => $this->radius->forget($username));
    }

    protected function despuesDeResponder(callable $callback): void
    {
        defer($callback);
    }
}
