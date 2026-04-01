<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gasto extends Model
{
    use Auditable;

    protected $table = 'gastos';

    protected $fillable = [
        'categoria_gasto_id',
        'proveedor_id',
        'fecha',
        'monto',
        'descripcion',
        'referencia',
        'pagado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'pagado' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_gasto_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'referencia_id')->where('tipo', 'gasto');
    }
}
