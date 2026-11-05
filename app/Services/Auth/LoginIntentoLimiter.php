<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Límite de intentos fallidos por cuenta, no por IP.
 * Varias personas detrás de la misma salida no se bloquean entre sí.
 */
class LoginIntentoLimiter
{
    public const MAX_FALLOS = 8;

    public const PAUSA_SEGUNDOS = 900;

    public function bloqueado(string $cuenta): bool
    {
        $key = $this->clave($cuenta);
        if ($key === null) {
            return false;
        }

        return RateLimiter::tooManyAttempts($key, self::MAX_FALLOS);
    }

    public function segundos(string $cuenta): int
    {
        $key = $this->clave($cuenta);

        return $key === null ? 0 : RateLimiter::availableIn($key);
    }

    public function registrarFallo(string $cuenta): void
    {
        $key = $this->clave($cuenta);
        if ($key === null) {
            return;
        }

        RateLimiter::hit($key, self::PAUSA_SEGUNDOS);
    }

    public function limpiar(string $cuenta): void
    {
        $key = $this->clave($cuenta);
        if ($key === null) {
            return;
        }

        RateLimiter::clear($key);
    }

    public function mensaje(string $cuenta): string
    {
        $minutos = max(1, (int) ceil($this->segundos($cuenta) / 60));

        return "Demasiados intentos. Esperá {$minutos} min y volvé a intentar.";
    }

    private function clave(string $cuenta): ?string
    {
        $cuenta = strtolower(trim($cuenta));
        if ($cuenta === '') {
            return null;
        }

        return 'login-cuenta:'.hash('sha256', $cuenta);
    }
}
