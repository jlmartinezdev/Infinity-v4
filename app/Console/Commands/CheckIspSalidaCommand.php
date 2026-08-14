<?php

namespace App\Console\Commands;

use App\Services\Monitoreo\IspFailoverService;
use Illuminate\Console\Command;

class CheckIspSalidaCommand extends Command
{
    protected $signature = 'mikrotik:check-isp-salida';

    protected $description = 'Ping 1.1.1.1 vía ISP 1 en el router de borde; si falla, failover a ISP 2 y aviso WhatsApp.';

    public function handle(IspFailoverService $failover): int
    {
        $r = $failover->verificar();

        if (! ($r['ok'] ?? false)) {
            $this->warn($r['message'] ?? 'Chequeo ISP omitido o con error de configuración.');

            return self::SUCCESS;
        }

        $pingOk = $r['ping_ok'] ?? null;
        if ($pingOk === true) {
            $this->info($r['message']);
        } elseif ($pingOk === false) {
            $this->warn($r['message']);
        } else {
            $this->line($r['message']);
        }

        if (($r['accion'] ?? 'ninguna') !== 'ninguna' && ($r['accion'] ?? '') !== 'omitido') {
            $this->comment('Acción: '.$r['accion']);
        }

        return self::SUCCESS;
    }
}
