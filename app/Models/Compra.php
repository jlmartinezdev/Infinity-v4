<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use Auditable;

    protected $table = 'compras';

    protected $fillable = [
        'proveedor_id',
        'fecha',
        'numero_factura',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'pagado',
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
            'pagado' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'referencia_id')->where('tipo', 'compra');
    }

    public function saldoPendiente(): float
    {
        return (float) $this->total - (float) $this->pagado;
    }

    public function estaPagado(): bool
    {
        return $this->pagado >= $this->total;
    }
}
