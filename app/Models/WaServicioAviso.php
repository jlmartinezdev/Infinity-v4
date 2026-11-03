<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaServicioAviso extends Model
{
    protected $table = 'wa_servicio_avisos';

    protected $fillable = [
        'servicio_id',
        'cliente_id',
        'telefono',
        'nombre_pila',
        'apellido',
        'sandbox',
        'notificado_at',
    ];

    protected function casts(): array
    {
        return [
            'sandbox' => 'boolean',
            'notificado_at' => 'datetime',
        ];
    }
}
