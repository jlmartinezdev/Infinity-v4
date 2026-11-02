<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaIncidenteZona extends Model
{
    protected $table = 'wa_incidentes_zona';

    protected $fillable = [
        'router_id',
        'host',
        'origen',
        'estado',
        'resumen',
        'abierto_at',
        'cerrado_at',
    ];

    protected function casts(): array
    {
        return [
            'abierto_at' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }
}
