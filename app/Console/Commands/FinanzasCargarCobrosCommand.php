<?php

namespace App\Console\Commands;

use App\Services\Finanzas\FinanzasCobroPuente;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FinanzasCargarCobrosCommand extends Command
{
    protected $signature = 'finanzas:cargar-cobros {mes? : Mes en formato YYYY-MM}';

    protected $description = 'Carga en el libro los cobros de un mes que todavía no están contabilizados.';

    public function handle(FinanzasCobroPuente $puente): int
    {
        $mes = $this->argument('mes') ?: now()->format('Y-m');
        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfMonth();
        } catch (\Throwable) {
            $this->error('El mes tiene que tener el formato YYYY-MM.');

            return self::FAILURE;
        }

        $previa = $puente->previaMes($fecha);
        $this->line($fecha->translatedFormat('F Y'));
        $this->line('Recibos: '.$previa['total']);
        $this->line('Ya en el libro: '.$previa['ya_estaban']);
        $this->line('Por cargar: '.$previa['pendientes'].' · '.number_format($previa['monto_pendiente'], 0, ',', '.'));
        $this->line('Anteriores al corte: '.$previa['antes_de_corte']);
        $this->line('Sin cuenta: '.$previa['sin_cuenta']);

        $resultado = $puente->cargarMes($fecha);
        $this->info('Cargados: '.$resultado['cargados'].' · '.number_format($resultado['monto_cargado'], 0, ',', '.'));
        $this->line('Ya estaban: '.$resultado['ya_estaban'].' · Omitidos: '.$resultado['omitidos']);
        foreach ($resultado['errores'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
