<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use Auditable;

    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'codigo',
        'unidad',
        'stock_actual',
        'stock_minimo',
        'precio_compra',
        'precio_venta',
        'descripcion',
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
}
