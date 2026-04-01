<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaPeriodica extends Model
{
    protected $table = 'tareas_periodicas';

    protected $fillable = [
        'nombre',
        'accion',
        'resultado',
        'estado',
        'ultima_aplicacion',
        'total_veces_aplicada',
        'nodo_id',
    ];

    protected function casts(): array
    {
        return [
            'ultima_aplicacion' => 'datetime',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public static function estados(): array
    {
        return [
            'activo' => 'Activo',
            'pausado' => 'Pausado',
            'error' => 'Error',
        ];
    }
}
