<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cobro extends Model
{
    use Auditable;

    protected $fillable = [
        'cliente_id',
        'factura_interna_id',
        'monto',
        'fecha_pago',
        'forma_pago',
        'numero_recibo',
        'referencia',
        'concepto',
        'observaciones',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    public static function formasPago(): array
    {
        return [
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'tarjeta' => 'Tarjeta',
            'cheque' => 'Cheque',
            'otro' => 'Otro',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    /** Facturas internas asociadas a este cobro (un cobro puede aplicar a varias facturas). */
    public function facturaInternas(): BelongsToMany
    {
        return $this->belongsToMany(FacturaInterna::class, 'cobro_factura_interna', 'cobro_id', 'factura_interna_id')
            ->withPivot('monto')
            ->withTimestamps();
    }

    /** Primera factura interna (compatibilidad con código que espera una sola). */
    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class, 'factura_interna_id', 'id');
    }

    /** Factura interna asociada al cobro (primera de la relación many, o legacy factura_interna_id). */
    public function getFacturaAttribute(): ?FacturaInterna
    {
        if ($this->relationLoaded('facturaInternas') && $this->facturaInternas->isNotEmpty()) {
            return $this->facturaInternas->first();
        }
        return $this->facturaInterna;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Formato fijo: 000-000-0000000 (3-3-7 dígitos).
     */
    public static function formatearValorNumericoRecibo(int $valor): string
    {
        return sprintf(
            '%03d-%03d-%07d',
            (int) ($valor / 10000000000) % 1000,
            (int) ($valor / 10000000) % 1000,
            $valor % 10000000
        );
    }

    /**
     * Reserva N números consecutivos de forma atómica (multiusuario).
     * Debe llamarse dentro de DB::transaction.
     *
     * @return array<int, string>
     */
    public static function reservarSiguientesNumerosRecibo(int $cantidad): array
    {
        if ($cantidad < 1) {
            throw new \InvalidArgumentException('La cantidad debe ser al menos 1.');
        }

        $row = DB::table('recibo_secuencias')->where('id', 1)->lockForUpdate()->first();
        if (!$row) {
            throw new \RuntimeException('Secuencia de recibos no inicializada. Ejecute las migraciones.');
        }

        $inicio = (int) $row->ultimo_valor + 1;
        $fin = $inicio + $cantidad - 1;
        DB::table('recibo_secuencias')->where('id', 1)->update(['ultimo_valor' => $fin]);

        $result = [];
        for ($v = $inicio; $v <= $fin; $v++) {
            $result[] = static::formatearValorNumericoRecibo($v);
        }

        return $result;
    }
}
