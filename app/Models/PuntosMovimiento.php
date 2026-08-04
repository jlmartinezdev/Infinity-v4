<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuntosMovimiento extends Model
{
    protected $table = 'puntos_movimientos';

    protected $fillable = [
        'cliente_id',
        'puntos',
        'saldo_despues',
        'tipo',
        'concepto',
        'referencia_tipo',
        'referencia_id',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'puntos' => 'integer',
            'saldo_despues' => 'integer',
            'meta' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'usuario_id');
    }
}
