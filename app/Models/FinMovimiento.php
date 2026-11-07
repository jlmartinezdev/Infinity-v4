<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class FinMovimiento extends Model
{
    use Auditable;

    protected $table = 'fin_movimientos';

    protected $fillable = [
        'cuenta_id', 'tipo', 'signo', 'fecha_efectiva', 'importe', 'concepto',
        'categoria_id', 'referencia_tipo', 'referencia_id', 'referencia_texto',
        'usuario_id', 'observaciones', 'revertido_por_id', 'transferencia_id', 'liquidacion_id',
    ];

    protected $casts = [
        'fecha_efectiva' => 'date',
        'importe' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new RuntimeException('Un movimiento contabilizado no se elimina. Se corrige con un reverso.');
        });
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FinCategoria::class, 'categoria_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
