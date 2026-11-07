<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinCierre extends Model
{
    use Auditable;

    protected $table = 'fin_cierres';

    protected $fillable = [
        'cuenta_id', 'tipo', 'periodo_desde', 'periodo_hasta',
        'saldo_inicial', 'ingresos', 'egresos', 'transferencias_entrada', 'transferencias_salida',
        'retiros', 'ajustes', 'saldo_calculado', 'saldo_real', 'diferencia', 'usuario_id',
    ];

    protected $casts = [
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_id');
    }
}
