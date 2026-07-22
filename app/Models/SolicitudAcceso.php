<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SolicitudAcceso extends Model
{
    protected $table = 'solicitudes_acceso';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    protected $fillable = [
        'cedula',
        'nombre',
        'whatsapp',
        'direccion',
        'latitud',
        'longitud',
        'frente_path',
        'estado',
        'cliente_id',
        'aprobado_por',
        'aprobado_at',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
            'aprobado_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por', 'usuario_id');
    }

    public static function estados(): array
    {
        return [
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_APROBADA => 'Aprobada',
            self::ESTADO_RECHAZADA => 'Rechazada',
        ];
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function getFrenteUrlAttribute(): ?string
    {
        if (! $this->frente_path) {
            return null;
        }

        return Storage::disk('public')->url($this->frente_path);
    }
}
