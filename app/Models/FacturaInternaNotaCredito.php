<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaInternaNotaCredito extends Model
{
    use Auditable;

    protected $table = 'factura_interna_notas_credito';

    protected $fillable = [
        'factura_interna_id',
        'monto',
        'motivo',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
