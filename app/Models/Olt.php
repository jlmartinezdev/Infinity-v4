<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    use Auditable;

    protected $table = 'olts';

    protected $primaryKey = 'olt_id';

    protected $fillable = [
        'nodo_id',
        'olt_marca_id',
        'modelo',
        'ip',
        'cantidad_puertos',
        'tipo_pon',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_puertos' => 'integer',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function oltMarca(): BelongsTo
    {
        return $this->belongsTo(OltMarca::class, 'olt_marca_id', 'olt_marca_id');
    }

    public function oltPuertos(): HasMany
    {
        return $this->hasMany(OltPuerto::class, 'olt_id', 'olt_id');
    }

    public function getRouteKeyName(): string
    {
        return 'olt_id';
    }
}
