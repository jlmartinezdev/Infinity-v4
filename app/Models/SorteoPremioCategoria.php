<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SorteoPremioCategoria extends Model
{
    protected $table = 'sorteo_premio_categorias';

    protected $fillable = [
        'campana_id',
        'codigo',
        'nombre',
        'cupo',
        'porcentaje',
        'ventanas_emision',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'cupo' => 'integer',
            'porcentaje' => 'decimal:2',
            'ventanas_emision' => 'array',
        ];
    }

    public function campana(): BelongsTo
    {
        return $this->belongsTo(SorteoCampana::class, 'campana_id');
    }

    public function tieneDescuento(): bool
    {
        return $this->porcentaje !== null && (float) $this->porcentaje > 0;
    }
}
