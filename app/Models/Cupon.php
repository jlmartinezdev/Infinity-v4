<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cupon extends Model
{
    public const TIPO_BIENVENIDA = 'bienvenida_app';

    public const PENDIENTE = 'pendiente';

    public const CANJEADO = 'canjeado';

    public const ANULADO = 'anulado';

    protected $table = 'cupones';

    protected $fillable = [
        'cliente_id',
        'tipo',
        'porcentaje',
        'campana_id',
        'ganador_id',
        'estado',
        'factura_interna_id',
        'ventana',
        'canjeado_at',
        'created_by',
        'anulado_por',
        'anulado_at',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
            'canjeado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class, 'factura_interna_id');
    }
}
