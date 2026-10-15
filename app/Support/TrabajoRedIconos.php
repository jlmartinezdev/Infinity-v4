<?php

namespace App\Support;

/**
 * Íconos lineales (viewBox 24×24, currentColor) para trabajos de red.
 * Reusa el catálogo de tickets y completa claves de operación.
 */
class TrabajoRedIconos
{
    /**
     * @return array<string, string>
     */
    private static function extras(): array
    {
        return [
            'pin' => '<path d="M12 21s-7-6-7-12a7 7 0 0 1 14 0c0 6-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
            'flag' => '<path d="M4 21V4"/><path d="M4 4h12l-2 4 2 4H4"/>',
            'map' => '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
            'user' => '<path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'trash' => '<path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"/><path d="M10 11v6M14 11v6"/>',
            'check' => '<path d="M5 13l4 4L19 7"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
            'truck' => '<path d="M3 7h11v9H3z"/><path d="M14 10h3.5L21 13.5V16h-7z"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
            'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
            'external' => '<path d="M10 6H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/><path d="M14 4h6v6"/><path d="M10 14L20 4"/>',
        ];
    }

    public static function claveTipo(?string $tipo): string
    {
        return match ($tipo) {
            'mantenimiento' => 'herramienta',
            'mejora' => 'refrescar',
            'ampliacion' => 'plan',
            'reparo' => 'cortado',
            default => 'troncal',
        };
    }

    public static function claveActivo(?string $tipo): string
    {
        return match ($tipo) {
            'nodo' => 'troncal',
            'olt' => 'rayo',
            'router' => 'router',
            'caja_nap' => 'caja-nap',
            'salida_pon' => 'fibra',
            'ap' => 'wifi',
            default => 'internet',
        };
    }

    public static function svgTipo(?string $tipo, string $class = 'w-3.5 h-3.5'): string
    {
        return self::svg(self::claveTipo($tipo), $class);
    }

    public static function svgActivo(?string $tipo, string $class = 'w-4 h-4'): string
    {
        return self::svg(self::claveActivo($tipo), $class);
    }

    public static function svg(string $clave, string $class = 'w-3.5 h-3.5', float $strokeWidth = 1.9): string
    {
        $extras = self::extras();
        if (isset($extras[$clave])) {
            $class = e($class);

            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
                .'stroke-width="'.$strokeWidth.'" stroke-linecap="round" stroke-linejoin="round" class="'.$class.'" aria-hidden="true">'
                .$extras[$clave].'</svg>';
        }

        return TicketAsuntoIconos::svg($clave, $class, $strokeWidth);
    }
}
