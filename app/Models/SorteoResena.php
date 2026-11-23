<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SorteoResena extends Model
{
    protected $table = 'sorteo_resenas';

    protected $fillable = [
        'campana_id',
        'cliente_id',
        'cobro_id',
        'telefono',
        'nombre_google',
        'enviada_at',
        'marcada_por',
        'marcada_at',
    ];

    protected function casts(): array
    {
        return [
            'enviada_at' => 'datetime',
            'marcada_at' => 'datetime',
        ];
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(SorteoCampana::class, 'campana_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }
}
