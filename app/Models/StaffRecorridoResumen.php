<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffRecorridoResumen extends Model
{
    protected $table = 'staff_recorrido_resumenes';

    protected $fillable = [
        'usuario_id',
        'fecha',
        'puntos_n',
        'distancia_metros',
        'visitas',
        'inicio_at',
        'fin_at',
        'lat_inicio',
        'lng_inicio',
        'lat_fin',
        'lng_fin',
        'traza',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'puntos_n' => 'integer',
            'distancia_metros' => 'float',
            'visitas' => 'integer',
            'inicio_at' => 'datetime',
            'fin_at' => 'datetime',
            'lat_inicio' => 'float',
            'lng_inicio' => 'float',
            'lat_fin' => 'float',
            'lng_fin' => 'float',
            'traza' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
