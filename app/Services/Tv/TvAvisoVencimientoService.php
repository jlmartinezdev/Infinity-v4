<?php

namespace App\Services\Tv;

use App\Models\TvAvisoNotificacion;
use App\Models\TvCuenta;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\TvAvisoConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TvAvisoVencimientoService
{
    public function __construct(
        private readonly WhatsAppOutboundNotifier $whatsapp,
    ) {}

    /**
     * @return array{candidatas: int, enviadas: int, omitidas: int, errores: int}
     */
    public function ejecutar(?Carbon $referencia = null): array
    {
        $stats = ['candidatas' => 0, 'enviadas' => 0, 'omitidas' => 0, 'errores' => 0];

        if (! TvAvisoConfig::enabled()) {
            Log::info('[TV aviso] Deshabilitado');

            return $stats;
        }

        $destinatarios = TvAvisoConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            Log::info('[TV aviso] Sin destinatarios configurados');

            return $stats;
        }

        $diasAntes = TvAvisoConfig::diasAntes();
        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();

        $cuentas = TvCuenta::query()->orderBy('nombre')->get();
        foreach ($cuentas as $cuenta) {
            $vencimiento = $cuenta->fechaVencimientoReferencia($hoy);
            $dias = $cuenta->diasParaVencimiento($hoy);

            if ($dias > $diasAntes) {
                continue;
            }

            $stats['candidatas']++;

            $ya = TvAvisoNotificacion::query()
                ->where('tv_cuenta_id', $cuenta->id)
                ->whereDate('fecha_vencimiento', $vencimiento->toDateString())
                ->exists();

            if ($ya) {
                $stats['omitidas']++;

                continue;
            }

            try {
                $ok = $this->whatsapp->tvVencimiento($cuenta, $destinatarios, $dias, $vencimiento);
                if ($ok) {
                    TvAvisoNotificacion::query()->create([
                        'tv_cuenta_id' => $cuenta->id,
                        'fecha_vencimiento' => $vencimiento->toDateString(),
                        'enviado_at' => now(),
                    ]);
                    $stats['enviadas']++;
                } else {
                    $stats['errores']++;
                }
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::error('[TV aviso] Error: '.$e->getMessage(), [
                    'tv_cuenta_id' => $cuenta->id,
                    'exception' => $e,
                ]);
            }
        }

        return $stats;
    }
}
