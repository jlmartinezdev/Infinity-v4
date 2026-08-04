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

class EmitirFacturaSifenJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public int $facturaId) {}

    public function uniqueId(): string
    {
        return 'sifen-emitir-'.$this->facturaId;
    }

    public function handle(SifenService $sifenService): void
    {
        $factura = Factura::query()->find($this->facturaId);
        if (! $factura) {
            return;
        }

        if ($factura->estado !== 'borrador') {
            return;
        }

        // Ya enviada a lote o autorizada: no reemitir.
        if ($factura->lotePendienteSifen() || $factura->set_estado_envio === 'autorizado') {
            return;
        }

        try {
            $resultado = $sifenService->emitirDocumento($factura, true);
            Log::info('[SIFEN job] Emisión procesada', [
                'factura_id' => $this->facturaId,
                'lote_pendiente' => (bool) ($resultado['lote_pendiente'] ?? false),
                'cdc' => $resultado['cdc'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $factura->refresh();
            if ($factura->set_estado_envio !== 'rechazado' && ! $factura->lotePendienteSifen()) {
                $factura->update([
                    'set_estado_envio' => 'rechazado',
                    'set_xml_respuesta' => mb_substr($e->getMessage(), 0, 65000),
                ]);
            }

            Log::error('[SIFEN job] Emisión fallida', [
                'factura_id' => $this->facturaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
