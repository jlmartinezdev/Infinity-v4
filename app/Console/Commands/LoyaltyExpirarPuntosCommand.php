<?php

namespace App\Console\Commands;

use App\Services\Loyalty\PuntosService;
use Illuminate\Console\Command;

class LoyaltyExpirarPuntosCommand extends Command
{
    protected $signature = 'loyalty:expirar-puntos {--cliente= : ID de cliente opcional}';

    protected $description = 'Vence lotes de puntos Loyalty con FIFO (vence_at <= ahora)';

    public function handle(PuntosService $puntos): int
    {
        $clienteId = $this->option('cliente');
        $result = $puntos->expirarLotesVencidos(
            $clienteId !== null && $clienteId !== '' ? (int) $clienteId : null
        );

        $this->info(sprintf(
            'Lotes vencidos: %d · Puntos: %d · Clientes: %d',
            $result['lotes'],
            $result['puntos'],
            $result['clientes']
        ));

        \App\Support\ScheduleOnceAfter::markDone('loyalty-expirar');

        return self::SUCCESS;
    }
}
