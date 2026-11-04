<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushAvisoEntrega extends Model
{
    protected $table = 'push_aviso_entregas';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    public const OMITIDO = 'omitido';

    protected $fillable = [
        'push_aviso_id',
        'cliente_id',
        'estado',
        'motivo',
    ];

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(PushAviso::class, 'push_aviso_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    /**
     * @return array<string, string>
     */
    public static function estados(): array
    {
        return [
            self::ENVIADO => 'Entregado',
            self::FALLIDO => 'No entregado',
            self::OMITIDO => 'Sin app',
        ];
    }

    public function motivoVisible(): string
    {
        return self::motivoLegible($this->motivo, (string) $this->estado);
    }

    public static function motivoLegible(?string $motivo, string $estado): string
    {
        $map = [
            'UNREGISTERED' => 'La app ya no está instalada o el aviso no tiene un token vigente',
            'NOT_FOUND' => 'La app ya no está instalada o el aviso no tiene un token vigente',
            'INVALID_ARGUMENT' => 'El token de la app no es válido',
            'SENDER_ID_MISMATCH' => 'El token no corresponde a esta app',
            'QUOTA_EXCEEDED' => 'Se alcanzó el límite de envíos',
            'UNAVAILABLE' => 'El servicio de notificaciones no respondió',
            'INTERNAL' => 'El servicio de notificaciones no respondió',
            'SIN_TOKEN' => 'Sin token de notificación',
            'SIN_SERVICIO' => 'El envío de notificaciones no está configurado',
            'ERROR' => 'Error al enviar',
            'NO_ENTREGADO' => 'No se pudo entregar',
        ];

        if ($motivo && isset($map[$motivo])) {
            return $map[$motivo];
        }

        if ($estado === self::ENVIADO) {
            return 'Entregado';
        }

        if ($estado === self::OMITIDO) {
            return 'Sin app o sin token de notificación';
        }

        return $motivo ? 'No se pudo entregar ('.$motivo.')' : 'No se pudo entregar';
    }
}
