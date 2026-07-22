<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class OltModelo extends Model
{
    protected $table = 'olt_modelos';

    protected $primaryKey = 'olt_modelo_id';

    protected $fillable = [
        'slug',
        'nombre',
        'marca',
        'descripcion',
        'imagen',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function olts(): HasMany
    {
        return $this->hasMany(Olt::class, 'modelo', 'slug');
    }

    public function imagenEsSubida(): bool
    {
        return filled($this->imagen) && str_starts_with($this->imagen, 'olt-modelos/');
    }

    public function imagenUrl(): string
    {
        if (! filled($this->imagen)) {
            return asset('images/olts/olt-generic.svg');
        }

        if ($this->imagenEsSubida() && Storage::disk('public')->exists($this->imagen)) {
            return Storage::disk('public')->url($this->imagen);
        }

        if ($this->imagenEsSubida()) {
            return asset('images/olts/olt-generic.svg');
        }

        return asset($this->imagen);
    }

    public function eliminarImagenSubida(): void
    {
        if ($this->imagenEsSubida() && Storage::disk('public')->exists($this->imagen)) {
            Storage::disk('public')->delete($this->imagen);
        }
    }

    /**
     * @return array{nombre: string, marca: string, imagen: string, descripcion: string, slug: string, imagen_url: string}
     */
    public function toCatalogoArray(): array
    {
        return [
            'slug' => $this->slug,
            'nombre' => $this->nombre,
            'marca' => $this->marca,
            'imagen' => $this->imagen ?? '',
            'imagen_url' => $this->imagenUrl(),
            'descripcion' => $this->descripcion ?? '',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'olt_modelo_id';
    }
}
