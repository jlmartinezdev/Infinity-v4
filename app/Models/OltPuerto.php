<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltPuerto extends Model
{
    use Auditable;

    protected $table = 'olt_puertos';

    protected $primaryKey = 'olt_puerto_id';

    protected $fillable = [
        'olt_id',
        'numero',
        'tipo_pon',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class, 'olt_id', 'olt_id');
    }

    public function salidaPons(): HasMany
    {
        return $this->hasMany(SalidaPon::class, 'olt_puerto_id', 'olt_puerto_id');
    }

    public function getRouteKeyName(): string
    {
        return 'olt_puerto_id';
    }
}
