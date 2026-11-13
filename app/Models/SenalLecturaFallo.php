<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SenalLecturaFallo extends Model
{
    protected $table = 'senal_lectura_fallos';

    public const TIPO_OPTICA = 'optica';

    public const TIPO_ANTENA = 'antena';

    public const MOTIVOS = [
        'sin_asociar' => 'Sin ONU asociada',
        'asociacion_ambigua' => 'Varias ONU con la misma descripción',
        'sin_rx' => 'La OLT no devolvió potencia',
        'olt_sin_respuesta' => 'La OLT no respondió',
        'lectura_invalida' => 'Potencia fuera de rango',
        'sin_ip' => 'Sin IP',
        'sin_respuesta' => 'La antena no respondió',
        'sin_senal' => 'La antena respondió sin intensidad',
    ];

    protected $fillable = [
        'senal_lectura_id',
        'servicio_id',
        'tipo',
        'motivo',
        'detalle',
    ];

    public function lectura(): BelongsTo
    {
        return $this->belongsTo(SenalLectura::class, 'senal_lectura_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function motivoEtiqueta(): string
    {
        return self::MOTIVOS[$this->motivo] ?? $this->motivo;
    }
}
