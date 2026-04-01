<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    use Auditable;

    protected $table = 'factura_electronicas';

    protected $fillable = [
        'cliente_id',
        'tipo_documento',
        'estado',
        'numero_timbrado',
        'timbrado_vigencia_desde',
        'timbrado_vigencia_hasta',
        'establecimiento',
        'punto_emision',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'total_impuestos',
        'total',
        'observaciones',
        'usuario_id',
        'set_cdc',
        'set_qr_url',
        'set_fecha_autorizacion',
        'set_estado_envio',
        'xml_path',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'timbrado_vigencia_desde' => 'date',
            'timbrado_vigencia_hasta' => 'date',
            'set_fecha_autorizacion' => 'datetime',
            'subtotal' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'total' => 'decimal:2',
            'tipo_cambio' => 'decimal:4',
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
        return $this->hasMany(FacturaDetalle::class, 'factura_electronica_id')->orderBy('id');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'factura_electronica_id');
    }

    /** Monto aplicado a esta factura (cap en total para sobrepagos que van a saldo a favor). */
    public function getMontoPagadoAttribute(): float
    {
        $suma = $this->relationLoaded('cobros')
            ? (float) $this->cobros->sum('monto')
            : (float) $this->cobros()->sum('monto');
        return min((float) $this->total, $suma);
    }

    /** Saldo pendiente (total - cobrado). */
    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total - $this->monto_pagado);
    }

    /** Si está totalmente pagada. */
    public function getEstaPagadaAttribute(): bool
    {
        return $this->saldo_pendiente <= 0;
    }

    /**
     * Número de factura formateado (establecimiento-punto-número) Paraguay.
     */
    public function getNumeroCompletoAttribute(): ?string
    {
        if ($this->numero === null) {
            return null;
        }
        return sprintf(
            '%03d-%03d-%07d',
            $this->establecimiento,
            $this->punto_emision,
            $this->numero
        );
    }

    public static function tiposDocumento(): array
    {
        return [
            'factura_contado' => 'Factura al contado',
            'factura_credito' => 'Factura a crédito',
            'nota_credito' => 'Nota de crédito',
            'nota_debito' => 'Nota de débito',
        ];
    }

    public static function estados(): array
    {
        return [
            'borrador' => 'Borrador',
            'emitida' => 'Emitida',
            'anulada' => 'Anulada',
        ];
    }

    /**
     * Recalcula subtotal, total_impuestos y total desde los detalles.
     */
    public function recalcularTotales(): void
    {
        $subtotal = $this->detalles->sum('subtotal');
        $totalImpuestos = $this->detalles->sum('monto_impuesto');
        $total = $this->detalles->sum('total');
        $this->update([
            'subtotal' => $subtotal,
            'total_impuestos' => $totalImpuestos,
            'total' => $total,
        ]);
    }
}
