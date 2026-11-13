<?php

namespace App\Console\Commands;

use App\Services\Senal\LecturaSenalDiariaService;
use App\Support\ScheduleOnceAfter;
use Illuminate\Console\Command;
use Throwable;

class LeerSenalDiariaCommand extends Command
{
    protected $signature = 'senal:leer-diaria
                            {--solo= : fibra o antena}
                            {--limite= : máximo de antenas en esta ejecución}';

    protected $description = 'Lee una vez la señal de los servicios activos y guarda el informe de las que no respondieron.';

    public function handle(LecturaSenalDiariaService $lecturas): int
    {
        $solo = $this->option('solo');
        $solo = is_string($solo) && $solo !== '' ? $solo : null;
        $limite = $this->option('limite');
        $limite = $limite !== null && $limite !== '' ? (int) $limite : null;
        $completa = $solo === null && ($limite === null || $limite < 1);

        $this->info('Leyendo señal de servicios activos…');

        try {
            $resultado = $lecturas->ejecutar($solo, $limite);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Medio', 'Leídas', 'Sin lectura'],
            [
                ['Fibra', $resultado['fibra_leidas'], $resultado['fibra_fallidas']],
                ['Antena', $resultado['antena_leidas'], $resultado['antena_fallidas']],
            ]
        );
        $this->info('Informe #'.$resultado['lectura_id'].'.');

        if ($completa) {
            ScheduleOnceAfter::markDone('senal-diaria');
        }

        return self::SUCCESS;
    }
}
