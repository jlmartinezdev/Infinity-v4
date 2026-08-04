<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanUpsell extends Model
{
    protected $table = 'planes_upsell';

    protected $fillable = [
        'plan_id',
        'beneficios',
        'activo',
        'es_superior',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'es_superior' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'plan_id');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function toPortalArray(): array
    {
        $plan = $this->plan;

        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'nombre' => $plan?->nombre,
            'velocidad' => $plan?->velocidad,
            'precio' => $plan ? (float) $plan->precio : null,
            'beneficios' => $this->beneficios,
            'es_superior' => (bool) $this->es_superior,
            'activo' => (bool) $this->activo,
            'orden' => (int) $this->orden,
        ];
    }
}
