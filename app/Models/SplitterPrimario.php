<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SplitterPrimario extends Model
{
    use Auditable;

    protected $table = 'splitter_primarios';

    protected $primaryKey = 'splitter_primario_id';

    protected $fillable = [
        'caja_nap_id',
        'codigo',
        'ratio',
        'puerto_entrada',
        'potencia_entrada',
        'potencia_salida',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'puerto_entrada' => 'integer',
            'potencia_entrada' => 'float',
            'potencia_salida' => 'float',
        ];
    }

    public function cajaNap(): BelongsTo
    {
        return $this->belongsTo(CajaNap::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function splitterSecundarios(): HasMany
    {
        return $this->hasMany(SplitterSecundario::class, 'splitter_primario_id', 'splitter_primario_id');
    }

    public function getRouteKeyName(): string
    {
        return 'splitter_primario_id';
    }
}
