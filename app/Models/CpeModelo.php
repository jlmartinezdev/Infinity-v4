<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpeModelo extends Model
{
    protected $table = 'cpe_modelos';

    protected $fillable = [
        'tipo',
        'clave',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public const TIPO_ONU = 'onu';

    public const TIPO_ROUTER = 'router';

    public const TIPO_ANTENA = 'antena';

    public static function tipos(): array
    {
        return [
            self::TIPO_ONU => 'ONU',
            self::TIPO_ROUTER => 'Router WiFi',
            self::TIPO_ANTENA => 'Antena',
        ];
    }
}
