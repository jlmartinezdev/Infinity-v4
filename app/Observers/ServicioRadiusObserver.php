<?php

namespace App\Observers;

use App\Models\Servicio;
use App\Models\ServicioHotspot;
use App\Services\RadiusHotspotSyncService;

class ServicioRadiusObserver
{
    public function __construct(
        private readonly RadiusHotspotSyncService $radius
    ) {}

    public function created(Servicio $servicio): void
    {
        if (! $this->radius->enabled()) {
            return;
        }

        // Un servicio nuevo puede subir el nivel del cliente y habilitar cuentas suspendidas por plan.
        $this->sincronizar($this->cuentasDelCliente($servicio));
    }

    public function updated(Servicio $servicio): void
    {
        if (! $this->radius->enabled()) {
            return;
        }

        $cambioEstado = $servicio->wasChanged('estado');
        $cambioPlan = $servicio->wasChanged('plan_id')
            || ($cambioEstado && in_array(Servicio::ESTADO_CANCELADO, [$servicio->estado, $servicio->getOriginal('estado')], true));

        if (! $cambioEstado && ! $cambioPlan) {
            return;
        }

        $servicio->loadMissing('servicioHotspots');
        $cuentas = collect($servicio->servicioHotspots);

        // Cambió lo que el plan del cliente permite: revisar también las cuentas de sus otros servicios.
        if ($cambioPlan) {
            $cuentas = $cuentas->merge($this->cuentasDelCliente($servicio));
        }

        $this->sincronizar($cuentas);
    }

    public function deleted(Servicio $servicio): void
    {
        if (! $this->radius->enabled()) {
            return;
        }

        $this->sincronizar($this->cuentasDelCliente($servicio));
    }

    /**
     * @return \Illuminate\Support\Collection<int, ServicioHotspot>
     */
    private function cuentasDelCliente(Servicio $servicio)
    {
        if (! $servicio->cliente_id) {
            return collect();
        }

        return ServicioHotspot::query()
            ->where('cliente_id', $servicio->cliente_id)
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ServicioHotspot>  $cuentas
     */
    private function sincronizar($cuentas): void
    {
        $vistas = [];
        foreach ($cuentas as $cuenta) {
            $clave = $cuenta->getKey() ?? spl_object_id($cuenta);
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $this->radius->sync($cuenta);
        }
    }
}
