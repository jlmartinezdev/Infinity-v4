<?php

namespace App\Console\Commands;

use App\Services\Tpago\TpagoCallbackService;
use Illuminate\Console\Command;

class TpagoVerificarPendientesCommand extends Command
{
    protected $signature = 'tpago:verificar-pendientes';

    protected $description = 'Reintenta registrar cobros TPago cuyo callback aún no fue confirmado por la API';

    public function handle(TpagoCallbackService $callbacks): int
    {
        $n = $callbacks->reintentarPendientes();
        $this->info("Links TPago revisados: {$n}");

        return self::SUCCESS;
    }
}
