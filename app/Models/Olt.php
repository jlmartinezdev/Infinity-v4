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
        'marca',
        'codigo',
        'modelo',
        'ip',
        'cantidad_puerto',
        'tipo_pon',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_puerto' => 'integer',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function oltPuertos(): HasMany
    {
        return $this->hasMany(OltPuerto::class, 'olt_id', 'olt_id');
    }

    public function salidaPons(): HasMany
    {
        return $this->hasMany(SalidaPon::class, 'olt_id', 'olt_id');
    }

    public function getRouteKeyName(): string
    {
        return 'olt_id';
    }
}
