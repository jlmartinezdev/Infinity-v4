<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioImportarService
{
    /**
     * GPS propio del servicio, del pedido de instalación o del cliente de origen.
     */
    public static function resolverUrlUbicacion(Servicio $servicio, ?Cliente $origen = null): ?string
    {
        foreach ([
            $servicio->url_ubicacion,
            $servicio->pedido?->maps_gps,
            $origen?->url_ubicacion ?? $servicio->cliente?->url_ubicacion,
        ] as $raw) {
            $url = trim((string) $raw);
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Pasa el servicio (PPPoE, plan, IP, GPS) al cliente destino.
     * Las asignaciones de TV siguen al servicio_id. Las cuentas hotspot se reasignan de slot.
     */
    public function importar(Cliente $destino, Servicio $servicio): Servicio
    {
        if ((int) $servicio->cliente_id === (int) $destino->cliente_id) {
            throw ValidationException::withMessages([
                'servicio_id' => 'Ese servicio ya pertenece a este cliente.',
            ]);
        }

        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            throw ValidationException::withMessages([
                'servicio_id' => 'No se puede importar un servicio cancelado.',
            ]);
        }

        $servicio->loadMissing(['cliente', 'pedido', 'servicioHotspots']);

        return DB::transaction(function () use ($destino, $servicio) {
            $origen = $servicio->cliente;
            $gps = self::resolverUrlUbicacion($servicio, $origen);

            $this->moverHotspots($servicio, $destino);

            $updates = ['cliente_id' => $destino->cliente_id];
            if (trim((string) ($servicio->url_ubicacion ?? '')) === '' && $gps) {
                $updates['url_ubicacion'] = $gps;
            }
            $servicio->update($updates);

            if ($gps && trim((string) ($destino->url_ubicacion ?? '')) === '') {
                $destino->update(['url_ubicacion' => $gps]);
            }

            return $servicio->refresh();
        });
    }

    private function moverHotspots(Servicio $servicio, Cliente $destino): void
    {
        $hotspots = $servicio->servicioHotspots;
        if ($hotspots->isEmpty()) {
            return;
        }

        $ocupados = ServicioHotspot::query()
            ->where('cliente_id', $destino->cliente_id)
            ->pluck('slot_numero')
            ->all();
        $libres = ServicioHotspot::slotsLibresDe($ocupados);

        if (count($libres) < $hotspots->count()) {
            throw ValidationException::withMessages([
                'servicio_id' => 'El cliente destino no tiene slots hotspot libres para las cuentas de este servicio.',
            ]);
        }

        foreach ($hotspots->values() as $i => $hotspot) {
            $hotspot->update([
                'cliente_id' => $destino->cliente_id,
                'slot_numero' => $libres[$i],
            ]);
        }
    }
}
