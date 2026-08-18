<?php

namespace App\Jobs;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppAgentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcesarWhatsappAgentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 40;

    public function __construct(public int $whatsappMensajeId) {}

    public function handle(WhatsAppAgentService $agent): void
    {
        $mensaje = WhatsappMensaje::query()->find($this->whatsappMensajeId);
        if (! $mensaje) {
            return;
        }

        try {
            $agent->procesar($mensaje);
        } catch (\Throwable $e) {
            Log::error('[WA agent] Job falló: '.$e->getMessage(), [
                'whatsapp_mensaje_id' => $this->whatsappMensajeId,
                'exception' => $e,
            ]);
        }
    }
}
