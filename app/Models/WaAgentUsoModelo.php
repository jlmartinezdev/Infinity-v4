<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaAgentUsoModelo extends Model
{
    protected $table = 'wa_agent_uso_modelo';

    protected $fillable = [
        'ocurrido_at',
        'proveedor',
        'modelo',
        'tokens_in',
        'tokens_out',
        'llamadas',
        'costo_usd_estimado',
        'mensaje_id',
        'sandbox',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ocurrido_at' => 'datetime',
            'sandbox' => 'boolean',
            'meta' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'llamadas' => 'integer',
            'costo_usd_estimado' => 'float',
        ];
    }
}
