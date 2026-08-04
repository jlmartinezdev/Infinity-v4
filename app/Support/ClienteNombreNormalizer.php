<?php

namespace App\Support;

/**
 * Convierte nombres con guion bajo (ej. Juan_Carlos_Perez) a formato legible en MAYÚSCULAS.
 */
final class ClienteNombreNormalizer
{
    public static function necesitaNormalizacion(?string $nombre, ?string $apellido): bool
    {
        return str_contains((string) $nombre, '_')
            || str_contains((string) $apellido, '_');
    }

    /**
     * Omite duplicados u otros registros marcados con sufijo _2 al final.
     */
    public static function debeOmitir(?string $nombre, ?string $apellido): bool
    {
        foreach ([trim((string) $nombre), trim((string) $apellido)] as $valor) {
            if ($valor !== '' && preg_match('/_2$/i', $valor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{nombre: string, apellido: string|null, cambio: bool, omitir: bool}
     */
    public static function normalizar(?string $nombre, ?string $apellido): array
    {
        $nombre = trim((string) $nombre);
        $apellido = trim((string) $apellido);

        if (self::debeOmitir($nombre, $apellido)) {
            return [
                'nombre' => $nombre,
                'apellido' => $apellido !== '' ? $apellido : null,
                'cambio' => false,
                'omitir' => true,
            ];
        }

        if (! self::necesitaNormalizacion($nombre, $apellido)) {
            return [
                'nombre' => $nombre,
                'apellido' => $apellido !== '' ? $apellido : null,
                'cambio' => false,
                'omitir' => false,
            ];
        }

        if ($apellido === '' && str_contains($nombre, '_')) {
            [$nombreNorm, $apellidoNorm] = self::partirNombreCompletoUnderscore($nombre);

            return [
                'nombre' => self::limitar($nombreNorm),
                'apellido' => $apellidoNorm !== '' ? self::limitar($apellidoNorm) : null,
                'cambio' => true,
                'omitir' => false,
            ];
        }

        $nombreNorm = self::normalizarSegmento($nombre);
        $apellidoNorm = $apellido !== '' ? self::normalizarSegmento($apellido) : null;

        return [
            'nombre' => self::limitar($nombreNorm),
            'apellido' => $apellidoNorm !== '' ? self::limitar($apellidoNorm) : null,
            'cambio' => $nombreNorm !== $nombre || $apellidoNorm !== ($apellido !== '' ? $apellido : null),
            'omitir' => false,
        ];
    }

    /**
     * Formato nombre_nombre_apellido → nombre = primeros segmentos, apellido = último(s).
     *
     * @return array{0: string, 1: string}
     */
    private static function partirNombreCompletoUnderscore(string $raw): array
    {
        $partes = array_values(array_filter(
            array_map('trim', explode('_', $raw)),
            static fn (string $p): bool => $p !== ''
        ));

        $total = count($partes);

        if ($total === 0) {
            return ['', ''];
        }

        if ($total === 1) {
            return [self::normalizarSegmento($partes[0]), ''];
        }

        if ($total === 2) {
            return [
                self::normalizarSegmento($partes[0]),
                self::normalizarSegmento($partes[1]),
            ];
        }

        if ($total === 3) {
            return [
                self::normalizarSegmento(implode(' ', array_slice($partes, 0, 2))),
                self::normalizarSegmento($partes[2]),
            ];
        }

        $apellido = self::normalizarSegmento(implode(' ', array_slice($partes, -2)));
        $nombre = self::normalizarSegmento(implode(' ', array_slice($partes, 0, -2)));

        return [$nombre, $apellido];
    }

    private static function normalizarSegmento(string $value): string
    {
        $value = str_replace('_', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        if ($value === '') {
            return '';
        }

        return mb_strtoupper($value, 'UTF-8');
    }

    private static function limitar(string $value): string
    {
        return mb_substr(trim($value), 0, 100);
    }
}
