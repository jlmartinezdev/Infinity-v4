<?php

namespace App\Console\Commands;

use App\Services\HotspotCuotaResetService;
use Illuminate\Console\Command;

class HotspotResetCuotaCommand extends Command
{
    protected $signature = 'hotspot:reset-cuota
                            {--periodo= : Mes AAAA-MM. Por defecto el mes actual}';

    protected $description = 'Pone en cero el contador de cuota de los usuarios hotspot con límite de GB. Un mismo mes no se repite.';

    public function handle(HotspotCuotaResetService $servicio): int
    {
        $periodo = trim((string) $this->option('periodo'));
        if ($periodo === '') {
            $periodo = now()->format('Y-m');
        }

        $resultado = $servicio->resetear($periodo);

        if ($resultado['skipped']) {
            $this->info($resultado['motivo'] ?? 'No hubo nada que resetear.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Cuota %s: %d contadores en cero, %d ya estaban en cero, %d sesiones cerradas, %d errores.',
            $periodo,
            $resultado['reseteados'],
            $resultado['ya_reseteados'],
            $resultado['sesiones'],
            $resultado['errores']
        ));

        return $resultado['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
