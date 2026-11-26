<?php

namespace App\Services\WhatsApp;

/**
 * Plantilla Meta para retomar un pedido de instalación que sigue pendiente.
 * Categoría utilidad: avisa el estado del pedido ya iniciado, no ofrece un plan.
 */
class PlantillaPedidoPendiente
{
    public static function nombre(): string
    {
        $nombre = trim((string) config('whatsapp.templates.pedido_pendiente', 'pedido_pendiente_retomar'));

        return $nombre !== '' ? $nombre : 'pedido_pendiente_retomar';
    }

    public static function idioma(): string
    {
        $idioma = trim((string) config('whatsapp.template_languages.pedido_pendiente', 'es'));

        return $idioma !== '' ? $idioma : 'es';
    }

    public static function cuerpo(): string
    {
        return "Hola {{1}}.\n\n"
            .'Le escribimos de Interplus porque su pedido de instalación sigue pendiente. '
            .'¿Podemos retomar la conversación para confirmar el siguiente paso?';
    }

    /**
     * La plantilla exige un solo dato: el nombre. Si el formulario lo manda vacío, se usa el del chat.
     *
     * @param  list<array{type?: string, text?: string}>  $bodyParameters
     * @return list<array{type: string, text: string}>
     */
    public static function parametros(array $bodyParameters, string $nombre): array
    {
        foreach ($bodyParameters as $parametro) {
            $texto = trim((string) ($parametro['text'] ?? ''));
            if ($texto !== '') {
                return [['type' => 'text', 'text' => mb_substr($texto, 0, 80)]];
            }
        }

        $nombre = trim((string) (preg_replace('/\s+/u', ' ', $nombre) ?? ''));
        $letras = preg_match_all('/\p{L}/u', $nombre);
        if ($letras < 2) {
            $nombre = 'cliente';
        }

        return [['type' => 'text', 'text' => mb_substr($nombre, 0, 80)]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function definicion(): array
    {
        return [
            'name' => self::nombre(),
            'language' => self::idioma(),
            'category' => 'UTILITY',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => self::cuerpo(),
                    'example' => [
                        'body_text' => [['Ana']],
                    ],
                ],
                [
                    'type' => 'BUTTONS',
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => 'Sí, retomemos'],
                        ['type' => 'QUICK_REPLY', 'text' => 'Más tarde'],
                    ],
                ],
            ],
        ];
    }
}
