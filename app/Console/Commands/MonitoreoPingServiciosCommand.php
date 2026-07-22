<?php

namespace App\Console\Commands;

use App\Services\Monitoreo\ServicioPingMonitoreoService;
use Illuminate\Console\Command;

class MonitoreoPingServiciosCommand extends Command
{
    protected $signature = 'monitoreo:ping-servicios
                            {--limite= : Máximo de servicios a pingear en esta ejecución}
                            {--nodo= : Solo servicios del nodo (pool/router o caja NAP)}';

    protected $description = 'Ejecuta ping ICMP a servicios activos y guarda el estado en monitoreo_ping_servicios.';

    public function handle(ServicioPingMonitoreoService $monitoreo): int
    {
        if (! config('monitoreo.habilitado', true)) {
            $this->warn('Monitoreo ping deshabilitado (MONITOREO_PING_HABILITADO=false).');

            return self::SUCCESS;
        }

        $limite = $this->option('limite');
        $limite = $limite !== null && $limite !== '' ? (int) $limite : null;

        $nodoOpt = $this->option('nodo');
        $nodoId = $nodoOpt !== null && $nodoOpt !== '' ? (int) $nodoOpt : null;
        if ($nodoId !== null) {
            $this->info("Filtro por nodo_id: {$nodoId}");
        }

        $this->info('Iniciando ronda de ping a servicios activos…');
        $stats = $monitoreo->ejecutarRonda($limite, $nodoId);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Procesados', $stats['procesados']],
                ['En línea', $stats['en_linea']],
                ['Sin respuesta', $stats['sin_respuesta']],
                ['Omitidos (IP inválida)', $stats['omitidos']],
            ]
        );

        return self::SUCCESS;
    }
}
