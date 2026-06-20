<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\User;
use App\Notifications\AccionSistemaNotification;
use Illuminate\Support\Facades\Notification;

class NotificacionAccionService
{
    /**
     * Notifica a los administradores sobre la auditoría (incluido quien realizó la acción, si es admin).
     */
    public function notificarAccion(Auditoria $auditoria): void
    {
        $auditoria->loadMissing('usuario');

        $usuarios = User::whereHas('rol', fn ($q) => $q->whereRaw('LOWER(descripcion) = ?', ['administrador']))->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new AccionSistemaNotification($auditoria));
    }
}
