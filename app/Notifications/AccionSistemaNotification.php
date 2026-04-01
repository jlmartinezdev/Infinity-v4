<?php

namespace App\Notifications;

use App\Models\Auditoria;
use Illuminate\Notifications\Notification;

class AccionSistemaNotification extends Notification
{

    public function __construct(
        public Auditoria $auditoria
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $usuarioNombre = $this->auditoria->usuario_id
            ? ($this->auditoria->usuario?->name ?? "Usuario #{$this->auditoria->usuario_id}")
            : 'Sistema';

        $accionLabel = match ($this->auditoria->accion) {
            'created' => 'creó',
            'updated' => 'actualizó',
            'deleted' => 'eliminó',
            default => $this->auditoria->accion,
        };

        return [
            'auditoria_id' => $this->auditoria->auditoria_id,
            'tabla' => $this->auditoria->tabla,
            'accion' => $this->auditoria->accion,
            'accion_label' => $accionLabel,
            'mensaje' => "{$usuarioNombre} {$accionLabel} un registro en {$this->auditoria->tabla}.",
            'usuario_nombre' => $usuarioNombre,
            'registro_id' => $this->auditoria->registro_id,
            'registro_key' => $this->auditoria->registro_key,
            'created_at' => $this->auditoria->created_at?->toIso8601String(),
        ];
    }
}
