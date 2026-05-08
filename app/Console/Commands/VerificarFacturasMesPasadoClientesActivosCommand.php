<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VerificarFacturasMesPasadoClientesActivosCommand extends Command
{
    protected $signature = 'facturas:verificar-mes-pasado-clientes-activos
                            {--solo-faltantes : Mostrar solo clientes sin factura del mes pasado}';

    protected $description = 'Verifica si todos los clientes activos con servicio tienen factura interna del mes pasado.';

    public function handle(): int
    {
        $soloFaltantes = (bool) $this->option('solo-faltantes');

        $periodoDesde = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $periodoHasta = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $clientesActivos = Cliente::query()
            ->where('estado', 'activo')
            ->whereHas('servicios')
            ->orderBy('cliente_id')
            ->get(['cliente_id', 'nombre', 'apellido']);

        $clienteIds = $clientesActivos->pluck('cliente_id')->all();

        $clientesConFactura = FacturaInterna::query()
            ->whereIn('cliente_id', $clienteIds)
            ->whereDate('periodo_hasta', $periodoHasta->toDateString())
            ->pluck('cliente_id')
            ->unique()
            ->values();

        $faltantes = $clientesActivos
            ->whereNotIn('cliente_id', $clientesConFactura)
            ->values();

        $this->info(sprintf(
            'Verificación de facturación del mes pasado (%s a %s)',
            $periodoDesde->toDateString(),
            $periodoHasta->toDateString()
        ));
        $this->line('Clientes activos con servicio: '.$clientesActivos->count());
        $this->line('Clientes con factura del período: '.$clientesConFactura->count());
        $this->line('Clientes sin factura del período: '.$faltantes->count());

        if ($faltantes->isEmpty()) {
            $this->newLine();
            $this->info('OK: todos los clientes activos con servicio tienen factura del mes pasado.');

            return self::SUCCESS;
        }

        if (! $soloFaltantes) {
            $this->newLine();
            $this->warn('Listado de clientes sin factura del mes pasado:');
            foreach ($faltantes as $cliente) {
                $this->line(sprintf(
                    ' - Cliente %d: %s %s',
                    (int) $cliente->cliente_id,
                    (string) $cliente->nombre,
                    (string) $cliente->apellido
                ));
            }
        }

        return self::FAILURE;
    }
}
