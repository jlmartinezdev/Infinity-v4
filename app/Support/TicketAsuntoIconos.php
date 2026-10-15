<?php

namespace App\Support;

/**
 * Biblioteca de íconos SVG lineales (viewBox 24x24, trazo currentColor)
 * para clasificar visualmente los asuntos de tickets.
 */
class TicketAsuntoIconos
{
    public const DEFAULT = 'ticket';

    /**
     * @return array<string, array{label: string, grupo: string, svg: string}>
     */
    public static function catalogo(): array
    {
        return [
            // Red & Internet
            'internet' => ['label' => 'Internet', 'grupo' => 'Red & Internet', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>'],
            'sin-internet' => ['label' => 'Sin internet', 'grupo' => 'Red & Internet', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/><path d="M4 4l16 16"/>'],
            'lento' => ['label' => 'Lentitud', 'grupo' => 'Red & Internet', 'svg' => '<path d="M4 18a9 9 0 1 1 16 0"/><path d="M12 14l-4.5-3"/><circle cx="12" cy="14" r="1.5"/><path d="M6.5 7.5l1 1M12 5v1.5M17.5 7.5l-1 1"/>'],
            'intermitente' => ['label' => 'Intermitencia', 'grupo' => 'Red & Internet', 'svg' => '<path d="M2 12h4l2.5-6 3.5 12 3-8 2 2h5"/>'],
            'wifi' => ['label' => 'WiFi', 'grupo' => 'Red & Internet', 'svg' => '<path d="M2 8.5a15 15 0 0 1 20 0"/><path d="M5 12a10 10 0 0 1 14 0"/><path d="M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1"/>'],
            'wifi-off' => ['label' => 'WiFi caído', 'grupo' => 'Red & Internet', 'svg' => '<path d="M2 8.5a15 15 0 0 1 20 0"/><path d="M5 12a10 10 0 0 1 14 0"/><path d="M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1"/><path d="M3 3l18 18"/>'],
            'antena' => ['label' => 'Antena / Torre', 'grupo' => 'Red & Internet', 'svg' => '<path d="M12 10v11"/><path d="M8 21h8"/><circle cx="12" cy="8" r="2"/><path d="M8.2 4.6a5 5 0 0 0 0 6.8"/><path d="M15.8 4.6a5 5 0 0 1 0 6.8"/><path d="M5.4 2a9 9 0 0 0 0 12"/><path d="M18.6 2a9 9 0 0 1 0 12"/>'],
            'parabolica' => ['label' => 'Antena parabólica', 'grupo' => 'Red & Internet', 'svg' => '<path d="M4 9a9 9 0 0 0 11 11z"/><path d="M9.5 14.5l3.5-3.5"/><circle cx="13.5" cy="10.5" r="1"/><path d="M14 3a7 7 0 0 1 7 7"/><path d="M14 6.5a3.5 3.5 0 0 1 3.5 3.5"/><path d="M7 20h6"/>'],
            'router' => ['label' => 'Router', 'grupo' => 'Red & Internet', 'svg' => '<rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 16.5h.01M10.5 16.5h.01"/><path d="M14.5 16.5h3"/><path d="M6 13l-1-6"/><path d="M18 13l1-6"/><path d="M9.5 9.5a3.5 3.5 0 0 1 5 0"/><path d="M7.8 7a6 6 0 0 1 8.4 0"/>'],
            'onu' => ['label' => 'ONU / Módem', 'grupo' => 'Red & Internet', 'svg' => '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 12h.01M10 12h.01M13 12h.01"/><path d="M16 12h2"/><path d="M7 17v2M17 17v2"/>'],
            'troncal' => ['label' => 'Troncal / Red', 'grupo' => 'Red & Internet', 'svg' => '<circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><path d="M12 7v5"/><path d="M12 12l-5.6 5.6"/><path d="M12 12l5.6 5.6"/>'],
            'caja-nap' => ['label' => 'Caja NAP', 'grupo' => 'Red & Internet', 'svg' => '<rect x="4" y="3" width="16" height="12" rx="2"/><path d="M8 7h8M8 11h8"/><path d="M7 15v6M12 15v6M17 15v6"/>'],

            // Físico & Energía
            'fibra' => ['label' => 'Fibra óptica', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M3 21c0-6 4-8 8-8h2"/><rect x="13" y="10" width="5" height="6" rx="1"/><path d="M18 13h2"/><path d="M21 9.5l1-1M21 16.5l1 1M22 13h.01"/>'],
            'cable' => ['label' => 'Cable / Enchufe', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M9 2v5M15 2v5"/><path d="M6 7h12v4a6 6 0 0 1-12 0z"/><path d="M12 17v5"/>'],
            'desconexion' => ['label' => 'Desconexión', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M9 2v5M15 2v5"/><path d="M6 7h12v4a6 6 0 0 1-12 0z"/><path d="M12 17v5"/><path d="M3 3l18 18"/>'],
            'conector' => ['label' => 'Conector RJ45', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M6 3h12v10l-2 3H8l-2-3z"/><path d="M9 3v4M12 3v4M15 3v4"/><path d="M10 16v5h4v-5"/>'],
            'cortado' => ['label' => 'Corte / Rotura', 'grupo' => 'Físico & Energía', 'svg' => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.1 8.1L20 20"/><path d="M8.1 15.9L20 4"/>'],
            'rayo' => ['label' => 'Energía / PoE', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>'],
            'fuego' => ['label' => 'Quemado', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M12 22a7 7 0 0 0 7-7c0-4-3-6-4-10-2 2-3 4-3 6-1-1-2-2-2-4-2 2-5 5-5 8a7 7 0 0 0 7 7z"/>'],
            'fuente' => ['label' => 'Fuente / Cargador', 'grupo' => 'Físico & Energía', 'svg' => '<rect x="4" y="8" width="11" height="11" rx="2"/><path d="M7.5 8V4M11.5 8V4"/><path d="M15 13.5h2.5a2 2 0 0 1 2 2V21"/><path d="M10 10.5l-1.8 3h3.6L10 16.5"/>'],
            'herramienta' => ['label' => 'Mantenimiento', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M14.5 3.5a5 5 0 0 0-5.3 6.8L3 16.5 7.5 21l6.2-6.2a5 5 0 0 0 6.8-5.3l-3 3-3-1-1-3z"/>'],
            'alerta' => ['label' => 'Advertencia', 'grupo' => 'Físico & Energía', 'svg' => '<path d="M12 3L2 20h20z"/><path d="M12 10v4"/><path d="M12 17h.01"/>'],

            // TV & Streaming
            'tv' => ['label' => 'Televisión', 'grupo' => 'TV & Streaming', 'svg' => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M8 22h8"/><path d="M8 2l4 4 4-4"/>'],
            'app' => ['label' => 'App móvil', 'grupo' => 'TV & Streaming', 'svg' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18.5h2"/><path d="M10 8l4.5 2.5L10 13z"/>'],
            'tvbox' => ['label' => 'TV Box', 'grupo' => 'TV & Streaming', 'svg' => '<rect x="3" y="10" width="18" height="8" rx="2"/><path d="M7 14h.01"/><path d="M13 14h5"/><path d="M12 10L9 5M12 10l3-5"/>'],
            'candado' => ['label' => 'Contraseña / Acceso', 'grupo' => 'TV & Streaming', 'svg' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><path d="M12 15v2"/>'],
            'llave' => ['label' => 'Clave', 'grupo' => 'TV & Streaming', 'svg' => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3"/><path d="M17 6l3 3"/><path d="M14.5 8.5l2 2"/>'],

            // Gestión & Comercial
            'dinero' => ['label' => 'Cobranza / Mora', 'grupo' => 'Gestión & Comercial', 'svg' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/>'],
            'plan' => ['label' => 'Plan / Velocidad', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M3 21h18"/><rect x="5" y="13" width="3" height="8" rx="0.5"/><rect x="10.5" y="8" width="3" height="13" rx="0.5"/><rect x="16" y="3" width="3" height="18" rx="0.5"/>'],
            'power' => ['label' => 'Reconexión', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M12 3v9"/><path d="M6.3 7.3a8 8 0 1 0 11.4 0"/>'],
            'cancelar' => ['label' => 'Cancelación', 'grupo' => 'Gestión & Comercial', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>'],
            'casa' => ['label' => 'Domicilio', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>'],
            'ubicacion' => ['label' => 'Cambio de lugar', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M12 21s-7-6-7-12a7 7 0 0 1 14 0c0 6-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>'],
            'intercambio' => ['label' => 'Cambio de equipo', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M7 4L3 8l4 4"/><path d="M3 8h14"/><path d="M17 12l4 4-4 4"/><path d="M21 16H7"/>'],
            'refrescar' => ['label' => 'Reseteo / Actualización', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M20 11a8 8 0 0 0-14.9-3"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.9 3"/><path d="M20 20v-4h-4"/>'],
            'paquete' => ['label' => 'Recolección', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/>'],
            'documento' => ['label' => 'Datos / Titular', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>'],
            'chat' => ['label' => 'WhatsApp / Chat', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M21 12a8 8 0 0 1-11.8 7L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9 12h.01M12 12h.01M15 12h.01"/>'],
            'ticket' => ['label' => 'Genérico', 'grupo' => 'Gestión & Comercial', 'svg' => '<path d="M4 6h16a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4V7a1 1 0 0 1 1-1z"/><path d="M14 6v2M14 11v2M14 16v2"/>'],
        ];
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::catalogo());
    }

    public static function existe(?string $clave): bool
    {
        return $clave !== null && array_key_exists($clave, self::catalogo());
    }

    /**
     * SVG completo listo para insertar (markup de confianza, generado del catálogo propio).
     */
    public static function svg(?string $clave, string $class = 'w-5 h-5', float $strokeWidth = 1.75): string
    {
        $catalogo = self::catalogo();
        $item = $catalogo[$clave ?? ''] ?? $catalogo[self::DEFAULT];
        $class = e($class);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            .'stroke-width="'.$strokeWidth.'" stroke-linecap="round" stroke-linejoin="round" class="'.$class.'" aria-hidden="true">'
            .$item['svg'].'</svg>';
    }

    /**
     * @return array<string, array<string, array{label: string, grupo: string, svg: string}>>
     */
    public static function porGrupo(): array
    {
        $grupos = [];
        foreach (self::catalogo() as $clave => $item) {
            $grupos[$item['grupo']][$clave] = $item;
        }

        return $grupos;
    }
}
