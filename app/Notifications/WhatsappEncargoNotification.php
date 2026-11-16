<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WhatsappEncargoNotification extends Notification
{
    public function __construct(
        public string $mensaje,
        public string $url,
        public string $de,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'mensaje' => $this->mensaje,
            'url' => $this->url,
            'tabla' => 'whatsapp',
            'usuario_nombre' => $this->de,
        ];
    }
}
