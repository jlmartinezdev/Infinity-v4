<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Router;
use App\Models\WaIncidenteZona;

class IncidenteZonaService
{
    public function abrir(?int $routerId, ?string $host, string $origen, string $resumen): void
    {
        $query = WaIncidenteZona::query()->where('estado', 'abierto');
        if ($routerId) {
            $query->where('router_id', $routerId);
        } elseif (filled($host)) {
            $query->where('host', $host);
        } else {
            return;
        }

        if ($query->exists()) {
            return;
        }

        WaIncidenteZona::query()->create([
            'router_id' => $routerId,
            'host' => $host,
            'origen' => $origen,
            'estado' => 'abierto',
            'resumen' => mb_substr($resumen, 0, 255),
            'abierto_at' => now(),
        ]);
    }

    public function cerrar(?int $routerId, ?string $host): void
    {
        $query = WaIncidenteZona::query()->where('estado', 'abierto');
        if ($routerId) {
            $query->where('router_id', $routerId);
        } elseif (filled($host)) {
            $query->where('host', $host);
        } else {
            return;
        }

        $query->update([
            'estado' => 'cerrado',
            'cerrado_at' => now(),
        ]);
    }

    public function hayAbierto(?int $routerId): bool
    {
        if (! $routerId) {
            return false;
        }

        return WaIncidenteZona::query()
            ->where('estado', 'abierto')
            ->where('router_id', $routerId)
            ->exists();
    }

    public function abrirDesdeRouter(Router $router, string $origen): void
    {
        $this->abrir(
            (int) $router->router_id,
            (string) $router->ip,
            $origen,
            'Router '.$router->nombre.' sin respuesta',
        );
    }

    public function cerrarDesdeRouter(Router $router): void
    {
        $this->cerrar((int) $router->router_id, (string) $router->ip);
    }
}
