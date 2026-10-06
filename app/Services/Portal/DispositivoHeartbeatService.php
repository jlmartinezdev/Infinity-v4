<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Dispositivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DispositivoHeartbeatService
{
    /**
     * Upsert dispositivo en login cliente: last_login + last_seen + app_activa + app_version.
     */
    public function registrarLogin(Cliente $cliente, ?string $deviceName, ?string $appVersion): Dispositivo
    {
        $key = Dispositivo::deviceKeyFromName($deviceName);
        $dispositivo = Dispositivo::query()->firstOrNew([
            'cliente_id' => $cliente->cliente_id,
            'device_key' => $key,
        ]);

        $now = now();
        if ($deviceName) {
            $dispositivo->nombre = Str::limit($deviceName, 120, '');
        }
        $version = self::normalizarVersion($appVersion);
        if ($version !== null) {
            $dispositivo->app_version = $version;
        }
        $dispositivo->app_activa = true;
        $dispositivo->last_login = $now;
        $dispositivo->last_seen = $now;
        $dispositivo->save();

        // Mantener columnas legacy en clientes (compat web / resumen)
        $clienteData = [
            'ultimo_ingreso' => $now,
            'app_activa' => true,
            'dispositivo' => $dispositivo->nombre ?: $cliente->dispositivo,
            'app_version' => $dispositivo->app_version ?: $cliente->app_version,
        ];
        if (! $cliente->app_activa || ! $cliente->fecha_activacion_app) {
            $clienteData['fecha_activacion_app'] = $cliente->fecha_activacion_app ?? $now;
        }
        $cliente->update($clienteData);

        return $dispositivo;
    }

    /**
     * Heartbeat en requests portal autenticados (throttle ~60s para last_seen).
     * Si llega app_version distinta, se persiste aunque el heartbeat esté throttled
     * (caso típico: actualización de APK sin volver a hacer login).
     */
    public function tocarLastSeen(int $clienteId, ?string $deviceName = null, ?string $appVersion = null): void
    {
        if ($clienteId <= 0) {
            return;
        }

        $version = self::normalizarVersion($appVersion);

        $cacheKey = 'dispositivo:last_seen:'.$clienteId;
        $throttled = Cache::has($cacheKey);
        if ($throttled && $version === null) {
            return;
        }
        if (! $throttled) {
            Cache::put($cacheKey, 1, now()->addSeconds(60));
        }

        $key = Dispositivo::deviceKeyFromName($deviceName);
        $dispositivo = Dispositivo::query()
            ->where('cliente_id', $clienteId)
            ->when($deviceName, fn ($q) => $q->where('device_key', $key))
            ->orderByDesc('last_seen')
            ->orderByDesc('id')
            ->first();

        $now = now();
        $dispositivoDirty = false;

        if (! $dispositivo) {
            $dispositivo = Dispositivo::query()->create([
                'cliente_id' => $clienteId,
                'device_key' => $key,
                'nombre' => $deviceName ? Str::limit($deviceName, 120, '') : null,
                'app_version' => $version,
                'app_activa' => true,
                'last_seen' => $now,
                'last_login' => null,
            ]);
        } else {
            if (! $throttled) {
                $dispositivo->last_seen = $now;
                $dispositivoDirty = true;
            }
            if (! $dispositivo->app_activa) {
                $dispositivo->app_activa = true;
                $dispositivoDirty = true;
            }
            if ($deviceName && ! $dispositivo->nombre) {
                $dispositivo->nombre = Str::limit($deviceName, 120, '');
                $dispositivoDirty = true;
            }
            if ($version !== null && (string) $dispositivo->app_version !== $version) {
                $dispositivo->app_version = $version;
                $dispositivoDirty = true;
            }
            if ($dispositivoDirty) {
                $dispositivo->save();
            }
        }

        $clienteUpdate = [];
        if (! $throttled) {
            // Sync liviano del legacy ultimo_ingreso solo si está atrasado > 5 min
            $cliente = Cliente::query()
                ->where('cliente_id', $clienteId)
                ->first(['cliente_id', 'ultimo_ingreso', 'app_activa', 'app_version']);
            if ($cliente) {
                if ($cliente->ultimo_ingreso === null || $cliente->ultimo_ingreso->lt($now->copy()->subMinutes(5))) {
                    $clienteUpdate['ultimo_ingreso'] = $now;
                }
                if (! $cliente->app_activa) {
                    $clienteUpdate['app_activa'] = true;
                }
                if ($version !== null && (string) $cliente->app_version !== $version) {
                    $clienteUpdate['app_version'] = $version;
                }
            }
        } elseif ($version !== null) {
            // Solo versión (sesión viva post-update sin re-login)
            Cliente::query()
                ->where('cliente_id', $clienteId)
                ->where(function ($q) use ($version) {
                    $q->whereNull('app_version')->orWhere('app_version', '!=', $version);
                })
                ->update(['app_version' => $version]);
        }

        if ($clienteUpdate !== []) {
            Cliente::query()->where('cliente_id', $clienteId)->update($clienteUpdate);
        }
    }

    public static function normalizarVersion(?string $appVersion): ?string
    {
        $v = trim((string) $appVersion);
        if ($v === '') {
            return null;
        }

        return Str::limit($v, 40, '');
    }

    /**
     * Lee versión desde headers habituales de la app.
     */
    public static function versionDesdeRequest(\Illuminate\Http\Request $request): ?string
    {
        foreach (['X-App-Version', 'X-App-Version-Name', 'App-Version'] as $header) {
            $v = self::normalizarVersion($request->header($header));
            if ($v !== null) {
                return $v;
            }
        }

        return self::normalizarVersion($request->input('app_version'));
    }
}
