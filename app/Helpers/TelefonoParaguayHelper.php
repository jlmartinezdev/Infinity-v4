<?php

namespace App\Helpers;

use App\Models\Cliente;

/**
 * Normalización de teléfonos móviles Paraguay para comparar equivalentes
 * (p. ej. 0981123123 y +595981123123).
 */
class TelefonoParaguayHelper
{
    /**
     * Devuelve solo dígitos en forma local móvil 09XXXXXXXX cuando aplique, o dígitos sin espacios.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $trimmed);
        if ($digits === '') {
            return null;
        }
        // Formato internacional 595 + 9 dígitos nacionales (móvil 9…)
        if (str_starts_with($digits, '595')) {
            $national = substr($digits, 3);
            if (strlen($national) === 9 && str_starts_with($national, '9')) {
                return '0'.$national;
            }
        }
        // Ya con 0 inicial (09…)
        if (str_starts_with($digits, '0')) {
            return $digits;
        }
        // Nueve dígitos empezando en 9 (sin 0)
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '0'.$digits;
        }

        return $digits;
    }

    /**
     * Muestra un móvil paraguayo como 595 9XX XXXXXX (ej. 595 984 995239).
     */
    public static function formatear(?string $raw): string
    {
        $original = trim((string) $raw);
        if ($original === '') {
            return '';
        }

        $norm = self::normalize($raw);
        if ($norm !== null && str_starts_with($norm, '09') && strlen($norm) === 10) {
            $nacional = substr($norm, 1);

            return '595 '.substr($nacional, 0, 3).' '.substr($nacional, 3);
        }

        $digits = preg_replace('/\D/', '', $original) ?? '';

        return $digits !== '' ? $digits : $original;
    }

    /**
     * Indica si otro cliente (distinto de $excludeClienteId) con al menos un pedido
     * tiene el mismo número normalizado.
     */
    public static function telefonoUsadoPorOtroClienteConPedido(string $normalized, ?int $excludeClienteId): bool
    {
        $query = Cliente::query()
            ->has('pedidos')
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '');

        if ($excludeClienteId !== null) {
            $query->where('cliente_id', '!=', $excludeClienteId);
        }

        foreach ($query->cursor() as $cliente) {
            $otro = self::normalize($cliente->telefono);
            if ($otro !== null && $otro !== '' && $otro === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Busca clientes con el mismo teléfono normalizado (cualquier cliente con teléfono en BD).
     *
     * @return array{0: ?Cliente, 1: ?Cliente} [cliente_conflicto, cliente_excluido_coincidente] conflicto = otro id; mismo = coincide con exclude_cliente_id
     */
    public static function buscarPorTelefonoNormalizado(?string $raw, ?int $excludeClienteId): array
    {
        $norm = self::normalize($raw);
        if ($norm === null || $norm === '') {
            return [null, null];
        }

        $conflicto = null;
        $mismo = null;

        foreach (Cliente::query()->whereNotNull('telefono')->where('telefono', '!=', '')->cursor() as $cliente) {
            $cn = self::normalize($cliente->telefono);
            if ($cn !== $norm) {
                continue;
            }
            $cid = (int) $cliente->cliente_id;
            if ($excludeClienteId !== null && $cid === (int) $excludeClienteId) {
                $mismo = $cliente;

                continue;
            }
            $conflicto = $cliente;
            break;
        }

        return [$conflicto, $mismo];
    }
}
