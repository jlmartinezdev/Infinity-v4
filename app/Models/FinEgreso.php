<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinEgreso extends Model
{
    use Auditable;

    protected $table = 'fin_egresos';

    protected $fillable = [
        'cuenta_id', 'categoria_id', 'fecha', 'importe', 'proveedor_id',
        'comprobante', 'usuario_id', 'usuario_staff_id', 'observaciones', 'estado',
        'plantilla_id', 'documento_path', 'factura_path',
    ];

    protected $casts = [
        'fecha' => 'date',
        'importe' => 'decimal:2',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(FinCuenta::class, 'cuenta_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(FinCategoria::class, 'categoria_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(FinEgresoPlantilla::class, 'plantilla_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_staff_id', 'usuario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function movimiento(): HasOne
    {
        return $this->hasOne(FinMovimiento::class, 'referencia_id', 'id')
            ->where('referencia_tipo', 'egreso')
            ->where('tipo', 'egreso');
    }
}
