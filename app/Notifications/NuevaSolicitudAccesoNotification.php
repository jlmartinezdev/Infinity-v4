<?php

namespace App\Notifications;

use App\Models\SolicitudAcceso;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NuevaSolicitudAccesoNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SolicitudAcceso $solicitud
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'solicitud_acceso',
            'title' => 'Nueva Solicitud de Acceso',
            'body' => "{$this->solicitud->nombre} ha solicitado acceso al portal",
            'solicitud_id' => $this->solicitud->id,
            'cedula' => $this->solicitud->cedula,
        ];
    }
}
