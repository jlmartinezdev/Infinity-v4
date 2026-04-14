<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarNotificacionesAntiguasCommand extends Command
{
    protected $signature = 'notificaciones:limpiar-antiguas
                            {--days=5 : Eliminar notificaciones con mas de N dias}
                            {--dry-run : Solo mostrar cuantas se eliminarian}';

    protected $description = 'Elimina notificaciones antiguas en base a su fecha de creacion.';

    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $cutoffDate = now()->subDays($days);

        $query = DB::table('notifications')->where('created_at', '<', $cutoffDate);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("No hay notificaciones con mas de {$days} dias para eliminar.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry-run: se eliminarian {$total} notificaciones anteriores a {$cutoffDate->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Se eliminaron {$deleted} notificaciones anteriores a {$cutoffDate->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
