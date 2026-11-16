<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsappEncargoService;
use App\Support\ScheduleOnceAfter;
use Illuminate\Console\Command;

class WhatsappSoltarEncargosCommand extends Command
{
    protected $signature = 'whatsapp:soltar-encargos';

    protected $description = 'Suelta los chats de WhatsApp encargados en días anteriores';

    public function handle(WhatsappEncargoService $encargos): int
    {
        $sueltos = $encargos->liberarVencidos();
        $this->info('Encargos de días anteriores sueltos: '.$sueltos);
        ScheduleOnceAfter::markDone('whatsapp-soltar-encargos');

        return self::SUCCESS;
    }
}
