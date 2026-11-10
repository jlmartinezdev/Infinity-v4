<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinControlGastoMes extends Model
{
    use Auditable;

    protected $table = 'fin_control_gasto_meses';

    protected $fillable = [
        'anio', 'mes', 'estado', 'cuenta_id', 'importe', 'fin_egreso_id',
        'abierto_por', 'abierto_en', 'cerrado_por', 'cerrado_en',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'abierto_en' => 'datetime',
        'cerrado_en' => 'datetime',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_id');
    }

    public function egreso(): BelongsTo
    {
        return $this->belongsTo(FinEgreso::class, 'fin_egreso_id');
    }
}
