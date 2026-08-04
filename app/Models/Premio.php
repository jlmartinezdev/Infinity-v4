<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Premio extends Model
{
    protected $table = 'premios';

    public const TIPO_FISICO = 'fisico';

    public const TIPO_PRODUCTO = 'producto';

    public const TIPO_RETIRO = 'retiro';

    public const TIPO_DESCUENTO = 'descuento_factura';

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
        'imagen',
        'puntos_requeridos',
        'descuento_porcentaje',
        'descuento_monto',
        'stock',
        'activo',
        'orden',
        'destacado',
    ];

    protected function casts(): array
    {
        return [
            'puntos_requeridos' => 'integer',
            'descuento_porcentaje' => 'decimal:2',
            'descuento_monto' => 'decimal:2',
            'stock' => 'integer',
            'activo' => 'boolean',
            'orden' => 'integer',
            'destacado' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    public static function tipos(): array
    {
        return [
            self::TIPO_FISICO => 'Físico (retiro oficina)',
            self::TIPO_PRODUCTO => 'Producto (retiro oficina)',
            self::TIPO_RETIRO => 'Retiro en oficina',
            self::TIPO_DESCUENTO => 'Descuento en factura',
        ];
    }

    public function esDescuentoFactura(): bool
    {
        return $this->tipo === self::TIPO_DESCUENTO;
    }

    public function esRetiroOficina(): bool
    {
        return in_array($this->tipo, [
            self::TIPO_FISICO,
            self::TIPO_PRODUCTO,
            self::TIPO_RETIRO,
        ], true);
    }

    /**
     * Modalidad de canje derivada del tipo (el cliente no elige).
     */
    public function modalidadCanje(): string
    {
        return $this->esDescuentoFactura()
            ? Canje::MOD_DESCUENTO
            : Canje::MOD_RETIRO;
    }

    public function canjes(): HasMany
    {
        return $this->hasMany(Canje::class, 'premio_id');
    }

    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('activo', true)->where('stock', '>', 0);
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
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo ?: self::TIPO_FISICO,
            'imagen_url' => $this->imagenUrl(),
            'puntos_requeridos' => (int) $this->puntos_requeridos,
            'descuento_porcentaje' => $this->descuento_porcentaje !== null
                ? (float) $this->descuento_porcentaje
                : null,
            'descuento_monto' => $this->descuento_monto !== null
                ? (float) $this->descuento_monto
                : null,
            'stock' => (int) $this->stock,
            'activo' => (bool) $this->activo,
            'orden' => (int) $this->orden,
            'destacado' => (bool) $this->destacado,
        ];
    }
}
