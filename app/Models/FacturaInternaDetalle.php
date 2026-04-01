<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaInternaDetalle extends Model
{
    protected $table = 'factura_interna_detalles';

    protected $fillable = [
        'factura_interna_id',
        'impuesto_id',
        'servicio_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'porcentaje_impuesto',
        'monto_impuesto',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'porcentaje_impuesto' => 'decimal:2',
            'monto_impuesto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class);
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }
}
