<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Support\LoyaltyImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use Auditable;

    public const MONEDA_PYG = 'PYG';

    public const MONEDA_USD = 'USD';

    public const MONEDAS = [
        self::MONEDA_PYG,
        self::MONEDA_USD,
    ];

    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'proveedor_id',
        'nombre',
        'codigo',
        'unidad',
        'stock_actual',
        'stock_minimo',
        'precio_compra',
        'precio_venta',
        'moneda',
        'descripcion',
        'imagen',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'producto_id');
    }

    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'producto_id');
    }

    public function inventarioMovimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'producto_id');
    }

    public function stockBajo(): bool
    {
        return $this->stock_actual <= $this->stock_minimo;
    }

    /** Stock, precios, etc. sin ceros decimales de más (1.500,50 → 1.500,5; 10,00 → 10). */
    public static function formatoNumero(mixed $valor): string
    {
        $n = is_numeric($valor) ? (float) $valor : 0.0;

        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }

    /** Valor para inputs type=number (punto decimal, sin ceros a la derecha). */
    public static function valorInput(mixed $valor): string
    {
        $n = is_numeric($valor) ? (float) $valor : 0.0;

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    public function esDolar(): bool
    {
        return $this->moneda === self::MONEDA_USD;
    }

    public function etiquetaMoneda(): string
    {
        return self::etiquetaMonedaDe($this->moneda);
    }

    public static function etiquetaMonedaDe(?string $moneda): string
    {
        return $moneda === self::MONEDA_USD ? 'USD' : 'Gs';
    }

    public static function formatoPrecio(mixed $valor, ?string $moneda = self::MONEDA_PYG): string
    {
        return self::formatoNumero($valor).' '.self::etiquetaMonedaDe($moneda);
    }

    public function imagenUrl(): ?string
    {
        if (! filled($this->imagen)) {
            return null;
        }

        return asset('storage/'.$this->imagen);
    }

    public function eliminarImagen(): void
    {
        LoyaltyImageUploader::borrar($this->imagen);
        $this->imagen = null;
    }
}
