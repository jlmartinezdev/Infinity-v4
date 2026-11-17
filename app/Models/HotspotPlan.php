<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotPlan extends Model
{
    use Auditable;

    protected $table = 'hotspot_planes';

    protected $primaryKey = 'hotspot_plan_id';

    protected $fillable = [
        'nombre',
        'gigas',
        'precio',
        'vigencia_horas',
        'rate_limit',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'gigas' => 'integer',
            'precio' => 'integer',
            'vigencia_horas' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(HotspotVenta::class, 'hotspot_plan_id', 'hotspot_plan_id');
    }

    public function precioTexto(): string
    {
        return number_format((int) $this->precio, 0, ',', '.').' Gs.';
    }

    public function vigenciaTexto(): string
    {
        $horas = (int) $this->vigencia_horas;
        if ($horas <= 0) {
            return 'Hasta agotar los gigas';
        }
        if ($horas % 24 === 0) {
            $dias = (int) ($horas / 24);

            return $dias === 1 ? '1 día' : $dias.' días';
        }

        return $horas === 1 ? '1 hora' : $horas.' horas';
    }
}
