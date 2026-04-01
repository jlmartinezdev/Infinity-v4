<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'titulo',
        'descripcion',
        'estado',
        'prioridad',
        'orden',
        'usuario_id',
        'asignado_id',
        'fecha_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_id', 'usuario_id');
    }

    public static function estados(): array
    {
        return [
            'pendiente' => 'Pendiente',
            'en_progreso' => 'En progreso',
            'completado' => 'Completado',
        ];
    }

    public static function prioridades(): array
    {
        return [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
        ];
    }
}
