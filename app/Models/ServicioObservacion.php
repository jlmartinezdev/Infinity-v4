<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioObservacion extends Model
{
    public const MOTIVO_CAMBIO = 'cambio_equipo';

    public const MOTIVO_FALLA = 'falla';

    public const MOTIVO_SENAL = 'senal';

    public const RESULTADO_RESUELTO = 'resuelto';

    public const RESULTADO_REINCIDIO = 'reincidio';

    /** @var list<int> */
    public const HORAS = [24, 48, 72, 168];

    protected $table = 'servicio_observaciones';

    protected $fillable = [
        'servicio_id',
        'ticket_id',
        'usuario_id',
        'motivo',
        'equipo',
        'nota',
        'horas',
        'inicia_at',
        'vence_at',
        'cerrada_at',
        'resultado',
    ];

    protected function casts(): array
    {
        return [
            'horas' => 'integer',
            'inicia_at' => 'datetime',
            'vence_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function estaAbierta(): bool
    {
        return $this->cerrada_at === null;
    }

    public static function motivoEtiqueta(string $motivo): string
    {
        return match ($motivo) {
            self::MOTIVO_CAMBIO => 'Cambio de equipo',
            self::MOTIVO_FALLA => 'Falla reportada',
            self::MOTIVO_SENAL => 'Señal o potencia',
            default => $motivo,
        };
    }

    public static function horasEtiqueta(int $horas): string
    {
        return $horas >= 168 ? '7 días' : $horas.' h';
    }
};
