<?php

namespace App\Jobs;

use App\Models\Factura;
use App\Services\Sifen\SifenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConsultarLoteSifenJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $facturaId) {}

    public function uniqueId(): string
    {
        return 'sifen-consultar-lote-'.$this->facturaId;
    }

    public function handle(SifenService $sifenService): void
    {
        $factura = Factura::query()->find($this->facturaId);
        if (! $factura) {
            return;
        }

        if ($factura->estado === 'emitida' && $factura->set_estado_envio === 'autorizado') {
            return;
        }

        if (blank($factura->set_nro_lote)) {
            return;
        }

        try {
            $resultado = $sifenService->consultarResultadoLote($factura);
            Log::info('[SIFEN job] Consulta de lote procesada', [
                'factura_id' => $this->facturaId,
                'aprobado' => (bool) ($resultado['sifen']['aprobado'] ?? $resultado['aprobado'] ?? false),
                'cdc' => $resultado['cdc'] ?? $factura->set_cdc,
            ]);
        } catch (\Throwable $e) {
            $factura->refresh();
            // Si sigue pendiente, no marcar rechazo: DNIT aún puede estar procesando.
            if (! $factura->lotePendienteSifen() && $factura->set_estado_envio !== 'rechazado') {
                Log::warning('[SIFEN job] Consulta de lote con error', [
                    'factura_id' => $this->facturaId,
                    'error' => $e->getMessage(),
                ]);
            } else {
                Log::info('[SIFEN job] Consulta de lote aún pendiente', [
                    'factura_id' => $this->facturaId,
                    'mensaje' => $e->getMessage(),
                ]);
            }
        }
    }
}
