<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Router;
use App\Models\Servicio;
use App\Models\WaImpresoraReserva;
use App\Services\MikroTikService;

class ImpresoraSoporteService
{
    public function __construct(
        private readonly MikroTikService $mikrotik,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $equipos
     * @return array{reply: string, reserva_id: ?int, ip: ?string, mac: ?string, hostname: ?string, lease_fijo: bool}
     */
    public function localizar(Servicio $servicio, array $equipos, bool $fijarLease, ?int $clienteId, bool $persistir = true): array
    {
        $elegido = null;
        foreach ($equipos as $equipo) {
            $nombre = strtolower((string) ($equipo['hostname'] ?? $equipo['nombre'] ?? ''));
            if ($nombre !== '' && preg_match('/print|impres|hp|epson|canon|brother/', $nombre)) {
                $elegido = $equipo;
                break;
            }
        }

        if ($elegido === null || ! filled($elegido['ip'] ?? null)) {
            return [
                'reply' => FraseCliente::IMPRESORA_NO,
                'reserva_id' => null,
                'ip' => null,
                'mac' => null,
                'hostname' => null,
                'lease_fijo' => false,
            ];
        }

        $leaseFijo = false;
        $router = $servicio->pool?->router;
        if ($persistir && $fijarLease && $router instanceof Router) {
            $leaseFijo = (bool) ($this->mikrotik->fijarLeaseDhcp($router, (string) $elegido['ip'])['success'] ?? false);
        }

        $reservaId = null;
        if ($persistir) {
            $reserva = WaImpresoraReserva::query()->create([
                'cliente_id' => $clienteId,
                'servicio_id' => $servicio->servicio_id,
                'ip' => (string) $elegido['ip'],
                'mac' => (string) ($elegido['mac'] ?? ''),
                'hostname' => (string) ($elegido['hostname'] ?? $elegido['nombre'] ?? ''),
                'lease_fijo' => $leaseFijo,
            ]);
            $reservaId = (int) $reserva->id;
        }

        return [
            'reply' => $persistir
                ? FraseCliente::IMPRESORA
                : 'En la prueba encontramos la impresora. Cuando esté habilitado, le dejamos la dirección fija y un archivo para Windows.',
            'reserva_id' => $reservaId,
            'ip' => (string) $elegido['ip'],
            'mac' => (string) ($elegido['mac'] ?? ''),
            'hostname' => (string) ($elegido['hostname'] ?? ''),
            'lease_fijo' => $leaseFijo,
        ];
    }
}
