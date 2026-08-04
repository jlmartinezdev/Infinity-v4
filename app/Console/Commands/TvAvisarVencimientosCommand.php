<?php

namespace App\Console\Commands;

use App\Services\Tv\TvAvisoVencimientoService;
use Illuminate\Console\Command;

class TvAvisarVencimientosCommand extends Command
{
    protected $signature = 'tv:avisar-vencimientos
                            {--probar : Envía avisos de prueba para todas las cuentas en ventana (no registra ni exige avisos activos)}';

    protected $description = 'Envía avisos WhatsApp de cuentas TV por vencer/vencidas a usuarios configurados';

    public function handle(TvAvisoVencimientoService $service): int
    {
        if ($this->option('probar')) {
            $resultado = $service->probar();
            if ($resultado['ok']) {
                $this->info($resultado['message']);

                return self::SUCCESS;
            }
            $this->error($resultado['message']);

            return self::FAILURE;
        }

        $stats = $service->ejecutar();

        $this->table(
            ['candidatas', 'enviadas', 'omitidas', 'errores'],
            [[$stats['candidatas'], $stats['enviadas'], $stats['omitidas'], $stats['errores']]]
        );

        return self::SUCCESS;
    }
}
