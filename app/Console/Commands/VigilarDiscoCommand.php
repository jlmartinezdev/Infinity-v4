<?php

namespace App\Console\Commands;

use App\Services\Sistema\DiscoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VigilarDiscoCommand extends Command
{
    protected $signature = 'sistema:vigilar-disco';

    protected $description = 'Mide el disco y deja alerta en el panel si /tmp o el sistema se llenan';

    public function handle(DiscoService $disco): int
    {
        $estado = $disco->estado();
        Cache::put('sistema.disco', $estado, now()->addMinutes(30));

        if (! $estado['alerta']) {
            $this->info('Disco ok');

            return self::SUCCESS;
        }

        $this->warn($estado['texto']);
        if (Cache::add('sistema.disco.avisado', 1, now()->addHour())) {
            Log::warning('[disco] '.$estado['texto']);
        }

        return self::SUCCESS;
    }
}
