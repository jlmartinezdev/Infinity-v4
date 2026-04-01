<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalidaPon extends Model
{
    use Auditable;

    protected $table = 'salida_pons';

    protected $primaryKey = 'salida_pon_id';

    protected $fillable = [
        'nodo_id',
        'caja_nap_id',
        'olt_puerto_id',
        'codigo',
        'puerto',
        'lat',
        'lon',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'puerto' => 'integer',
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function cajaNap(): BelongsTo
    {
        return $this->belongsTo(CajaNap::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function oltPuerto(): BelongsTo
    {
        return $this->belongsTo(OltPuerto::class, 'olt_puerto_id', 'olt_puerto_id');
    }

    public function getRouteKeyName(): string
    {
        return 'salida_pon_id';
    }

    /**
     * Obtiene lat/lon para el mapa. Usa coordenadas propias, de la caja o del nodo.
     */
    public function getCoordenadasParaMapa(): ?array
    {
        if ($this->lat !== null && $this->lon !== null) {
            return ['lat' => (float) $this->lat, 'lon' => (float) $this->lon];
        }
        $caja = $this->cajaNap;
        if ($caja && $caja->lat !== null && $caja->lon !== null) {
            return ['lat' => (float) $caja->lat, 'lon' => (float) $caja->lon];
        }
        $nodo = $this->nodo;
        if ($nodo && $nodo->coordenas_gps) {
            $parts = array_map('trim', explode(',', $nodo->coordenas_gps));
            if (isset($parts[0], $parts[1])) {
                return ['lat' => (float) $parts[0], 'lon' => (float) $parts[1]];
            }
        }
        return null;
    }
}
