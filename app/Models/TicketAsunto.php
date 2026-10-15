<?php

namespace App\Models;

use App\Support\TicketAsuntoIconos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketAsunto extends Model
{
    protected $table = 'ticket_asuntos';

    protected $fillable = ['nombre', 'icono', 'icono_svg', 'color'];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_asunto_id', 'id');
    }

    /** Emoji (se usa en <select>, donde no se puede renderizar SVG). */
    public function getIconoOrDefault(): string
    {
        $icono = trim((string) ($this->icono ?? ''));
        return $icono !== '' ? $icono : '🎫';
    }

    /** Color HEX saneado (#RRGGBB) apto para estilos inline. */
    public function getColorOrDefault(): string
    {
        $hex = ltrim(trim((string) ($this->color ?? '')), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#'.strtolower($hex) : '#8b5cf6';
    }

    public function getIconoSvgKey(): string
    {
        return TicketAsuntoIconos::existe($this->icono_svg) ? $this->icono_svg : TicketAsuntoIconos::DEFAULT;
    }

    /** Markup SVG del ícono del asunto. */
    public function iconoSvg(string $class = 'w-5 h-5', float $strokeWidth = 1.75): string
    {
        return TicketAsuntoIconos::svg($this->getIconoSvgKey(), $class, $strokeWidth);
    }

    /** Estilo inline para badges con tintes acordes al color seleccionado. */
    public function badgeStyle(): string
    {
        $c = $this->getColorOrDefault();

        return "background-color: {$c}18; color: {$c}; border: 1px solid {$c}40;";
    }

    /** Estilo inline para la "baldosa" que contiene el ícono SVG. */
    public function tileStyle(): string
    {
        $c = $this->getColorOrDefault();

        return "background-color: {$c}1f; color: {$c}; border: 1px solid {$c}4d;";
    }
}
