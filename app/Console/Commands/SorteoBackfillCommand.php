<?php

namespace App\Console\Commands;

use App\Models\SorteoCampana;
use App\Services\Sorteo\SorteoService;
use Illuminate\Console\Command;

class SorteoBackfillCommand extends Command
{
    protected $signature = 'sorteo:backfill
                            {--dry-run : Solo mostrar cuántos clientes y tickets se generarían}
                            {--solo= : tickets o bienvenida; vacío procesa los dos}
                            {--campana= : Id de campaña. Por defecto la activa}';

    protected $description = 'Crea tickets de la campaña para pagos ya cargados y el cupón de bienvenida de la app.';

    public function handle(SorteoService $sorteo): int
    {
        $campana = $this->option('campana')
            ? SorteoCampana::query()->find($this->option('campana'))
            : SorteoCampana::activa();
        if (! $campana) {
            $this->error('No hay campaña para el backfill.');

            return self::FAILURE;
        }

        $solo = (string) $this->option('solo');
        $tickets = $solo === '' || $solo === 'tickets';
        $bienvenida = $solo === '' || $solo === 'bienvenida';
        $preview = $sorteo->previsualizar($campana, $tickets, $bienvenida);

        $this->info($campana->nombre.($this->option('dry-run') ? ' (dry-run)' : ''));
        if ($tickets) {
            $this->line('Tickets nuevos: '.$preview['tickets']);
            $this->line('Tickets que ya estaban: '.$preview['ya']);
            $this->line('Anticipos en saldo a favor, a confirmar cuando exista la factura del período: '.$preview['anticipos']);
        }
        if ($bienvenida) {
            $this->line('Cupones de bienvenida: '.$preview['bienvenida']);
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $hecho = $sorteo->ejecutarBackfill($campana, $tickets, $bienvenida);
        if ($tickets) {
            $this->info('Tickets creados: '.$hecho['tickets']);
        }
        if ($bienvenida) {
            $this->info('Cupones de bienvenida creados: '.$hecho['bienvenida']);
        }

        return self::SUCCESS;
    }
}
