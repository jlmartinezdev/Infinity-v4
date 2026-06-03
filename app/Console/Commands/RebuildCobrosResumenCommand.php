<?php

namespace App\Console\Commands;

use App\Services\CobrosResumenService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebuildCobrosResumenCommand extends Command
{
    protected $signature = 'cobros:rebuild-resumen
                            {--desde= : Primer mes YYYY-MM (opcional)}
                            {--hasta= : Último mes YYYY-MM (opcional)}';

    protected $description = 'Recalcula la tabla cobros_resumen desde cobros y cobro_factura_interna.';

    public function handle(CobrosResumenService $service): int
    {
        $desde = null;
        $hasta = null;

        if ($this->option('desde')) {
            try {
                $desde = Carbon::createFromFormat('Y-m', (string) $this->option('desde'))->startOfMonth();
            } catch (\Throwable) {
                $this->error('Formato invalido en --desde. Usa YYYY-MM.');

                return self::FAILURE;
            }
        }

        if ($this->option('hasta')) {
            try {
                $hasta = Carbon::createFromFormat('Y-m', (string) $this->option('hasta'))->endOfMonth();
            } catch (\Throwable) {
                $this->error('Formato invalido en --hasta. Usa YYYY-MM.');

                return self::FAILURE;
            }
        }

        $meses = $service->sincronizarDesdeCero($desde, $hasta);
        $this->info("Resumen recalculado para {$meses} mes(es).");

        return self::SUCCESS;
    }
}
