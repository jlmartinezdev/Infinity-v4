<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccionChatAtendida implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $participantes
     */
    public function __construct(
        public int $mensajeId,
        public int $conversacionId,
        public int $usuarioId,
        public bool $resuelta,
        public array $participantes,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $canales = [new PrivateChannel('chat.conversacion.'.$this->conversacionId)];
        foreach ($this->participantes as $usuarioId) {
            $canales[] = new PrivateChannel('chat.usuario.'.$usuarioId);
        }

        return $canales;
    }

    public function broadcastAs(): string
    {
        return 'accion.atendida';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'mensaje_id' => $this->mensajeId,
            'conversacion_id' => $this->conversacionId,
            'usuario_id' => $this->usuarioId,
            'resuelta' => $this->resuelta,
        ];
    }
}
