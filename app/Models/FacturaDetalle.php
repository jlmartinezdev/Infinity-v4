<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $table = 'factura_electronica_detalles';

    protected $fillable = [
        'factura_electronica_id',
        'impuesto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'porcentaje_impuesto',
        'monto_impuesto',
        'total',
        'servicio_id',
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

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_electronica_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    /**
     * Calcula subtotal, monto_impuesto y total según cantidad, precio e impuesto.
     */
    public static function calcularDesdePrecio(float $cantidad, float $precioUnitario, ?Impuesto $impuesto): array
    {
        $subtotal = round($cantidad * $precioUnitario, 2);
        $porcentaje = $impuesto ? (float) $impuesto->porcentaje : 0;
        $montoImpuesto = round($subtotal * ($porcentaje / 100), 2);
        $total = $subtotal + $montoImpuesto;
        return [
            'subtotal' => $subtotal,
            'porcentaje_impuesto' => $porcentaje,
            'monto_impuesto' => $montoImpuesto,
            'total' => $total,
        ];
    }
}
