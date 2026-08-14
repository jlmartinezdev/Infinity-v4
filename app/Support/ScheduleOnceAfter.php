<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tareas diarias que no deben perderse si el PC estaba apagado a la hora exacta.
 */
class ScheduleOnceAfter
{
    public static function due(string $key, string $horaHhMm): bool
    {
        $horaHhMm = self::normalizeHora($horaHhMm);
        if (now()->format('H:i') < $horaHhMm) {
            return false;
        }

        return ! Cache::get(self::cacheKey($key));
    }

    public static function markDone(string $key): void
    {
        Cache::put(self::cacheKey($key), 1, now()->endOfDay());
    }

    public static function doneToday(string $key): bool
    {
        return (bool) Cache::get(self::cacheKey($key));
    }

    public static function workerActivo(int $maxSegundos = 180): bool
    {
        $log = storage_path('logs/schedule-work.log');
        if (! is_file($log)) {
            return false;
        }

        return filemtime($log) >= time() - $maxSegundos;
    }

    public static function ultimoLatido(): ?\Carbon\CarbonInterface
    {
        $log = storage_path('logs/schedule-work.log');
        if (! is_file($log)) {
            return null;
        }

        return \Carbon\Carbon::createFromTimestamp(filemtime($log));
    }

    public static function normalizeHora(string $hora): string
    {
        $hora = trim($hora);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            return '00:00';
        }
        [$h, $m] = array_map('intval', explode(':', $hora));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return '00:00';
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    private static function cacheKey(string $key): string
    {
        return 'schedule:once:'.$key.':'.now()->toDateString();
    }
}
