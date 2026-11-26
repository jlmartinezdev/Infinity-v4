<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaFusion extends Model
{
    use Auditable;

    protected $table = 'cajas_fusion';

    protected $primaryKey = 'caja_fusion_id';

    protected $fillable = [
        'codigo',
        'ubicacion',
        'lat',
        'lon',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'caja_fusion_id';
    }

    /** Siguiente código libre con formato CF-001, CF-002, … */
    public static function siguienteCodigo(): string
    {
        $max = 0;
        foreach (static::query()->pluck('codigo') as $codigo) {
            if (preg_match('/^CF-(\d+)$/i', trim((string) $codigo), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('CF-%03d', $max + 1);
    }

    public function cablesComoOrigen(): HasMany
    {
        return $this->hasMany(LineaCable::class, 'origen_id', 'caja_fusion_id')
            ->where('origen_tipo', 'caja_fusion');
    }

    public function cablesComoDestino(): HasMany
    {
        return $this->hasMany(LineaCable::class, 'destino_id', 'caja_fusion_id')
            ->where('destino_tipo', 'caja_fusion');
    }
}
