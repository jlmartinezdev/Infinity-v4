<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpeAccesoFallo extends Model
{
    protected $table = 'cpe_acceso_fallos';

    protected $fillable = [
        'servicio_id',
        'cliente_id',
        'via',
        'ip',
        'error',
        'ultimo_at',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_at' => 'datetime',
        ];
    }
}
