<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaMemoria extends Model
{
    protected $table = 'wa_memorias';

    public const TIPOS = [
        'instruccion' => 'Instrucción / playbook',
        'tv' => 'TV / streaming',
        'producto' => 'Producto / planes',
        'politica' => 'Política / condiciones',
        'banco' => 'Notas de pago (no reemplaza canned)',
    ];

    protected $fillable = [
        'titulo',
        'tipo',
        'palabras',
        'cuerpo',
        'activo',
        'version',
        'consumido_at',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'version' => 'integer',
            'consumido_at' => 'datetime',
        ];
    }
}
