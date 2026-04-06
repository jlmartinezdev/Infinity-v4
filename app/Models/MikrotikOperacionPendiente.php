<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MikrotikOperacionPendiente extends Model
{
    protected $table = 'mikrotik_operaciones_pendientes';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_COMPLETADO = 'completado';

    public const ESTADO_DESCARTADO = 'descartado';

    public const TIPO_SYNC_PPPOE_SERVICIO = 'sync_pppoe_servicio';

    public const TIPO_PPPOE_DISABLED = 'pppoe_disabled';

    public const TIPO_REMOVE_PPPOE_SECRET = 'remove_pppoe_secret';

    public const TIPO_SYNC_HOTSPOT = 'sync_hotspot_usuario';

    protected $fillable = [
        'tipo',
        'payload',
        'error_ultimo',
        'origen',
        'intentos',
        'estado',
        'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    /**
     * Registra un fallo de MikroTik para reintento. Si ya existe un pendiente con el mismo tipo y datos clave, actualiza el error.
     */
    public static function registrarSiFallo(string $tipo, array $payload, string $errorMensaje, ?string $origen = null): void
    {
        $errorMensaje = mb_substr($errorMensaje, 0, 5000);

        $q = static::query()->pendientes()->where('tipo', $tipo);

        if (! empty($payload['servicio_id'])) {
            $q->where('payload->servicio_id', $payload['servicio_id']);
        } elseif (! empty($payload['servicio_hotspot_id'])) {
            $q->where('payload->servicio_hotspot_id', $payload['servicio_hotspot_id']);
        } elseif (! empty($payload['router_id']) && ! empty($payload['usuario_pppoe'])) {
            $q->where('payload->router_id', $payload['router_id'])
                ->where('payload->usuario_pppoe', $payload['usuario_pppoe']);
        } else {
            static::create([
                'tipo' => $tipo,
                'payload' => $payload,
                'error_ultimo' => $errorMensaje,
                'origen' => $origen,
                'estado' => self::ESTADO_PENDIENTE,
            ]);

            return;
        }

        $existente = $q->first();
        if ($existente) {
            $existente->update([
                'error_ultimo' => $errorMensaje,
                'origen' => $origen ?? $existente->origen,
            ]);

            return;
        }

        static::create([
            'tipo' => $tipo,
            'payload' => $payload,
            'error_ultimo' => $errorMensaje,
            'origen' => $origen,
            'estado' => self::ESTADO_PENDIENTE,
        ]);
    }
}
