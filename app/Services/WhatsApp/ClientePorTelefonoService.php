<?php

namespace App\Services\WhatsApp;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;

/**
 * Resumen de cliente para el agente N8N (GET /api/v1/clientes/por-telefono).
 */
class ClientePorTelefonoService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppAgentService $agent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buscar(?string $telefono): array
    {
        $waId = $this->whatsapp->normalizePhone($telefono)
            ?? (preg_replace('/\D+/', '', (string) $telefono) ?: null);

        $cliente = $this->whatsapp->findClienteByPhone($telefono);
        $catalogo = $this->agent->catalogoPlanes();
        if (! $cliente) {
            $hilo = $this->agent->hiloParaTelefono($waId);

            return [
                'encontrado' => false,
                'whatsapp' => $waId,
                'hilo' => $hilo['historial'],
                'hilo_texto' => $hilo['historial_texto'],
                'planes_fibra' => $catalogo['fibra'],
                'planes_antena' => $catalogo['antena'],
                'planes_texto' => $catalogo['planes_texto'],
            ];
        }

        $cliente->load([
            'servicios.plan.tipoTecnologia',
            'servicios.pool.router.nodo',
            'servicios.cajaNapPuertoActivo.cajaNap.nodo',
        ]);

        $servicio = $this->servicioPrincipal($cliente);
        $plan = $servicio?->plan;
        $saldos = $this->saldosYVencimientos((int) $cliente->cliente_id);
        $nombre = trim(trim((string) $cliente->nombre).' '.trim((string) $cliente->apellido));
        $hilo = $this->agent->hiloParaTelefono($waId ?: $this->whatsapp->normalizePhone($cliente->telefono));
        $planInfo = $plan ? WhatsAppAgentService::serializarPlanWa($plan) : null;

        return [
            'encontrado' => true,
            'cliente_id' => (int) $cliente->cliente_id,
            'nombre' => $nombre !== '' ? $nombre : (string) $cliente->nombre,
            'nombre_corto' => $this->nombreCorto((string) $cliente->nombre),
            'plan' => $planInfo['nombre'] ?? $plan?->nombre,
            'plan_id' => $plan?->plan_id ? (int) $plan->plan_id : null,
            'plan_tecnologia' => $planInfo['tecnologia'] ?? null,
            'plan_grupo' => $planInfo['grupo'] ?? null,
            'saldo_pendiente' => $saldos['saldo_pendiente'],
            'estado_servicio' => $this->estadoServicioLabel($servicio?->estado),
            'dias_mora' => $saldos['dias_mora'],
            'proxima_factura_vencimiento' => $saldos['proxima_factura_vencimiento'],
            'whatsapp' => $waId ?: $this->whatsapp->normalizePhone($cliente->telefono),
            'email' => $cliente->email ? trim((string) $cliente->email) : null,
            'localidad' => $this->localidad($servicio),
            'cliente_desde' => $this->clienteDesde($cliente, $servicio),
            'tiene_ticket_abierto' => $this->tieneTicketAbierto((int) $cliente->cliente_id),
            'hilo' => $hilo['historial'],
            'hilo_texto' => $hilo['historial_texto'],
            'planes_fibra' => $catalogo['fibra'],
            'planes_antena' => $catalogo['antena'],
            'planes_texto' => $catalogo['planes_texto'],
        ];
    }

    private function servicioPrincipal(Cliente $cliente): ?Servicio
    {
        $prioridad = [
            Servicio::ESTADO_ACTIVO => 0,
            Servicio::ESTADO_SUSPENDIDO => 1,
            Servicio::ESTADO_CORTADO => 2,
            Servicio::ESTADO_CANCELADO => 3,
        ];

        return $cliente->servicios
            ->sortBy(fn (Servicio $s) => [($prioridad[$s->estado] ?? 9), -((int) $s->servicio_id)])
            ->first();
    }

    /**
     * @return array{saldo_pendiente: float, dias_mora: int, proxima_factura_vencimiento: string|null}
     */
    private function saldosYVencimientos(int $clienteId): array
    {
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $hoy = now()->toDateString();

        $saldo = (float) (FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->selectRaw('SUM('.$saldoExpr.') as total')
            ->value('total') ?? 0);

        $vencimientos = FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereNotNull('fecha_vencimiento')
            ->whereRaw($saldoExpr.' > 0.009')
            ->orderBy('fecha_vencimiento')
            ->pluck('fecha_vencimiento');

        $proxima = $vencimientos->first();
        $masAntiguaVencida = $vencimientos->first(
            fn ($fecha) => $fecha && $fecha->toDateString() < $hoy
        );

        $diasMora = 0;
        if ($masAntiguaVencida) {
            $diasMora = max(0, (int) $masAntiguaVencida->startOfDay()->diffInDays(now()->startOfDay()));
        }

        return [
            'saldo_pendiente' => round($saldo, 0),
            'dias_mora' => $diasMora,
            'proxima_factura_vencimiento' => $proxima?->toDateString(),
        ];
    }

    private function localidad(?Servicio $servicio): ?string
    {
        $nodo = $servicio?->pool?->router?->nodo
            ?? $servicio?->cajaNapPuertoActivo?->cajaNap?->nodo;
        $ciudad = trim((string) ($nodo?->ciudad ?? ''));

        return $ciudad !== '' ? $ciudad : null;
    }

    private function clienteDesde(Cliente $cliente, ?Servicio $servicio): ?string
    {
        if ($cliente->fecha_otorgamiento) {
            return $cliente->fecha_otorgamiento->toDateString();
        }

        return $servicio?->fecha_instalacion?->toDateString();
    }

    private function nombreCorto(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '';
        }

        return explode(' ', $nombre)[0];
    }

    private function estadoServicioLabel(?string $estado): ?string
    {
        return match ($estado) {
            Servicio::ESTADO_ACTIVO => 'activo',
            Servicio::ESTADO_SUSPENDIDO => 'suspendido',
            Servicio::ESTADO_CORTADO => 'cortado',
            Servicio::ESTADO_CANCELADO => 'cancelado',
            default => $estado,
        };
    }

    private function tieneTicketAbierto(int $clienteId): bool
    {
        return Ticket::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado'])
            ->exists();
    }
}
