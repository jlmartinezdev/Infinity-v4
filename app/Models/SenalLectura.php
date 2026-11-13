<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SenalLectura extends Model
{
    protected $table = 'senal_lecturas';

    protected $fillable = [
        'inicio_at',
        'fin_at',
        'estado',
        'parcial',
        'fibra_objetivo',
        'fibra_leidas',
        'fibra_fallidas',
        'antena_objetivo',
        'antena_leidas',
        'antena_fallidas',
        'nota',
    ];

    protected function casts(): array
    {
        return [
            'inicio_at' => 'datetime',
            'fin_at' => 'datetime',
            'parcial' => 'boolean',
        ];
    }

    public function fallos(): HasMany
    {
        return $this->hasMany(SenalLecturaFallo::class, 'senal_lectura_id');
    }

    public function duracionHumana(): string
    {
        if ($this->inicio_at === null || $this->fin_at === null) {
            return 'en curso';
        }

        $seg = max(0, $this->inicio_at->diffInSeconds($this->fin_at));
        if ($seg < 60) {
            return $seg.' s';
        }

        $min = intdiv($seg, 60);
        $resto = $seg % 60;

        return $resto > 0 ? "{$min} min {$resto} s" : "{$min} min";
    }
}
