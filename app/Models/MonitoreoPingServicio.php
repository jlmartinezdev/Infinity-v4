<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoreoPingServicio extends Model
{
    protected $table = 'monitoreo_ping_servicios';

    protected $primaryKey = 'servicio_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'servicio_id',
        'cliente_id',
        'ip',
        'en_linea',
        'latencia_ms',
        'verificado_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'en_linea' => 'boolean',
            'latencia_ms' => 'integer',
            'verificado_at' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }
}
