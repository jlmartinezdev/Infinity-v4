<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffRecorridoPunto extends Model
{
    protected $table = 'staff_recorrido_puntos';

    protected $fillable = [
        'usuario_id',
        'lat',
        'lng',
        'accuracy',
        'visita_id',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'float',
            'visita_id' => 'integer',
            'reported_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
