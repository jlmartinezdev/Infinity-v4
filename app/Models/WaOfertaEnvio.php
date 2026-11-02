<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaOfertaEnvio extends Model
{
    protected $table = 'wa_oferta_envios';

    protected $fillable = [
        'telefono',
        'referencia',
    ];
}
