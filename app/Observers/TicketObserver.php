<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Services\ClientePushNotifier;
use App\Services\TicketStaffPushService;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;

class TicketObserver
{
    public function __construct(
        private readonly WhatsAppOutboundNotifier $whatsappOutbound,
        private readonly ClientePushNotifier $clientePush,
        private readonly TicketStaffPushService $staffPush,
    ) {}

    public function created(Ticket $ticket): void
    {
        $this->staffPush->ticketCreado($ticket);

        if ($ticket->asignado_id) {
            $this->whatsappOutbound->ticketAsignado($ticket);
        }
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('asignado_id') && $ticket->asignado_id) {
            $this->staffPush->ticketAsignado($ticket);
            $this->whatsappOutbound->ticketAsignado($ticket);
        }

        // Aviso al cliente de la app cuando cambia estado u observaciones
        if ($ticket->cliente_id && ($ticket->wasChanged('estado') || $ticket->wasChanged('observaciones'))) {
            $cambio = $ticket->wasChanged('observaciones') && ! $ticket->wasChanged('estado')
                ? 'observaciones'
                : 'estado';
            $this->clientePush->ticketActualizado($ticket, $cambio);
        }

        // WhatsApp al cliente: ticket → resuelto
        if (
            $ticket->wasChanged('estado')
            && $ticket->estado === 'resuelto'
            && $ticket->cliente_id
        ) {
            $this->whatsappOutbound->ticketResuelto($ticket);
        }
    }
}
