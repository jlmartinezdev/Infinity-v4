<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaNap extends Model
{
    use Auditable;

    protected $table = 'caja_naps';

    protected $primaryKey = 'caja_nap_id';

    protected $fillable = [
        'nodo_id',
        'codigo',
        'descripcion',
        'lat',
        'lon',
        'direccion',
        'tipo',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function splitterPrimarios(): HasMany
    {
        return $this->hasMany(SplitterPrimario::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function splitterSecundarios(): HasMany
    {
        return $this->hasMany(SplitterSecundario::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function salidaPons(): HasMany
    {
        return $this->hasMany(SalidaPon::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function getRouteKeyName(): string
    {
        return 'caja_nap_id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        return $this->where($field, $value)->first();
    }
}
