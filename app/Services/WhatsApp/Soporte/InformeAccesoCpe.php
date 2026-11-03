<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\CpeAccesoFallo;
use App\Models\Servicio;
use Illuminate\Support\Collection;

/**
 * Equipos cuyo SSH o ACS no responde. El informe es para el técnico, no para el cliente.
 */
class InformeAccesoCpe
{
    /**
     * @param  array<string, mixed>  $respuesta
     */
    public static function registrarSiFalla(Servicio $servicio, string $via, array $respuesta): ?string
    {
        $via = $via === 'acs' ? 'acs' : 'ssh';
        $ok = (bool) ($respuesta['success'] ?? $respuesta['ok'] ?? false);
        $msg = trim((string) ($respuesta['message'] ?? $respuesta['error'] ?? ''));
        $bajo = mb_strtolower($msg);

        if ($ok) {
            self::limpiar((int) $servicio->getKey(), $via);

            return null;
        }

        // El CPE es SSH a propósito: no es una falla de ACS.
        if ($via === 'acs' && str_contains($bajo, 'acceso ssh')) {
            return null;
        }
        if (str_contains($bajo, 'no hay leases')) {
            return null;
        }

        if ($msg === '') {
            $msg = $via === 'ssh' ? 'SSH sin respuesta' : 'ACS sin respuesta';
        }

        $ip = trim((string) ($servicio->ip ?? ''));
        self::anotar(
            (int) $servicio->getKey(),
            isset($servicio->cliente_id) ? (int) $servicio->cliente_id : null,
            $via,
            $ip !== '' ? $ip : null,
            mb_substr($msg, 0, 500),
        );

        return strtoupper($via).($ip !== '' ? ' '.$ip : '').': '.$msg;
    }

    public static function anotar(int $servicioId, ?int $clienteId, string $via, ?string $ip, string $error): void
    {
        if ($servicioId <= 0) {
            return;
        }

        try {
            CpeAccesoFallo::query()->updateOrCreate(
                ['servicio_id' => $servicioId, 'via' => $via],
                [
                    'cliente_id' => $clienteId,
                    'ip' => $ip,
                    'error' => $error,
                    'ultimo_at' => now(),
                ],
            );
        } catch (\Throwable) {
            // La tabla puede no estar migrada todavía.
        }
    }

    public static function limpiar(int $servicioId, string $via): void
    {
        if ($servicioId <= 0) {
            return;
        }

        try {
            CpeAccesoFallo::query()
                ->where('servicio_id', $servicioId)
                ->where('via', $via)
                ->delete();
        } catch (\Throwable) {
        }
    }

    public static function texto(int $servicioId): string
    {
        if ($servicioId <= 0) {
            return '';
        }

        try {
            $filas = CpeAccesoFallo::query()
                ->where('servicio_id', $servicioId)
                ->orderBy('via')
                ->get();
        } catch (\Throwable) {
            return '';
        }

        $lineas = [];
        foreach ($filas as $fila) {
            $lineas[] = strtoupper((string) $fila->via)
                .($fila->ip ? ' '.$fila->ip : '')
                .': '.$fila->error;
        }

        return implode("\n", $lineas);
    }

    /**
     * @return Collection<int, CpeAccesoFallo>
     */
    public static function recientes(int $limite = 100): Collection
    {
        try {
            return CpeAccesoFallo::query()
                ->orderByDesc('ultimo_at')
                ->limit($limite)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
