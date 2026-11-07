<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinEgresoPlantilla extends Model
{
    use Auditable;

    protected $table = 'fin_egreso_plantillas';

    protected $fillable = ['nombre', 'monto', 'es_sueldo', 'usuario_staff_id', 'orden', 'activa'];

    protected $casts = [
        'monto' => 'decimal:2',
        'es_sueldo' => 'boolean',
        'activa' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_staff_id', 'usuario_id');
    }
}
