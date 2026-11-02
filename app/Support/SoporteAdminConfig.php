<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use App\Models\User;

class SoporteAdminConfig
{
    public static function usuarioId(): ?int
    {
        $id = (int) FacturacionParametro::obtener('wa_soporte_admin_usuario_id', 0);
        if ($id > 0) {
            return $id;
        }

        $fallback = config('whatsapp.inbound_ticket_asignado_id');

        return filled($fallback) ? (int) $fallback : null;
    }

    public static function usuario(): ?User
    {
        $id = self::usuarioId();
        if (! $id) {
            return null;
        }

        return User::query()->staff()->activos()->where('usuario_id', $id)->first();
    }

    public static function guardar(?int $usuarioId): void
    {
        FacturacionParametro::establecer(
            'wa_soporte_admin_usuario_id',
            $usuarioId ? (string) $usuarioId : '',
            'Staff que recibe el resumen técnico del soporte WhatsApp',
        );
    }
}
