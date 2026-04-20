<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Caja NAP en campo FTTH: {@see $splitter_segundo_nivel} (1x8 / 1x16) y {@see CajaNapPuertoActivo}.
 */
class CajaNap extends Model
{
    use Auditable;

    protected $table = 'caja_naps';

    protected $primaryKey = 'caja_nap_id';

    protected $fillable = [
        'nodo_id',
        'salida_pon_id',
        'codigo',
        'descripcion',
        'lat',
        'lon',
        'direccion',
        'tipo',
        'splitter_primer_nivel',
        'splitter_segundo_nivel',
        'potencia_salida',
        'estado',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'splitter_segundo_nivel' => 'integer',
            'potencia_salida' => 'decimal:3',
        ];
    }

    /** Capacidad de puertos cliente: 8 (1x8) o 16 (1x16), o null si no aplica FTTH en caja. */
    public function capacidadFTTH(): ?int
    {
        $v = $this->splitter_segundo_nivel;

        return in_array((int) $v, [8, 16], true) ? (int) $v : null;
    }

    /**
     * Crea o ajusta filas de puertos según {@see $splitter_segundo_nivel}.
     * Si el splitter queda sin definir, elimina solo filas sin servicio asignado.
     */
    public function sincronizarPuertosActivos(): void
    {
        $n = $this->capacidadFTTH();
        if ($n === null) {
            $this->puertosActivos()->delete();

            return;
        }

        for ($i = 1; $i <= $n; $i++) {
            CajaNapPuertoActivo::firstOrCreate(
                [
                    'caja_nap_id' => $this->caja_nap_id,
                    'numero_puerto' => $i,
                ],
                []
            );
        }

        $this->puertosActivos()
            ->where('numero_puerto', '>', $n)
            ->whereNull('servicio_id')
            ->delete();
    }

    public function puertosActivos(): HasMany
    {
        return $this->hasMany(CajaNapPuertoActivo::class, 'caja_nap_id', 'caja_nap_id')
            ->orderBy('numero_puerto');
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function salidaPon(): BelongsTo
    {
        return $this->belongsTo(SalidaPon::class, 'salida_pon_id', 'salida_pon_id');
    }

    public function splitterPrimarios(): HasMany
    {
        return $this->hasMany(SplitterPrimario::class, 'caja_nap_id', 'caja_nap_id');
    }

    public function splitterSecundarios(): HasMany
    {
        return $this->hasMany(SplitterSecundario::class, 'caja_nap_id', 'caja_nap_id');
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
