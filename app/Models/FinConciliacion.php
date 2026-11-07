<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinConciliacion extends Model
{
    use Auditable;

    protected $table = 'fin_conciliaciones';

    protected $fillable = [
        'cuenta_id', 'fecha', 'saldo_sistema', 'saldo_real', 'diferencia',
        'usuario_id', 'observaciones', 'ajuste_movimiento_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'saldo_sistema' => 'decimal:2',
        'saldo_real' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_id');
    }
}
