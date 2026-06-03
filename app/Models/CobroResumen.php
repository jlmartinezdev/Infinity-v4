<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CobroResumen extends Model
{
    protected $table = 'cobros_resumen';

    protected $fillable = [
        'mes',
        'total_facturado',
        'total_pendiente',
        'total_cobrado',
        'pago_adelantado',
        'pago_atrasado',
    ];

    protected function casts(): array
    {
        return [
            'mes' => 'date',
            'total_facturado' => 'decimal:2',
            'total_pendiente' => 'decimal:2',
            'total_cobrado' => 'decimal:2',
            'pago_adelantado' => 'decimal:2',
            'pago_atrasado' => 'decimal:2',
        ];
    }

    public static function totalCobradoParaMes(?Carbon $mesReferencia = null): float
    {
        $mes = ($mesReferencia ?? now())->copy()->startOfMonth()->toDateString();

        return (float) (static::query()->where('mes', $mes)->value('total_cobrado') ?? 0);
    }

    public static function totalPendienteParaMes(?Carbon $mesReferencia = null): float
    {
        $mes = ($mesReferencia ?? now())->copy()->startOfMonth()->toDateString();

        return (float) (static::query()->where('mes', $mes)->value('total_pendiente') ?? 0);
    }

    /**
     * @return Collection<string, self> Clave Y-m
     */
    public static function mapaPorRangoMeses(Carbon $desde, Carbon $hasta): Collection
    {
        return static::query()
            ->whereBetween('mes', [
                $desde->copy()->startOfMonth()->toDateString(),
                $hasta->copy()->startOfMonth()->toDateString(),
            ])
            ->orderBy('mes')
            ->get()
            ->keyBy(fn (self $r) => $r->mes->format('Y-m'));
    }
}
