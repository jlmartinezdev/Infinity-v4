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

    /**
     * Envía avisos de prueba para todas las cuentas en ventana de aviso (por vencer / vencidas).
     * No registra envío ni exige avisos automáticos activos.
     *
     * @return array{ok: bool, message: string, candidatas?: int, enviadas?: int, errores?: int, destinatarios?: int}
     */
    public function probar(?Carbon $referencia = null): array
    {
        if (! app(\App\Services\WhatsApp\WhatsAppService::class)->isConfigured()) {
            return ['ok' => false, 'message' => 'WhatsApp no está configurado.'];
        }

        $destinatarios = TvAvisoConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'No hay destinatarios configurados. Guardá al menos un usuario con teléfono WhatsApp.',
            ];
        }

        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();
        $diasAntes = TvAvisoConfig::diasAntes();

        $candidatas = TvCuenta::query()
            ->orderBy('nombre')
            ->get()
            ->filter(fn (TvCuenta $c) => $c->diasParaVencimiento($hoy) <= $diasAntes)
            ->values();

        if ($candidatas->isEmpty()) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'No hay cuentas por vencer ni vencidas dentro de los %d día(s) de anticipación configurados.',
                    $diasAntes
                ),
            ];
        }

        $enviadas = 0;
        $errores = 0;
        $nombresOk = [];

        foreach ($candidatas as $cuenta) {
            $vencimiento = $cuenta->fechaVencimientoReferencia($hoy);
            $dias = $cuenta->diasParaVencimiento($hoy);

            try {
                $ok = $this->whatsapp->tvVencimiento($cuenta, $destinatarios, $dias, $vencimiento, true);
                if ($ok) {
                    $enviadas++;
                    $nombresOk[] = $cuenta->nombre ?: ('#'.$cuenta->id);
                } else {
                    $errores++;
                }
            } catch (\Throwable $e) {
                $errores++;
                Log::error('[TV aviso] Error en prueba: '.$e->getMessage(), [
                    'tv_cuenta_id' => $cuenta->id,
                    'exception' => $e,
                ]);
            }
        }

        if ($enviadas === 0) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'No se pudo enviar ninguna de las %d cuenta(s) candidata(s). Revisá teléfonos WhatsApp y el log.',
                    $candidatas->count()
                ),
                'candidatas' => $candidatas->count(),
                'enviadas' => 0,
                'errores' => $errores,
                'destinatarios' => $destinatarios->count(),
            ];
        }

        $lista = implode(', ', array_slice($nombresOk, 0, 8));
        if (count($nombresOk) > 8) {
            $lista .= '…';
        }

        return [
            'ok' => true,
            'message' => sprintf(
                'Prueba: %d de %d aviso(s) enviados a %d destinatario(s) [%s]. No se registraron como avisos reales.',
                $enviadas,
                $candidatas->count(),
                $destinatarios->count(),
                $lista
            ),
            'candidatas' => $candidatas->count(),
            'enviadas' => $enviadas,
            'errores' => $errores,
            'destinatarios' => $destinatarios->count(),
        ];
    }
}
