<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvCuentaAsignacion extends Model
{
    protected $table = 'tv_cuenta_asignaciones';

    protected $fillable = [
        'tv_cuenta_id',
        'servicio_id',
        'perfil_numero',
        'fecha_activacion',
        'es_promo',
        'precio_aplicado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_activacion' => 'date',
            'es_promo' => 'boolean',
            'precio_aplicado' => 'decimal:2',
        ];
    }

    public function tvCuenta(): BelongsTo
    {
        return $this->belongsTo(TvCuenta::class, 'tv_cuenta_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Servicio::class, 'servicio_id', 'servicio_id');
    }
}
