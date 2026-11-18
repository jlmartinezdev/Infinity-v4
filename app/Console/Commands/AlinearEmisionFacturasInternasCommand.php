<?php

namespace App\Console\Commands;

use App\Models\FacturacionParametro;
use App\Models\FacturaInterna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AlinearEmisionFacturasInternasCommand extends Command
{
    protected $signature = 'facturas:alinear-emision-al-dia-1
                            {--dry-run : Mostrar cuántas fechas cambiarían, sin grabar}';

    protected $description = 'Pasa la fecha de emisión del día 20 al día 1 del mes siguiente en facturas cobradas y pendientes. Las especiales no se tocan. Deja el día de facturación automática en 1.';

    public function handle(): int
    {
        $query = DB::table('factura_internas')
            ->whereRaw('DAY(fecha_emision) = 20')
            ->whereIn('estado', ['pendiente', 'emitida', 'pagada'])
            ->where(function ($q) {
                $q->whereNull('tipo_factura')
                    ->orWhere('tipo_factura', '!=', FacturaInterna::TIPO_SERVICIO_ESPECIAL);
            });

        $cantidad = (clone $query)->count();
        $this->info("Facturas con emisión el día 20 (cobradas o pendientes, sin especiales): {$cantidad}.");

        if ($this->option('dry-run')) {
            $this->info('Modo dry-run: no se modificó nada.');

            return self::SUCCESS;
        }

        $actualizadas = $query->update([
            'fecha_emision' => DB::raw("DATE_ADD(DATE_FORMAT(fecha_emision, '%Y-%m-01'), INTERVAL 1 MONTH)"),
            'updated_at' => now(),
        ]);

        FacturacionParametro::establecer(
            'dia_creacion_factura_automatica',
            1,
            'Día del mes para crear las facturas del mes anterior'
        );

        $this->info("Fechas de emisión actualizadas: {$actualizadas}.");
        $this->info('El día de facturación automática quedó en 1.');

        return self::SUCCESS;
    }
}
