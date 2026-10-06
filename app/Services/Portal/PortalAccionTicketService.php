<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tickets de historial generados por acciones del cliente en la app
 * (cambio de clave app, cambio de clave Wi‑Fi, etc.): siempre resueltos.
 */
class PortalAccionTicketService
{
    public const ASUNTO_CLAVE_APP = 'Cambio de Contraseña App';

    public const ASUNTO_CLAVE_WIFI = 'Cambio de Contraseña en Router Wifi';

    /**
     * @param  array<string, mixed>  $diagnostico
     */
    public function crearResuelto(
        Cliente $cliente,
        string $asuntoNombre,
        string $descripcion,
        array $diagnostico = [],
        ?int $usuarioId = null,
        string $observaciones = 'Resuelto automáticamente: acción confirmada por la app.'
    ): ?Ticket {
        try {
            $asunto = TicketAsunto::firstOrCreate(['nombre' => $asuntoNombre], []);
            $usuarioId = $usuarioId
                ?? (function_exists('auth') ? auth()->id() : null)
                ?? $cliente->usuarioPortal()?->usuario_id;

            return Ticket::create([
                'cliente_id' => $cliente->cliente_id,
                'ticket_asunto_id' => $asunto->id,
                'descripcion' => $descripcion,
                'datos_diagnostico' => array_merge([
                    'origen' => 'app',
                ], $diagnostico),
                'prioridad' => 'baja',
                'estado' => 'resuelto',
                'reportado_desde' => 'app',
                'usuario_id' => $usuarioId,
                'fecha_cierre' => now(),
                'observaciones' => $observaciones,
            ]);
        } catch (Throwable $e) {
            Log::warning('[Portal] no se pudo crear ticket de acción app', [
                'cliente_id' => $cliente->cliente_id,
                'asunto' => $asuntoNombre,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ticket por cambio de clave (y opcionalmente nombre) Wi‑Fi desde la app.
     *
     * @return array{id: int, estado: string, fecha_cierre: string|null, asunto: string}|null
     */
    public function ticketCambioWifi(
        Cliente $cliente,
        int $servicioId,
        bool $cambiaClave,
        bool $cambiaSsid,
        ?string $ssid = null,
        ?string $wifiId = null,
        ?string $source = null,
    ): ?array {
        if (! $cambiaClave && ! $cambiaSsid) {
            return null;
        }

        // Tema canónico del catálogo (seed): cambio de clave router Wi‑Fi.
        // Si solo renombra SSID, igual queda historial con el mismo tema y texto aclaratorio.
        $partes = [];
        if ($cambiaClave) {
            $partes[] = 'cambio de contraseña Wi‑Fi';
        }
        if ($cambiaSsid) {
            $partes[] = 'cambio de nombre (SSID)'.($ssid ? ' a «'.$ssid.'»' : '');
        }
        $descripcion = 'Acción desde la app: '.implode(' y ', $partes).'.';
        if ($servicioId > 0) {
            $descripcion .= ' Servicio #'.$servicioId.'.';
        }

        $ticket = $this->crearResuelto(
            $cliente,
            self::ASUNTO_CLAVE_WIFI,
            $descripcion,
            [
                'accion' => $cambiaClave
                    ? ($cambiaSsid ? 'wifi_ssid_password' : 'wifi_password')
                    : 'wifi_ssid',
                'servicio_id' => $servicioId,
                'ssid' => $ssid,
                'wifi_id' => $wifiId,
                'source' => $source,
            ],
            null,
            'Resuelto automáticamente: cambio de Wi‑Fi confirmado por la app.'
        );

        return $ticket ? $this->payload($ticket, self::ASUNTO_CLAVE_WIFI) : null;
    }

    /**
     * @return array{id: int, estado: string, fecha_cierre: string|null, asunto: string}
     */
    public function payload(Ticket $ticket, ?string $asuntoNombre = null): array
    {
        $asunto = $asuntoNombre
            ?? ($ticket->relationLoaded('ticketAsunto')
                ? ($ticket->ticketAsunto->nombre ?? null)
                : null)
            ?? $ticket->ticketAsunto()?->value('nombre');

        return [
            'id' => (int) $ticket->id,
            'estado' => (string) $ticket->estado,
            'fecha_cierre' => optional($ticket->fecha_cierre)?->toIso8601String(),
            'asunto' => (string) ($asunto ?? ''),
        ];
    }
}
