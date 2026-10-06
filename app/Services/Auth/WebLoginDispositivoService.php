<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\WebLoginDispositivo;
use Illuminate\Support\Str;

class WebLoginDispositivoService
{
    public const DIAS = 30;

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @return array{token: string, expira_en: int} */
    public function emitir(User $user): array
    {
        $plain = Str::random(64);

        WebLoginDispositivo::query()->create([
            'usuario_id' => $user->usuario_id,
            'token_hash' => self::hashToken($plain),
            'expires_at' => now()->addDays(self::DIAS),
        ]);

        $viejos = WebLoginDispositivo::query()
            ->where('usuario_id', $user->usuario_id)
            ->orderByDesc('id')
            ->pluck('id')
            ->slice(8)
            ->values();

        if ($viejos->isNotEmpty()) {
            WebLoginDispositivo::query()->whereIn('id', $viejos)->delete();
        }

        return [
            'token' => $plain,
            'expira_en' => self::DIAS * 86400,
        ];
    }

    public function autenticar(string $email, string $token): ?User
    {
        $email = strtolower(trim($email));
        $token = trim($token);
        if ($email === '' || $token === '') {
            return null;
        }

        $row = WebLoginDispositivo::query()
            ->where('token_hash', self::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $row) {
            return null;
        }

        $user = User::query()->find($row->usuario_id);
        if (! $user || $user->estado !== 'activo' || $user->esClientePortal()) {
            $row->delete();

            return null;
        }

        if (strtolower((string) $user->email) !== $email) {
            return null;
        }

        $row->forceFill(['last_used_at' => now()])->save();

        return $user;
    }

    public function revocarTodos(User $user): void
    {
        WebLoginDispositivo::query()->where('usuario_id', $user->usuario_id)->delete();
    }
}
