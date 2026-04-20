<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaNapPuertoActivo extends Model
{
    protected $table = 'caja_nap_puerto_activos';

    protected $fillable = [
        'caja_nap_id',
        'numero_puerto',
        'servicio_id',
        'potencia_cliente',
    ];

    protected function casts(): array
    {
        return [
            'potencia_cliente' => 'decimal:3',
        ];
    }

    public function cajaNap(): BelongsTo
    {
        return $this->belongsTo(CajaNap::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function estaLibre(): bool
    {
        return $this->servicio_id === null;
    }
}
