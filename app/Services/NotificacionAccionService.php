<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\AccionSistemaNotification;
use Illuminate\Support\Facades\Notification;

class NotificacionAccionService
{
    /**
     * Notifica a todos los usuarios sobre la auditoría (incluido quien realizó la acción, para que vea su actividad en el dropdown).
     */
    public function notificarAccion(Auditoria $auditoria): void
    {
        $auditoria->loadMissing('usuario');

        $usuarios = User::all();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new AccionSistemaNotification($auditoria));
    }
}
