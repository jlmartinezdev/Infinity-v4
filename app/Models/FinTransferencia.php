<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinTransferencia extends Model
{
    use Auditable;

    protected $table = 'fin_transferencias';

    protected $fillable = [
        'cuenta_origen_id', 'cuenta_destino_id', 'importe', 'fecha',
        'numero_referencia', 'usuario_id', 'observaciones', 'estado', 'liquidacion_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    public function origen(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_destino_id');
    }
}
