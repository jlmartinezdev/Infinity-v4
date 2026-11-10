<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LecturaChatActualizada implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversacionId,
        public int $usuarioId,
        public int $ultimoLeidoMensajeId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.conversacion.'.$this->conversacionId);
    }

    public function broadcastAs(): string
    {
        return 'lectura.actualizada';
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'conversacion_id' => $this->conversacionId,
            'usuario_id' => $this->usuarioId,
            'ultimo_leido_mensaje_id' => $this->ultimoLeidoMensajeId,
        ];
    }
}
