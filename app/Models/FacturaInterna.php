<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturaInterna extends Model
{
    use Auditable;

    protected $table = 'factura_internas';

    protected $fillable = [
        'cliente_id',
        'periodo_desde',
        'periodo_hasta',
        'fecha_emision',
        'fecha_vencimiento',
        'fecha_pago',
        'estado',
        'moneda',
        'subtotal',
        'total_impuestos',
        'total',
        'descuento',
        'observaciones',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
            'subtotal' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'total' => 'decimal:2',
            'descuento' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaInternaDetalle::class)->orderBy('id');
    }

    /** Cobros que aplican a esta factura (vía pivot cobro_factura_interna con monto por factura). */
    public function cobros(): BelongsToMany
    {
        return $this->belongsToMany(Cobro::class, 'cobro_factura_interna', 'factura_interna_id', 'cobro_id')
            ->withPivot('monto')
            ->withTimestamps();
    }

    /** Monto aplicado a esta factura (suma de pivot.monto; cap en total para no exceder por sobrepagos). */
    public function getMontoPagadoAttribute(): float
    {
        $suma = (float) DB::table('cobro_factura_interna')
            ->where('factura_interna_id', $this->id)
            ->sum('monto');
        return min((float) $this->total, $suma);
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total - $this->monto_pagado);
    }

    public function getEstaPagadaAttribute(): bool
    {
        return $this->saldo_pendiente <= 0;
    }

    public static function estados(): array
    {
        return [
            'pendiente' => 'Pendiente de pago',
            'pagada' => 'Pagada',
            'cancelada' => 'Cancelada',
            'emitida' => 'Emitida (facturación electrónica)',
            'anulada' => 'Anulada (facturación electrónica)',
        ];
    }
}
