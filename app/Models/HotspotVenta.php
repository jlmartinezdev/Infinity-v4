<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotVenta extends Model
{
    public const PENDIENTE = 'pendiente';

    public const PAGADO = 'pagado';

    public const RECHAZADO = 'rechazado';

    public const ERROR = 'error';

    protected $table = 'hotspot_ventas';

    protected $fillable = [
        'hotspot_plan_id',
        'token',
        'mac',
        'monto',
        'gigas',
        'vigencia_horas',
        'rate_limit',
        'estado',
        'username',
        'password',
        'tpago_payment_link_id',
        'checkout_url',
        'radius_ok',
        'pagado_at',
        'vence_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'integer',
            'gigas' => 'integer',
            'vigencia_horas' => 'integer',
            'radius_ok' => 'boolean',
            'pagado_at' => 'datetime',
            'vence_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(HotspotPlan::class, 'hotspot_plan_id', 'hotspot_plan_id');
    }

    public function estaPagada(): bool
    {
        return $this->estado === self::PAGADO && trim((string) $this->username) !== '';
    }

    /** @return array<string, string> */
    public static function estados(): array
    {
        return [
            self::PENDIENTE => 'Pendiente',
            self::PAGADO => 'Pagado',
            self::RECHAZADO => 'Rechazado',
            self::ERROR => 'Error',
        ];
    }
}
