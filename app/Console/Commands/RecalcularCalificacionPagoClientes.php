<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Services\FacturacionService;
use Illuminate\Console\Command;

class RecalcularCalificacionPagoClientes extends Command
{
    protected $signature = 'clientes:recalcular-calificacion-pago';

    protected $description = 'Recalcula la calificación de pago (Malo/Bueno/Excelente) de todos los clientes según el porcentaje de cobros pagados a fecha.';

    public function handle(FacturacionService $facturacionService): int
    {
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])->get();
        $bar = $this->output->createProgressBar($clientes->count());
        $bar->start();

        $actualizados = 0;
        foreach ($clientes as $cliente) {
            $facturacionService->recalcularCalificacionPagoCliente($cliente);
            $actualizados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Procesados {$actualizados} clientes.");

        return self::SUCCESS;
    }
}
