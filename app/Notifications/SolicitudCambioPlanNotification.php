<?php

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Servicio;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SolicitudCambioPlanNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public Servicio $servicio,
        public Plan $planActual,
        public Plan $planNuevo,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cliente = $this->servicio->cliente;

        return [
            'tipo' => 'solicitud_cambio_plan',
            'title' => 'Solicitud de baja de plan',
            'body' => sprintf(
                '%s pide pasar de %s a %s (ticket #%d)',
                trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? '')),
                $this->planActual->nombre,
                $this->planNuevo->nombre,
                $this->ticket->id
            ),
            'ticket_id' => $this->ticket->id,
            'servicio_id' => $this->servicio->servicio_id,
            'cliente_id' => $this->servicio->cliente_id,
            'plan_actual_id' => $this->planActual->plan_id,
            'plan_solicitado_id' => $this->planNuevo->plan_id,
        ];
    }
}
