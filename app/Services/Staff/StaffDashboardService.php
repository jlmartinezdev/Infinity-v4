<?php

namespace App\Services\Staff;

use App\Models\SolicitudAcceso;
use App\Models\StaffUbicacion;
use App\Models\User;

class StaffDashboardService
{
    public function __construct(
        private readonly StaffVisitaService $visitas,
        private readonly StaffPedidoInstalacionService $pedidos,
    ) {}

    /**
     * Counts filtrados por el usuario autenticado.
     *
     * @return array{
     *   solicitudes_pendientes: int,
     *   pedidos_instalacion: int,
     *   visitas: int,
     *   tecnicos_online: int
     * }
     */
    public function counts(User $user): array
    {
        $solicitudes = SolicitudAcceso::query()
            ->where('estado', SolicitudAcceso::ESTADO_PENDIENTE)
            ->count();

        $pedidos = $this->pedidos->listar($user, ['estado_id' => 3])->count();
        $visitas = $this->visitas->listarPara($user)->count();

        $tecnicosOnline = (int) StaffUbicacion::query()
            ->where('reported_at', '>=', now()->subMinutes(5))
            ->distinct()
            ->count('usuario_id');

        return [
            'solicitudes_pendientes' => $solicitudes,
            'pedidos_instalacion' => $pedidos,
            'visitas' => $visitas,
            'tecnicos_online' => $tecnicosOnline,
        ];
    }
}
