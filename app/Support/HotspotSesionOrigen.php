<?php

namespace App\Support;

/**
 * El router intenta entrar por MAC antes de mostrar el portal.
 * Si esa MAC no corresponde a una cuenta, la sesión no es un locker:
 * la persona sigue en «use una cuenta para iniciar sesión».
 */
class HotspotSesionOrigen
{
    public static function de(string $user, bool $tieneLocker): string
    {
        if ($tieneLocker) {
            return 'locker';
        }

        $user = trim($user);
        if ($user === '') {
            return 'sin-usuario';
        }
        if (self::esMac($user)) {
            return 'sin-sesion';
        }
        if (stripos($user, 'trial') !== false || preg_match('/^T-/i', $user) === 1) {
            return 'trial';
        }

        return 'sin-locker';
    }

    public static function esMac(string $valor): bool
    {
        return (bool) preg_match('/^([0-9a-fA-F]{2}[:-]){5}[0-9a-fA-F]{2}$/', trim($valor));
    }
}
