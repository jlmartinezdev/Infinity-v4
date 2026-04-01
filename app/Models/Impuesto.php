<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Impuesto extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'porcentaje',
        'activo',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function facturaDetalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class, 'impuesto_id');
    }

    public static function activos(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('activo', true)->orderBy('porcentaje', 'desc')->get();
    }
}
