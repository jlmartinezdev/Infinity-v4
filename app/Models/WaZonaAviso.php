<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaZonaAviso extends Model
{
    protected $table = 'wa_zona_avisos';

    protected $fillable = [
        'incidente_id',
        'router_id',
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

    public function incidente(): BelongsTo
    {
        return $this->belongsTo(WaIncidenteZona::class, 'incidente_id');
    }
}
