<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class RouterModelo extends Model
{
    protected $table = 'router_modelos';

    protected $primaryKey = 'router_modelo_id';

    protected $fillable = [
        'slug',
        'nombre',
        'serie',
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

    public function routers(): HasMany
    {
        return $this->hasMany(Router::class, 'modelo', 'slug');
    }

    public function imagenEsSubida(): bool
    {
        return filled($this->imagen) && str_starts_with($this->imagen, 'router-modelos/');
    }

    public function imagenUrl(): string
    {
        if (! filled($this->imagen)) {
            return asset('images/routers/mikrotik-generic.svg');
        }

        if ($this->imagenEsSubida() && Storage::disk('public')->exists($this->imagen)) {
            return Storage::disk('public')->url($this->imagen);
        }

        if ($this->imagenEsSubida()) {
            return asset('images/routers/mikrotik-generic.svg');
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
     * @return array{nombre: string, serie: string, imagen: string, descripcion: string, slug: string, imagen_url: string}
     */
    public function toCatalogoArray(): array
    {
        return [
            'slug' => $this->slug,
            'nombre' => $this->nombre,
            'serie' => $this->serie,
            'imagen' => $this->imagen ?? '',
            'imagen_url' => $this->imagenUrl(),
            'descripcion' => $this->descripcion ?? '',
        ];
    }
}
