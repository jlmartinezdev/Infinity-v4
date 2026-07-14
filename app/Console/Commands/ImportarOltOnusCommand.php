<?php

namespace App\Console\Commands;

use App\Models\Olt;
use App\Services\Olt\OltOnuSyncService;
use Illuminate\Console\Command;
use Throwable;

class ImportarOltOnusCommand extends Command
{
    protected $signature = 'olt:import-onus
                            {--olt= : ID del OLT a importar}
                            {--all : Importar todos los OLTs activos con credenciales}';

    protected $description = 'Importa la lista de ONUs desde OLTs VSOL GPON vía Telnet';

    public function handle(OltOnuSyncService $syncService): int
    {
        $oltId = $this->option('olt');
        $all = (bool) $this->option('all');

        if (! $oltId && ! $all) {
            $this->error('Indique --olt=ID o --all');

            return self::FAILURE;
        }

        $query = Olt::query()->where('estado', 'activo');
        if ($oltId) {
            $query->where('olt_id', $oltId);
        }

        $olts = $query->get()->filter(fn (Olt $o) => $o->tieneCredencialesGestion());

        if ($olts->isEmpty()) {
            $this->warn('No hay OLTs con IP y contraseña de gestión configuradas.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($olts as $olt) {
            $label = $olt->codigo ?? $olt->ip ?? '#'.$olt->olt_id;
            $this->line("Importando ONUs: {$label} ({$olt->ip})…");

            try {
                $result = $syncService->importarDesdeOlt($olt);
                $this->info('  ✓ '.$result['message']);
                $ok++;
            } catch (Throwable $e) {
                $this->error('  ✗ '.$e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Finalizado: {$ok} OK, {$fail} error(es).");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
