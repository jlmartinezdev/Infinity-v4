<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoAjuste extends Model
{
    public const BIENVENIDA = 'bienvenida_app';

    protected $table = 'promo_ajustes';

    protected $fillable = [
        'clave',
        'activo',
        'porcentaje',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'porcentaje' => 'decimal:2',
        ];
    }

    public static function bienvenida(): ?self
    {
        return static::query()->where('clave', self::BIENVENIDA)->first();
    }
}
