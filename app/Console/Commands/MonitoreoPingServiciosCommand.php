<?php

namespace App\Console\Commands;

use App\Services\Monitoreo\ServicioPingMonitoreoService;
use Illuminate\Console\Command;

class MonitoreoPingServiciosCommand extends Command
{
    protected $signature = 'monitoreo:ping-servicios
                            {--limite= : Máximo de servicios a consultar en esta ejecución}
                            {--nodo= : Solo servicios del nodo (pool/router o caja NAP)}
                            {--router= : Solo servicios de este router_id}';

    protected $description = 'Consulta sesiones PPPoE activas en MikroTik y guarda el estado en monitoreo_ping_servicios.';

    public function handle(ServicioPingMonitoreoService $monitoreo): int
    {
        if (! config('monitoreo.habilitado', true)) {
            $this->warn('Monitoreo PPPoE deshabilitado (MONITOREO_PING_HABILITADO=false).');

            return self::SUCCESS;
        }

        $limite = $this->option('limite');
        $limite = $limite !== null && $limite !== '' ? (int) $limite : null;

        $nodoOpt = $this->option('nodo');
        $nodoId = $nodoOpt !== null && $nodoOpt !== '' ? (int) $nodoOpt : null;
        if ($nodoId !== null) {
            $this->info("Filtro por nodo_id: {$nodoId}");
        }

        $routerOpt = $this->option('router');
        $routerId = $routerOpt !== null && $routerOpt !== '' ? (int) $routerOpt : null;
        if ($routerId !== null) {
            $this->info("Filtro por router_id: {$routerId}");
        }

        $this->info('Consultando PPPoE activo en MikroTik…');
        $stats = $monitoreo->ejecutarRonda($limite, $nodoId, $routerId);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Procesados', $stats['procesados']],
                ['En línea', $stats['en_linea']],
                ['PPPoE caído', $stats['sin_respuesta']],
                ['Omitidos (sin router)', $stats['omitidos']],
                ['Errores MikroTik', $stats['errores'] ?? 0],
            ]
        );

        return self::SUCCESS;
    }
}
