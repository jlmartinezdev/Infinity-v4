<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuntosLote extends Model
{
    protected $table = 'puntos_lotes';

    public const ORIGEN_BIENVENIDA = 'bienvenida';

    public const ORIGEN_REGLA = 'regla';

    public const ORIGEN_AJUSTE = 'ajuste';

    public const ORIGEN_REVERSA = 'reversa';

    public const ORIGEN_CREDITO = 'credito';

    public const ORIGEN_BACKFILL = 'backfill';

    protected $fillable = [
        'cliente_id',
        'puntos_movimiento_id',
        'puntos_iniciales',
        'puntos_restantes',
        'vence_at',
        'origen',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'puntos_iniciales' => 'integer',
            'puntos_restantes' => 'integer',
            'vence_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(PuntosMovimiento::class, 'puntos_movimiento_id');
    }

    public function scopeConSaldo(Builder $query): Builder
    {
        return $query->where('puntos_restantes', '>', 0);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->conSaldo()->where(function (Builder $q) {
            $q->whereNull('vence_at')->orWhere('vence_at', '>', now());
        });
    }

    public function scopeOrdenFifo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('vence_at IS NULL ASC')
            ->orderBy('vence_at')
            ->orderBy('id');
    }
}
