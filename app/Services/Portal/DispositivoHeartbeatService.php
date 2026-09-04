<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Dispositivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DispositivoHeartbeatService
{
    /**
     * Upsert dispositivo en login cliente: last_login + last_seen + app_activa.
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
        if ($appVersion) {
            $dispositivo->app_version = Str::limit($appVersion, 40, '');
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
     * Heartbeat en requests portal autenticados (throttle ~60s).
     */
    public function tocarLastSeen(int $clienteId, ?string $deviceName = null): void
    {
        if ($clienteId <= 0) {
            return;
        }

        $cacheKey = 'dispositivo:last_seen:'.$clienteId;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, 1, now()->addSeconds(60));

        $key = Dispositivo::deviceKeyFromName($deviceName);
        $dispositivo = Dispositivo::query()
            ->where('cliente_id', $clienteId)
            ->when($deviceName, fn ($q) => $q->where('device_key', $key))
            ->orderByDesc('last_seen')
            ->orderByDesc('id')
            ->first();

        $now = now();
        if (! $dispositivo) {
            $dispositivo = Dispositivo::query()->create([
                'cliente_id' => $clienteId,
                'device_key' => $key,
                'nombre' => $deviceName ? Str::limit($deviceName, 120, '') : null,
                'app_activa' => true,
                'last_seen' => $now,
                'last_login' => null,
            ]);
        } else {
            $dispositivo->last_seen = $now;
            if (! $dispositivo->app_activa) {
                $dispositivo->app_activa = true;
            }
            if ($deviceName && ! $dispositivo->nombre) {
                $dispositivo->nombre = Str::limit($deviceName, 120, '');
            }
            $dispositivo->save();
        }

        // Sync liviano del legacy ultimo_ingreso solo si está atrasado > 5 min
        Cliente::query()
            ->where('cliente_id', $clienteId)
            ->where(function ($q) use ($now) {
                $q->whereNull('ultimo_ingreso')
                    ->orWhere('ultimo_ingreso', '<', $now->copy()->subMinutes(5));
            })
            ->update([
                'ultimo_ingreso' => $now,
                'app_activa' => true,
            ]);
    }
}
