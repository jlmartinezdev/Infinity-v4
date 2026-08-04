<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffUbicacion extends Model
{
    protected $table = 'staff_ubicaciones';

    protected $fillable = [
        'usuario_id',
        'lat',
        'lng',
        'accuracy',
        'heading',
        'en_turno',
        'visita_id',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'float',
            'heading' => 'float',
            'en_turno' => 'boolean',
            'visita_id' => 'integer',
            'reported_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function visita(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'visita_id', 'id');
    }

    /**
     * Payload para app admin / panel web (sin datos sensibles).
     *
     * @return array<string, mixed>
     */
    public function toFlotaItem(): array
    {
        $nombre = trim((string) ($this->usuario?->name ?? ''));

        return [
            'tecnico_id' => (int) $this->usuario_id,
            'nombre' => $nombre !== '' ? $nombre : 'Técnico #'.$this->usuario_id,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'accuracy' => $this->accuracy !== null ? (float) $this->accuracy : null,
            'en_turno' => (bool) $this->en_turno,
            'updated_at' => ($this->reported_at ?? $this->updated_at)?->utc()->toIso8601String(),
            'visita_id' => $this->visita_id !== null ? (int) $this->visita_id : null,
        ];
    }
}
