<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinLiquidacionCobro extends Model
{
    public $timestamps = false;

    protected $table = 'fin_liquidacion_cobros';

    protected $fillable = ['liquidacion_id', 'cobro_id', 'importe'];

    protected $casts = ['importe' => 'decimal:2'];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(FinLiquidacion::class, 'liquidacion_id');
    }

    public function cobro(): BelongsTo
    {
        return $this->belongsTo(Cobro::class, 'cobro_id');
    }
}
