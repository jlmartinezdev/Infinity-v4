<?php

namespace App\Console\Commands;

use App\Services\Tv\TvAvisoVencimientoService;
use Illuminate\Console\Command;

class TvAvisarVencimientosCommand extends Command
{
    protected $signature = 'tv:avisar-vencimientos';

    protected $description = 'Envía avisos WhatsApp de cuentas TV por vencer/vencidas a usuarios configurados';

    public function handle(TvAvisoVencimientoService $service): int
    {
        $stats = $service->ejecutar();

        $this->table(
            ['candidatas', 'enviadas', 'omitidas', 'errores'],
            [[$stats['candidatas'], $stats['enviadas'], $stats['omitidas'], $stats['errores']]]
        );

        return self::SUCCESS;
    }
}
