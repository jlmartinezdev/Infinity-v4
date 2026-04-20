<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalidaPon extends Model
{
    use Auditable;

    /** Tipos de módulo PON habituales (GPON/XGS-PON). */
    public const TIPOS_MODULO = ['B+', 'C+', 'C++', 'C+++', 'C++++'];

    /** Si el OLT no tiene cantidad de puertos declarada, se usa este máximo en el selector. */
    public const PUERTOS_MAX_SIN_DECLARAR_EN_OLT = 16;

    protected $table = 'salida_pons';

    protected $primaryKey = 'salida_pon_id';

    protected $fillable = [
        'olt_id',
        'nodo_id',
        'tipo_modulo',
        'potencia_salida',
        'codigo',
        'puerto_olt',
        'estado',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'puerto_olt' => 'integer',
            'potencia_salida' => 'decimal:3',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class, 'olt_id', 'olt_id');
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function cajaNaps(): HasMany
    {
        return $this->hasMany(CajaNap::class, 'salida_pon_id', 'salida_pon_id');
    }

    public function getRouteKeyName(): string
    {
        return 'salida_pon_id';
    }

    /**
     * Coordenadas para mapa: primera caja NAP enlazada con lat/lon, o nodo (vía OLT o nodo explícito).
     */
    public function getCoordenadasParaMapa(): ?array
    {
        $this->loadMissing('cajaNaps');
        foreach ($this->cajaNaps as $caja) {
            if ($caja->lat !== null && $caja->lon !== null) {
                return ['lat' => (float) $caja->lat, 'lon' => (float) $caja->lon];
            }
        }
        $nodo = $this->relationLoaded('nodo') ? $this->nodo : $this->nodo()->first();
        if (! $nodo && $this->olt_id) {
            $this->loadMissing('olt.nodo');
            $nodo = $this->olt?->nodo;
        }
        if ($nodo && $nodo->coordenas_gps) {
            $parts = array_map('trim', explode(',', $nodo->coordenas_gps));
            if (isset($parts[0], $parts[1])) {
                return ['lat' => (float) $parts[0], 'lon' => (float) $parts[1]];
            }
        }

        return null;
    }
}
