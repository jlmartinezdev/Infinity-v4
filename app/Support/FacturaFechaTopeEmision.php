<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Fecha tope para enviar el DE a SIFEN. Se puede seguir facturando con fecha igual o anterior.
 */
class FacturaFechaTopeEmision
{
    public const CLAVE = 'fe_fecha_tope_emision';

    public static function obtener(): ?Carbon
    {
        $raw = FacturacionParametro::obtener(self::CLAVE, '');
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable) {
            try {
                return Carbon::parse($raw)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function establecer(?string $fecha): void
    {
        $normalizada = self::normalizar($fecha);
        if ($normalizada === null) {
            FacturacionParametro::establecer(self::CLAVE, '', 'Fecha tope de emisión (vacío = sin tope)');

            return;
        }

        FacturacionParametro::establecer(
            self::CLAVE,
            $normalizada,
            'Fecha tope para enviar a SIFEN (Y-m-d). Se puede facturar con esa fecha o una anterior.'
        );
    }

    public static function normalizar(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $fecha)->toDateString();
        } catch (\Throwable) {
            try {
                return Carbon::parse($fecha)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * @return array{
     *     ok: bool,
     *     tope: ?Carbon,
     *     fecha: Carbon,
     *     vencida: bool,
     *     message: ?string
     * }
     */
    public static function evaluar(Carbon|string|null $fechaEmision = null): array
    {
        $fecha = self::parseFecha($fechaEmision) ?? now()->startOfDay();

        return self::resultado(self::obtener(), $fecha);
    }

    /**
     * @return array{
     *     ok: bool,
     *     tope: ?Carbon,
     *     fecha: Carbon,
     *     vencida: bool,
     *     message: ?string
     * }
     */
    public static function resultado(?Carbon $tope, Carbon $fecha, ?Carbon $hoy = null): array
    {
        $tope = $tope?->copy()->startOfDay();
        $fecha = $fecha->copy()->startOfDay();
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        $vencida = $tope !== null && $hoy->gt($tope);

        if ($tope === null || $fecha->lte($tope)) {
            return [
                'ok' => true,
                'tope' => $tope,
                'fecha' => $fecha,
                'vencida' => $vencida,
                'message' => null,
            ];
        }

        return [
            'ok' => false,
            'tope' => $tope,
            'fecha' => $fecha,
            'vencida' => $vencida,
            'message' => sprintf(
                'No se puede enviar a SIFEN: la fecha de emisión %s es posterior al tope (%s). Podés seguir facturando con fecha igual o anterior al tope.',
                $fecha->format('d/m/Y'),
                $tope->format('d/m/Y')
            ),
        ];
    }

    public static function asegurar(Carbon|string|null $fechaEmision = null): void
    {
        $eval = self::evaluar($fechaEmision);
        if (! $eval['ok']) {
            throw new InvalidArgumentException((string) $eval['message']);
        }
    }

    /**
     * @return array{
     *     fecha: ?string,
     *     label: ?string,
     *     sin_tope: bool,
     *     vencida: bool,
     *     fecha_sugerida: string,
     *     fecha_maxima: string
     * }
     */
    public static function resumen(): array
    {
        $tope = self::obtener();

        return [
            'fecha' => $tope?->toDateString(),
            'label' => $tope?->format('d/m/Y'),
            'sin_tope' => $tope === null,
            'vencida' => $tope !== null && now()->startOfDay()->gt($tope),
            'fecha_sugerida' => self::fechaSugeridaEmision(),
            'fecha_maxima' => self::fechaMaximaDocumento(),
        ];
    }

    /**
     * Fecha sugerida para el documento: hoy, o el tope si hoy ya lo pasó.
     */
    public static function fechaSugeridaEmision(?Carbon $hoy = null): string
    {
        return self::fechaMaximaDocumento($hoy);
    }

    /**
     * Tope de fecha en el formulario (no posterior a hoy ni al tope SIFEN).
     */
    public static function fechaMaximaDocumento(?Carbon $hoy = null): string
    {
        $hoyStr = ($hoy ?? now())->toDateString();
        $tope = self::obtener();

        return self::menorEntreHoyYTope($hoyStr, $tope?->toDateString());
    }

    public static function menorEntreHoyYTope(string $hoyYmd, ?string $topeYmd): string
    {
        if ($topeYmd === null || $topeYmd === '') {
            return $hoyYmd;
        }

        return $topeYmd < $hoyYmd ? $topeYmd : $hoyYmd;
    }

    private static function parseFecha(Carbon|string|null $fecha): ?Carbon
    {
        if ($fecha instanceof Carbon) {
            return $fecha->copy()->startOfDay();
        }
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
