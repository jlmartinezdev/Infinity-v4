<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaCaso extends Model
{
    protected $table = 'wa_casos';

    protected $fillable = [
        'titulo',
        'etiqueta',
        'palabras',
        'texto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
