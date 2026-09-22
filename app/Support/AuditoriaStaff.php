<?php

namespace App\Support;

use App\Models\User;

class AuditoriaStaff
{
    /**
     * Cambios automáticos (login, sync, ping, app) que no son una acción de mesa.
     *
     * @var list<string>
     */
    public const CAMPOS_OPERATIVOS = [
        'push_token',
        'device_type',
        'ultimo_acceso_at',
        'ultimo_acceso_ip',
        'remember_token',
        'updated_at',
        'created_at',
        'ultimo_ingreso',
        'dispositivo',
        'app_version',
        'app_activa',
        'fecha_activacion_app',
        'email_verified_at',
        'last_synced',
        'ros_id',
        'ping_latencia_ms',
        'ping_at',
        'ping_caido_desde',
        'ping_fallos_seguidos',
        'ping_alerta_enviada',
    ];

    public static function actorStaff(?object ...$candidatos): ?User
    {
        foreach ($candidatos as $candidato) {
            if ($candidato instanceof User && $candidato->esStaff()) {
                return $candidato;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $camposCambiados
     */
    public static function debeRegistrar(?User $actor, string $accion, array $camposCambiados = []): bool
    {
        if (! $actor instanceof User || ! $actor->esStaff()) {
            return false;
        }

        if ($accion === 'updated' && self::soloOperativo($camposCambiados)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $camposCambiados
     */
    public static function soloOperativo(array $camposCambiados): bool
    {
        $campos = [];
        foreach ($camposCambiados as $campo) {
            $nombre = is_string($campo) ? $campo : (string) $campo;
            if ($nombre === '' || in_array($nombre, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $campos[] = $nombre;
        }

        if ($campos === []) {
            return true;
        }

        foreach ($campos as $campo) {
            if (! in_array($campo, self::CAMPOS_OPERATIVOS, true)) {
                return false;
            }
        }

        return true;
    }
}
