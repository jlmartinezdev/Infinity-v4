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

    public const TIPO_AUTOMATICO = 'automatico';

    public const TIPO_APROBACION = 'requiere_aprobacion';

    public const TIPO_SORTEO = 'sorteo';

    public const ETIQUETAS = ['nuevo', 'novedad', 'sale'];

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
        'etiqueta',
        'tier',
        'requiere_aprobacion',
        'tope_anual_por_cliente',
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
            'tier' => 'integer',
            'requiere_aprobacion' => 'boolean',
            'tope_anual_por_cliente' => 'integer',
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
            self::TIPO_AUTOMATICO => 'Automático (boost, soporte…)',
            self::TIPO_APROBACION => 'Requiere aprobación staff',
            self::TIPO_SORTEO => 'Entrada a sorteo',
        ];
    }

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return [
            'nuevo' => 'Nuevo',
            'novedad' => 'Novedad',
            'sale' => 'Sale',
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

    public function esAutomatico(): bool
    {
        return $this->tipo === self::TIPO_AUTOMATICO;
    }

    public function esSorteo(): bool
    {
        return $this->tipo === self::TIPO_SORTEO;
    }

    public function necesitaAprobacion(): bool
    {
        return (bool) $this->requiere_aprobacion
            || $this->tipo === self::TIPO_APROBACION;
    }

    public function tieneStockIlimitado(): bool
    {
        return $this->stock === null;
    }

    public function tieneStockDisponible(): bool
    {
        return $this->tieneStockIlimitado() || (int) $this->stock > 0;
    }

    /**
     * Modalidad de canje derivada del tipo (el cliente no elige).
     */
    public function modalidadCanje(): string
    {
        return match ($this->tipo) {
            self::TIPO_DESCUENTO => Canje::MOD_DESCUENTO,
            self::TIPO_AUTOMATICO => Canje::MOD_AUTOMATICO,
            self::TIPO_APROBACION => Canje::MOD_APROBACION,
            self::TIPO_SORTEO => Canje::MOD_SORTEO,
            default => Canje::MOD_RETIRO,
        };
    }

    public function canjes(): HasMany
    {
        return $this->hasMany(Canje::class, 'premio_id');
    }

    public function scopeDisponiblesPortal(Builder $query): Builder
    {
        return $query
            ->where('activo', true)
            ->where(function (Builder $q) {
                $q->whereNull('stock')->orWhere('stock', '>', 0);
            });
    }

    /** @deprecated usar scopeDisponiblesPortal */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $this->scopeDisponiblesPortal($query);
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
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'activo' => (bool) $this->activo,
            'orden' => (int) $this->orden,
            'destacado' => (bool) $this->destacado,
            'etiqueta' => $this->etiqueta,
            'tier' => $this->tier !== null ? (int) $this->tier : null,
            'requiere_aprobacion' => $this->necesitaAprobacion(),
            'tope_anual_por_cliente' => $this->tope_anual_por_cliente !== null
                ? (int) $this->tope_anual_por_cliente
                : null,
        ];
    }
}
