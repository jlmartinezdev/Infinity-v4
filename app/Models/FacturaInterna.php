<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FacturaInterna extends Model
{
    use Auditable;

    public const TIPO_SERVICIO = 'servicio';

    public const TIPO_SERVICIO_ESPECIAL = 'servicio_especial';

    protected $table = 'factura_internas';

    protected $fillable = [
        'cliente_id',
        'tipo_factura',
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

    /** @return array<string, string> */
    public static function tiposFactura(): array
    {
        return [
            self::TIPO_SERVICIO => 'Servicio (período)',
            self::TIPO_SERVICIO_ESPECIAL => 'Servicio especial',
        ];
    }

    public function esServicioEspecial(): bool
    {
        return $this->tipo_factura === self::TIPO_SERVICIO_ESPECIAL;
    }

    public function etiquetaTipoFactura(): string
    {
        return self::tiposFactura()[$this->tipo_factura ?? self::TIPO_SERVICIO]
            ?? ($this->tipo_factura ?? self::TIPO_SERVICIO);
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

    /** Promesa de pago registrada para esta factura (como máximo una vigente). */
    public function promesaPago(): HasOne
    {
        return $this->hasOne(PromesaPago::class, 'factura_interna_id');
    }

    /** Cobros que aplican a esta factura (vía pivot cobro_factura_interna con monto por factura). */
    public function cobros(): BelongsToMany
    {
        return $this->belongsToMany(Cobro::class, 'cobro_factura_interna', 'factura_interna_id', 'cobro_id')
            ->withPivot('monto')
            ->withTimestamps();
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(FacturaInternaNotaCredito::class)->orderByDesc('id');
    }

    /** Monto aplicado a esta factura (suma de pivot.monto; cap en total para no exceder por sobrepagos). */
    public function getMontoPagadoAttribute(): float
    {
        $suma = (float) DB::table('cobro_factura_interna')
            ->where('factura_interna_id', $this->id)
            ->sum('monto');
        return min((float) $this->total, $suma);
    }

    public function getMontoNotasCreditoAttribute(): float
    {
        if ($this->relationLoaded('notasCredito')) {
            return (float) $this->notasCredito->sum('monto');
        }

        return (float) $this->notasCredito()->sum('monto');
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total - $this->monto_pagado - $this->monto_notas_credito);
    }

    /** Suma de cobros aplicados a factura_internas (subconsulta SQL). */
    public static function sqlSumCobros(): string
    {
        return '(SELECT COALESCE(SUM(monto),0) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id)';
    }

    /** Suma de notas de crédito emitidas sobre la factura (subconsulta SQL). */
    public static function sqlSumNotasCredito(): string
    {
        return '(SELECT COALESCE(SUM(monto),0) FROM factura_interna_notas_credito WHERE factura_interna_id = factura_internas.id)';
    }

    /** Saldo pendiente en SQL (total − cobrado − notas de crédito, mínimo 0). */
    public static function sqlSaldoPendienteExpr(): string
    {
        $cobrado = 'LEAST(factura_internas.total, '.self::sqlSumCobros().')';

        return 'GREATEST(factura_internas.total - '.$cobrado.' - '.self::sqlSumNotasCredito().', 0)';
    }

    /**
     * Verdadero si el cliente debe contarse en totales de saldo pendiente (activo y con servicio no cancelado).
     *
     * @param  string  $clienteIdColumn  Columna SQL del cliente_id (ej. factura_internas.cliente_id, fi_stats.cliente_id)
     */
    public static function sqlClienteCuentaEnTotalPendiente(string $clienteIdColumn = 'factura_internas.cliente_id'): string
    {
        $cancelado = Servicio::ESTADO_CANCELADO;

        return "(
            (SELECT c.estado FROM clientes c WHERE c.cliente_id = {$clienteIdColumn} LIMIT 1) != 'inactivo'
            AND EXISTS (
                SELECT 1 FROM servicios s
                WHERE s.cliente_id = {$clienteIdColumn}
                AND s.estado != '{$cancelado}'
            )
        )";
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
