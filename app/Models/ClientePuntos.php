<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientePuntos extends Model
{
    protected $table = 'cliente_puntos';

    protected $fillable = [
        'cliente_id',
        'saldo',
    ];

    protected function casts(): array
    {
        return [
            'saldo' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }
}
