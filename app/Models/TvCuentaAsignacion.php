<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvCuentaAsignacion extends Model
{
    protected $table = 'tv_cuenta_asignaciones';

    protected $fillable = [
        'tv_cuenta_id',
        'cliente_id',
    ];

    public function tvCuenta(): BelongsTo
    {
        return $this->belongsTo(TvCuenta::class, 'tv_cuenta_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }
}
