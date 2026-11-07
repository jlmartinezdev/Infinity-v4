<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinRetiro extends Model
{
    use Auditable;

    protected $table = 'fin_retiros';

    protected $fillable = [
        'persona', 'fecha', 'importe', 'cuenta_id', 'motivo',
        'usuario_id', 'autorizacion', 'comprobante', 'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_id');
    }
}
