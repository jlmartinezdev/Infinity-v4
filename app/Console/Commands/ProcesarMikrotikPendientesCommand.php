<?php

namespace App\Console\Commands;

use App\Models\MikrotikOperacionPendiente;
use App\Services\MikrotikPendienteEjecutor;
use Illuminate\Console\Command;

class ProcesarMikrotikPendientesCommand extends Command
{
    protected $signature = 'mikrotik:procesar-pendientes';

    protected $description = 'Reintenta operaciones MikroTik fallidas registradas en cola (sincronizaciones, deshabilitar PPPoE, etc.).';

    public function handle(MikrotikPendienteEjecutor $ejecutor): int
    {
        $ops = MikrotikOperacionPendiente::query()->pendientes()->orderBy('id')->get();

        if ($ops->isEmpty()) {
            $this->info('No hay operaciones MikroTik pendientes.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Procesando %d operación(es)…', $ops->count()));

        $ok = 0;
        $fail = 0;
        foreach ($ops as $op) {
            $r = $ejecutor->ejecutar($op);
            if (! empty($r['success'])) {
                $ok++;
                $this->line("  ✓ ID {$op->id} ({$op->tipo})");
            } else {
                $fail++;
                $this->warn("  ✗ ID {$op->id}: " . ($r['error'] ?? 'error'));
            }
        }

        $this->info("Listo: {$ok} correctas, {$fail} con error.");

        return self::SUCCESS;
    }
}
