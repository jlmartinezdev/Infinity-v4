<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use Auditable;

    protected $table = 'ventas';

    protected $fillable = [
        'cliente_id',
        'servicio_id',
        'fecha',
        'numero_factura',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'cobrado',
        'estado',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
            'cobrado' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function saldoPendiente(): float
    {
        return (float) $this->total - (float) $this->cobrado;
    }

    public function estaCobrado(): bool
    {
        return $this->cobrado >= $this->total;
    }
}
