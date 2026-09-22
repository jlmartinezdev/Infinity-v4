<?php

namespace App\Traits;

use App\Models\Auditoria;
use App\Models\User;
use App\Services\NotificacionAccionService;
use App\Support\AuditoriaStaff;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Atributos que no se guardan en los detalles del audit (contraseñas, tokens).
     *
     * @var array<string>
     */
    protected static array $auditHidden = [
        'password',
        'contrasena',
        'password_pppoe',
        'remember_token',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            static::registrarAuditoria($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (self $model) {
            static::registrarAuditoria($model, 'updated', $model->getOriginal(), $model->getAttributes());
        });

        static::deleted(function (self $model) {
            static::registrarAuditoria($model, 'deleted', $model->getAttributes(), null);
        });
    }

    /**
     * Registra un evento en la tabla auditoria.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected static function registrarAuditoria(self $model, string $accion, ?array $oldValues, ?array $newValues): void
    {
        $actor = static::actorStaffActual();
        $campos = $accion === 'updated' ? array_keys($model->getChanges()) : [];
        if (! AuditoriaStaff::debeRegistrar($actor, $accion, $campos)) {
            return;
        }

        $tabla = $model->getTable();
        $keyName = $model->getKeyName();
        if (is_array($keyName)) {
            $keyParts = [];
            foreach ($keyName as $name) {
                $keyParts[] = $model->getAttribute($name);
            }
            $registroId = 0;
            $registroKey = implode('|', array_map(static fn ($v) => (string) $v, $keyParts));
        } else {
            $key = $model->getKey();
            $registroId = is_numeric($key) ? (int) $key : 0;
            $registroKey = is_numeric($key) ? null : (string) $key;
        }

        $detalles = [];
        if ($oldValues !== null) {
            $detalles['old'] = static::ocultarSensibles($oldValues);
        }
        if ($newValues !== null) {
            $detalles['new'] = static::ocultarSensibles($newValues);
        }

        $usuarioId = $actor?->getKey();

        $request = Request::instance();
        $ipAddress = $request ? $request->ip() : null;
        $userAgent = $request ? $request->userAgent() : null;
        if ($userAgent !== null && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }

        $auditoria = Auditoria::create([
            'usuario_id' => $usuarioId,
            'tabla' => $tabla,
            'accion' => $accion,
            'registro_id' => $registroId,
            'registro_key' => $registroKey,
            'detalles' => json_encode($detalles),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        try {
            app(NotificacionAccionService::class)->notificarAccion($auditoria);
        } catch (\Throwable $e) {
            // No fallar la operación principal si falla el envío de notificaciones
        }
    }

    /**
     * Oculta atributos sensibles del array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected static function ocultarSensibles(array $attributes): array
    {
        $hidden = array_merge(
            static::$auditHidden,
            method_exists(static::class, 'getAuditHidden') ? static::getAuditHidden() : []
        );
        foreach ($hidden as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = '***';
            }
        }
        return $attributes;
    }

    protected static function actorStaffActual(): ?User
    {
        $candidatos = [];
        if (function_exists('auth')) {
            $candidatos[] = auth()->user();
            foreach (['web', 'sanctum'] as $guard) {
                try {
                    $candidatos[] = auth()->guard($guard)->user();
                } catch (\Throwable) {
                }
            }
        }
        $request = function_exists('request') ? request() : null;
        if ($request) {
            $candidatos[] = $request->user();
            try {
                $candidatos[] = $request->user('sanctum');
            } catch (\Throwable) {
            }
        }

        return AuditoriaStaff::actorStaff(...$candidatos);
    }
}
