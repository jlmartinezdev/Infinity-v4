<?php

namespace App\Console\Commands;

use App\Models\FacturaInterna;
use App\Services\ClientePushNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FcmAvisarFacturasPorVencerCommand extends Command
{
    protected $signature = 'fcm:avisar-facturas-por-vencer
                            {--dias= : Días antes del vencimiento (default: parámetro notificacion_dias_antes)}
                            {--dry-run : Solo listar, no enviar}';

    protected $description = 'Push FCM a clientes con factura por vencer en N días (saldo pendiente)';

    public function handle(ClientePushNotifier $notifier): int
    {
        $dias = $this->option('dias') !== null && $this->option('dias') !== ''
            ? max(1, (int) $this->option('dias'))
            : ClientePushNotifier::diasAntesVencimiento();

        $fechaObjetivo = Carbon::today()->addDays($dias)->toDateString();
        $dry = (bool) $this->option('dry-run');

        $query = FacturaInterna::query()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->whereDate('fecha_vencimiento', $fechaObjetivo)
            ->whereNotIn('estado', ['anulada', 'cancelada', 'pagada']);

        $candidatas = 0;
        $enviadas = 0;
        $omitidas = 0;

        $this->info("Objetivo: vencen el {$fechaObjetivo} ({$dias} día(s) desde hoy)".($dry ? ' [dry-run]' : ''));

        $query->orderBy('id')->chunkById(100, function ($facturas) use ($notifier, $dias, $dry, &$candidatas, &$enviadas, &$omitidas) {
            foreach ($facturas as $factura) {
                if ((float) $factura->saldo_pendiente <= 0.009) {
                    $omitidas++;

                    continue;
                }
                $candidatas++;
                $nombre = trim(($factura->cliente->nombre ?? '').' '.($factura->cliente->apellido ?? ''));
                $this->line("  #{$factura->id} {$nombre} saldo=".number_format((float) $factura->saldo_pendiente, 0, ',', '.'));

                if ($dry) {
                    continue;
                }
                $notifier->facturaPorVencer($factura, $dias);
                $enviadas++;
            }
        });

        $this->table(
            ['candidatas', 'enviadas', 'omitidas_sin_saldo', 'dias'],
            [[$candidatas, $dry ? 0 : $enviadas, $omitidas, $dias]]
        );

        if (! $dry) {
            \App\Support\ScheduleOnceAfter::markDone('fcm-facturas');
        }

        return self::SUCCESS;
    }
}
