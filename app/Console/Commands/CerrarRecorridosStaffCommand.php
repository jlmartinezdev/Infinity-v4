<?php

namespace App\Console\Commands;

use App\Services\Staff\StaffRecorridoService;
use App\Support\ScheduleOnceAfter;
use Illuminate\Console\Command;
use Throwable;

class CerrarRecorridosStaffCommand extends Command
{
    protected $signature = 'staff:cerrar-recorridos
                            {--fecha= : Cierra un día puntual (AAAA-MM-DD), incluso hoy}
                            {--dry-run : Calcula el resumen sin borrar puntos}';

    protected $description = 'Arma el resumen diario del recorrido de técnicos y borra los puntos detallados.';

    public function handle(StaffRecorridoService $recorridos): int
    {
        $dry = (bool) $this->option('dry-run');
        $fecha = $this->option('fecha');
        $fecha = is_string($fecha) ? trim($fecha) : '';

        try {
            $result = $fecha !== ''
                ? $recorridos->cerrarFecha($fecha, $dry)
                : $recorridos->cerrarDiasAnteriores($dry);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $verbo = $dry ? 'se cerrarían' : 'cerrados';
        $this->info(sprintf(
            'Recorridos %s: %d. Puntos %s: %d.',
            $verbo,
            $result['cerrados'],
            $dry ? 'a eliminar' : 'eliminados',
            $result['puntos_eliminados']
        ));

        if (! $dry && $fecha === '') {
            ScheduleOnceAfter::markDone('staff-cerrar-recorridos');
        }

        return self::SUCCESS;
    }
}
