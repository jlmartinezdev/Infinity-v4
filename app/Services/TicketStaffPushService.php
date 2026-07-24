<?php

namespace App\Services;

use App\Models\Ticket;

class TicketStaffPushService
{
    public function __construct(
        private readonly FcmPushService $fcm,
    ) {}

    public function ticketCreado(Ticket $ticket): void
    {
        $ticket->loadMissing('cliente:cliente_id,nombre,apellido');

        $descripcion = trim((string) ($ticket->descripcion ?? ''));
        $preview = $descripcion !== ''
            ? mb_strimwidth($descripcion, 0, 120, '…')
            : 'Sin descripción';

        $cliente = $ticket->cliente;
        $nombreCliente = $cliente
            ? trim((string) $cliente->nombre.' '.(string) $cliente->apellido)
            : '';

        $body = $nombreCliente !== ''
            ? "Cliente {$nombreCliente} reportó un problema: {$preview}"
            : "Nuevo reporte: {$preview}";

        $this->fcm->notifyStaff(
            'Nuevo Ticket de Soporte',
            $body,
            [
                'ticket_id' => (string) $ticket->id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]
        );
    }
}
