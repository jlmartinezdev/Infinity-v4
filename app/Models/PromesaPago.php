<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromesaPago extends Model
{
    protected $table = 'promesa_pagos';

    protected $fillable = [
        'factura_interna_id',
        'cliente_id',
        'vencimiento_at',
        'observaciones',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'vencimiento_at' => 'datetime',
        ];
    }

    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class, 'factura_interna_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function estaVigente(): bool
    {
        return $this->vencimiento_at->isFuture();
    }
}
