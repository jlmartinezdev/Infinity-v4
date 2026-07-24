<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMensaje;
use App\Models\WhatsappRegistroOtp;
use Illuminate\Support\Facades\Log;

/**
 * OTP invertido: el cliente escribe por WhatsApp, recibimos el from,
 * generamos PIN (BD + expiración) y lo validamos en POST solicitud-alta.
 */
class WhatsAppRegistroOtpService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppPhoneNormalizer $phones,
    ) {}

    /**
     * Si el mensaje pide el código, genera OTP, lo guarda y responde por WhatsApp.
     *
     * @return bool true si el mensaje era un pedido de OTP (aunque falle el envío)
     */
    public function intentarEmitirDesdeMensaje(WhatsappMensaje $mensaje): bool
    {
        if (! $mensaje->esEntrada()) {
            return false;
        }

        $cuerpo = trim((string) ($mensaje->cuerpo ?? ''));
        if ($cuerpo === '' || ! $this->esPedidoDeCodigo($cuerpo)) {
            return false;
        }

        $telefono = $this->phones->normalize($mensaje->telefono);
        if (! $telefono) {
            Log::warning('[WhatsApp OTP] Pedido sin teléfono válido', [
                'raw' => $mensaje->telefono,
            ]);

            return true;
        }

        $codigo = $this->generarYGuardar($telefono);
        $texto = $this->textoConCodigo($codigo);

        try {
            $this->whatsapp->sendText($telefono, $texto, [
                'contexto_tipo' => 'registro_otp_emitido',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp OTP] No se pudo enviar PIN: '.$e->getMessage(), [
                'telefono' => $telefono,
            ]);
        }

        Log::info('[WhatsApp OTP] PIN emitido', [
            'telefono' => $telefono,
            'ttl_min' => $this->ttlMinutos(),
        ]);

        return true;
    }

    public function esPedidoDeCodigo(string $cuerpo): bool
    {
        $normalizado = mb_strtolower(trim($cuerpo), 'UTF-8');
        $normalizado = preg_replace('/\s+/u', ' ', $normalizado) ?? $normalizado;

        if (preg_match('/quiero\s+mi\s+c[oó]digo\s+de\s+verificaci[oó]n/iu', $normalizado)) {
            return true;
        }

        return (bool) preg_match('/^(hola[,!.]?\s*)?(quiero|necesito|dame|solicito)\s+(mi\s+)?c[oó]digo(\s+de\s+verificaci[oó]n)?[.!]?\s*$/iu', $normalizado);
    }

    /**
     * Valida el OTP para el WhatsApp declarado y lo consume si es correcto.
     *
     * @return array{ok: bool, telefono_normalizado?: string, mensaje?: string}
     */
    public function validarYConsumir(string $whatsapp, string $codigoOtp): array
    {
        $codigo = preg_replace('/\D+/', '', trim($codigoOtp)) ?? '';
        if ($codigo === '' || strlen($codigo) < 4) {
            return [
                'ok' => false,
                'mensaje' => 'Código de verificación inválido o expirado',
            ];
        }

        $candidatos = $this->telefonosCandidatos($whatsapp);
        if ($candidatos === []) {
            Log::info('[WhatsApp OTP] Validación fallida: sin teléfono usable', [
                'whatsapp' => $whatsapp,
                'codigo_len' => strlen($codigo),
            ]);

            return [
                'ok' => false,
                'mensaje' => 'Código de verificación inválido o expirado',
            ];
        }

        $sufijos = [];
        foreach ($candidatos as $tel) {
            $sufijos[] = $this->sufijo($tel);
        }
        $sufijos = array_values(array_unique(array_filter($sufijos)));

        $otp = WhatsappRegistroOtp::query()
            ->vigentes()
            ->where('codigo', $codigo)
            ->where(function ($q) use ($candidatos, $sufijos) {
                $q->whereIn('telefono', $candidatos);
                if ($sufijos !== []) {
                    $q->orWhereIn('telefono_sufijo', $sufijos);
                }
            })
            ->orderByDesc('id')
            ->first();

        if (! $otp) {
            Log::info('[WhatsApp OTP] Validación fallida', [
                'whatsapp' => $whatsapp,
                'candidatos' => $candidatos,
                'sufijos' => $sufijos,
                'codigo' => $codigo,
            ]);

            return [
                'ok' => false,
                'mensaje' => 'Código de verificación inválido o expirado',
            ];
        }

        $otp->update(['used_at' => now()]);

        // Invalidar otros OTP vigentes del mismo teléfono/sufijo
        WhatsappRegistroOtp::query()
            ->vigentes()
            ->where(function ($q) use ($otp) {
                $q->where('telefono', $otp->telefono)
                    ->orWhere('telefono_sufijo', $otp->telefono_sufijo);
            })
            ->where('id', '!=', $otp->id)
            ->update(['used_at' => now()]);

        Log::info('[WhatsApp OTP] Validación OK', [
            'telefono' => $otp->telefono,
            'whatsapp_enviado' => $whatsapp,
        ]);

        return [
            'ok' => true,
            'telefono_normalizado' => $otp->telefono,
        ];
    }

    public function generarYGuardar(string $telefonoNormalizado): string
    {
        $codigo = (string) random_int(1000, 9999);
        $ttlMin = $this->ttlMinutos();
        $sufijo = $this->sufijo($telefonoNormalizado);

        // Un solo PIN vigente por número (y por sufijo)
        WhatsappRegistroOtp::query()
            ->vigentes()
            ->where(function ($q) use ($telefonoNormalizado, $sufijo) {
                $q->where('telefono', $telefonoNormalizado);
                if ($sufijo !== '') {
                    $q->orWhere('telefono_sufijo', $sufijo);
                }
            })
            ->update(['used_at' => now()]);

        WhatsappRegistroOtp::create([
            'telefono' => $telefonoNormalizado,
            'telefono_sufijo' => $sufijo,
            'codigo' => $codigo,
            'expires_at' => now()->addMinutes($ttlMin),
        ]);

        return $codigo;
    }

    public function ttlMinutos(): int
    {
        return max(5, (int) config('whatsapp.registro_otp_ttl_minutes', 15));
    }

    public function textoConCodigo(string $codigo): string
    {
        $plantilla = (string) config(
            'whatsapp.registro_otp_text',
            '¡Hola! Tu código de verificación para la aplicación es: {codigo}'
        );

        return str_replace('{codigo}', $codigo, $plantilla);
    }

    /**
     * Normalizaciones posibles del número declarado en la app (PY/AR/local).
     *
     * @return list<string>
     */
    public function telefonosCandidatos(string $whatsapp): array
    {
        $out = [];
        $digits = preg_replace('/\D+/', '', $whatsapp) ?? '';
        if ($digits === '') {
            return [];
        }

        $primary = $this->phones->normalize($whatsapp);
        if ($primary) {
            $out[] = $primary;
        }

        foreach ($this->phones->variants($whatsapp) as $variant) {
            $norm = $this->phones->normalize($variant);
            if ($norm) {
                $out[] = $norm;
            }
        }

        // Argentina: si vino sin país (10 dígitos tipo 11XXXXXXXX), probar 54/549
        if (strlen($digits) === 10 && str_starts_with($digits, '11')) {
            $out[] = '54'.$digits;
            $out[] = '549'.$digits;
            $out[] = $this->phones->normalize('54'.$digits) ?? '';
            $out[] = $this->phones->normalize('549'.$digits) ?? '';
        }

        // Argentina con 15 local legacy u otros 10–11 dígitos: probar 549 + últimos 10
        if (strlen($digits) >= 10 && strlen($digits) <= 11 && ! str_starts_with($digits, '595') && ! str_starts_with($digits, '54')) {
            $last10 = substr($digits, -10);
            $out[] = '54'.$last10;
            $out[] = '549'.$last10;
            $out[] = $this->phones->normalize('54'.$last10) ?? '';
            $out[] = $this->phones->normalize('549'.$last10) ?? '';
        }

        // Paraguay local 09XXXXXXXX ya lo cubre el normalizer; asegurar E.164
        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            $out[] = $this->phones->normalize($digits) ?? '';
        }

        return array_values(array_unique(array_filter($out)));
    }

    public function sufijo(string $telefonoNormalizado): string
    {
        $digits = preg_replace('/\D+/', '', $telefonoNormalizado) ?? '';

        // 8 dígitos finales: cubre AR (54911… ↔ 11…) y PY (595981… ↔ 0981…)
        if (strlen($digits) >= 8) {
            return substr($digits, -8);
        }

        return $digits;
    }
}
