<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SorteoGanador extends Model
{
    protected $table = 'sorteo_ganadores';

    protected $fillable = [
        'campana_id',
        'cliente_id',
        'categoria_id',
        'created_by',
    ];

    public function campana(): BelongsTo
    {
        return $this->belongsTo(SorteoCampana::class, 'campana_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(SorteoPremioCategoria::class, 'categoria_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }
}
