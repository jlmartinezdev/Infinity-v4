<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nodo extends Model
{
    use Auditable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nodos';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'nodo_id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'coordenas_gps',
        'ciudad',
        'tecnologia_gpon',
        'tecnologia_wireless',
    ];

    protected function casts(): array
    {
        return [
            'tecnologia_gpon' => 'boolean',
            'tecnologia_wireless' => 'boolean',
        ];
    }

    public function manejaGpon(): bool
    {
        return (bool) $this->tecnologia_gpon;
    }

    public function manejaWireless(): bool
    {
        return (bool) $this->tecnologia_wireless;
    }

    /** Etiqueta legible: GPON, Wireless o ambos. */
    public function tecnologiasEtiqueta(): string
    {
        $partes = [];
        if ($this->manejaGpon()) {
            $partes[] = 'GPON';
        }
        if ($this->manejaWireless()) {
            $partes[] = 'Wireless';
        }

        return $partes !== [] ? implode(' + ', $partes) : '—';
    }

    public function toArraySelect(): array
    {
        return [
            'nodo_id' => $this->nodo_id,
            'descripcion' => $this->descripcion,
            'tecnologia_gpon' => $this->manejaGpon(),
            'tecnologia_wireless' => $this->manejaWireless(),
            'tecnologias_etiqueta' => $this->tecnologiasEtiqueta(),
        ];
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function cajaNaps(): HasMany
    {
        return $this->hasMany(CajaNap::class, 'nodo_id', 'nodo_id');
    }

    public function olts(): HasMany
    {
        return $this->hasMany(Olt::class, 'nodo_id', 'nodo_id');
    }

    public function getCoordenadasParaMapa(): ?array
    {
        if (! $this->coordenas_gps) {
            return null;
        }
        $parts = array_map('trim', explode(',', $this->coordenas_gps));
        if (isset($parts[0], $parts[1])) {
            return ['lat' => (float) $parts[0], 'lon' => (float) $parts[1]];
        }
        return null;
    }

    public function getRouteKeyName(): string
    {
        return 'nodo_id';
    }

    /**
     * Retrieve the model for route model binding.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        return $this->where($field, $value)->first();
    }
}
