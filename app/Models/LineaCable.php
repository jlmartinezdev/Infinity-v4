<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineaCable extends Model
{
    use Auditable;

    protected $table = 'linea_cables';

    protected $primaryKey = 'linea_cable_id';

    protected $fillable = [
        'fibra_color_id',
        'origen_tipo',
        'origen_id',
        'destino_tipo',
        'destino_id',
        'longitud_metros',
        'coordenadas',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'coordenadas' => 'array',
            'longitud_metros' => 'float',
        ];
    }

    public function fibraColor(): BelongsTo
    {
        return $this->belongsTo(FibraColor::class, 'fibra_color_id', 'fibra_color_id');
    }

    public function getOrigenModelAttribute()
    {
        return $this->resolvePolymorphic('origen');
    }

    public function getDestinoModelAttribute()
    {
        return $this->resolvePolymorphic('destino');
    }

    protected function resolvePolymorphic(string $prefix)
    {
        $tipo = $this->{"{$prefix}_tipo"};
        $id = $this->{"{$prefix}_id"};
        if (! $tipo || ! $id) {
            return null;
        }
        $classMap = [
            'nodo' => Nodo::class,
            'caja_nap' => CajaNap::class,
            'splitter_primario' => SplitterPrimario::class,
            'splitter_secundario' => SplitterSecundario::class,
            'salida_pon' => SalidaPon::class,
        ];
        $class = $classMap[$tipo] ?? null;
        return $class ? $class::find($id) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'linea_cable_id';
    }
}
