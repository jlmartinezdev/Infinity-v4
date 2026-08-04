<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Novedad extends Model
{
    protected $table = 'novedades';

    public const TIPOS = ['promo', 'aviso', 'upsell', 'referi'];

    protected $fillable = [
        'titulo',
        'subtitulo',
        'imagen',
        'accion_url',
        'tipo',
        'orden',
        'activa',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'orden' => 'integer',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function scopePublicadas(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query
            ->where('activa', true)
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('vigente_desde')->orWhereDate('vigente_desde', '<=', $hoy);
            })
            ->where(function (Builder $q) use ($hoy) {
                $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy);
            });
    }

    public function imagenUrl(): ?string
    {
        if (! filled($this->imagen)) {
            return null;
        }

        if (Storage::disk('public')->exists($this->imagen)) {
            return url(Storage::disk('public')->url($this->imagen));
        }

        return null;
    }

    public function eliminarImagen(): void
    {
        if (filled($this->imagen) && Storage::disk('public')->exists($this->imagen)) {
            Storage::disk('public')->delete($this->imagen);
        }
    }

    public function toPortalArray(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'imagen_url' => $this->imagenUrl(),
            'accion_url' => $this->accion_url,
            'tipo' => $this->tipo,
            'orden' => (int) $this->orden,
            'activa' => (bool) $this->activa,
        ];
    }
}
