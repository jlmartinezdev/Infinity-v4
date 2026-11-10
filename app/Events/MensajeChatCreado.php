<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MensajeChatCreado implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $mensaje
     * @param  list<int>  $participantes
     */
    public function __construct(
        public array $mensaje,
        public array $participantes,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $canales = [new PrivateChannel('chat.conversacion.'.$this->mensaje['conversacion_id'])];
        foreach ($this->participantes as $usuarioId) {
            $canales[] = new PrivateChannel('chat.usuario.'.$usuarioId);
        }

        return $canales;
    }

    public function broadcastAs(): string
    {
        return 'mensaje.creado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['mensaje' => $this->mensaje];
    }
}
