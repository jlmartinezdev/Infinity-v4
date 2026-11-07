<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinLiquidacion extends Model
{
    use Auditable;

    protected $table = 'fin_liquidaciones';

    protected $fillable = [
        'fecha_liquidacion', 'referencia', 'bruto', 'comision', 'neto',
        'usuario_id', 'observaciones', 'estado',
    ];

    protected $casts = [
        'fecha_liquidacion' => 'date',
        'bruto' => 'decimal:2',
        'comision' => 'decimal:2',
        'neto' => 'decimal:2',
    ];

    public function cobros(): HasMany
    {
        return $this->hasMany(FinLiquidacionCobro::class, 'liquidacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
