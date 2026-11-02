<?php

namespace App\Jobs;

use App\Services\WhatsApp\Soporte\IncidenteZonaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotificarZonaRestablecidaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $incidenteId) {}

    public function handle(IncidenteZonaService $incidentes): void
    {
        $incidentes->notificarCierre($this->incidenteId);
    }
}
