<?php

namespace App\Console\Commands;

use App\Services\Monitoreo\ServicioPingMonitoreoService;
use Illuminate\Console\Command;

class MonitoreoPingDaemonCommand extends Command
{
    protected $signature = 'monitoreo:ping-daemon
                            {--intervalo= : Segundos entre rondas (default: config monitoreo.intervalo_segundos)}';

    protected $description = 'Microservicio de monitoreo: consulta PPPoE activo en MikroTik de forma periódica.';

    public function handle(ServicioPingMonitoreoService $monitoreo): int
    {
        if (! config('monitoreo.habilitado', true)) {
            $this->warn('Monitoreo PPPoE deshabilitado (MONITOREO_PING_HABILITADO=false).');

            return self::SUCCESS;
        }

        $intervalo = (int) ($this->option('intervalo') ?: config('monitoreo.intervalo_segundos', 300));
        $intervalo = max(60, $intervalo);

        $this->info("Daemon PPPoE iniciado — intervalo {$intervalo}s. Ctrl+C para detener.");

        while (true) {
            $inicio = microtime(true);
            $stats = $monitoreo->ejecutarRonda();
            $duracion = round(microtime(true) - $inicio, 1);

            $this->line(sprintf(
                '[%s] PPPoE: %d procesados (%d online, %d caídos, %d errores) en %ss',
                now()->format('Y-m-d H:i:s'),
                $stats['procesados'],
                $stats['en_linea'],
                $stats['sin_respuesta'],
                $stats['errores'] ?? 0,
                $duracion
            ));

            sleep($intervalo);
        }
    }
}
