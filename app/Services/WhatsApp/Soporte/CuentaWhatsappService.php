<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\HerramientasRedPayload;

/**
 * Estado de cuenta para el agente. No pasa por el modelo.
 */
class CuentaWhatsappService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function buscar(?string $telefono): ?array
    {
        $cliente = $this->whatsapp->findClienteByPhone($telefono);
        if (! $cliente) {
            return null;
        }

        return $this->desdeCliente($cliente, $telefono);
    }

    /**
     * Busca por teléfono, cédula/nro o nombre exacto en el mensaje.
     *
     * @return array<string, mixed>|null
     */
    public function buscarEnTexto(?string $texto): ?array
    {
        $tel = SoporteTexto::telefonoEnTexto($texto);
        if ($tel) {
            $porTel = $this->buscar($tel);
            if ($porTel) {
                return $porTel;
            }
        }

        $cedula = SoporteTexto::cedulaEnTexto($texto);
        if ($cedula) {
            $cliente = Cliente::buscarPorCedula($cedula);
            if ($cliente) {
                return $this->desdeCliente($cliente, $cliente->telefono);
            }
        }

        $nombre = SoporteTexto::nombreEnTexto($texto);
        if (! $nombre) {
            return null;
        }

        $cliente = Cliente::buscarPorNombreExacto($nombre);
        if (! $cliente) {
            return null;
        }

        return $this->desdeCliente($cliente, $cliente->telefono);
    }

    /**
     * @return array<string, mixed>
     */
    private function desdeCliente(Cliente $cliente, ?string $telefono): array
    {
        $cliente->load([
            'servicios.plan',
            'servicios.pool.router',
        ]);

        $servicio = $this->servicioPrincipal($cliente);
        $plan = $servicio?->plan;
        $saldos = $this->saldos((int) $cliente->cliente_id);
        $nombre = trim(trim((string) $cliente->nombre).' '.trim((string) $cliente->apellido));
        $wa = $this->whatsapp->normalizePhone($telefono)
            ?: $this->whatsapp->normalizePhone($cliente->telefono)
            ?: (string) ($telefono ?: $cliente->telefono);

        return [
            'cliente_id' => (int) $cliente->cliente_id,
            'nombre' => $nombre !== '' ? $nombre : (string) $cliente->nombre,
            'cedula' => FraseCliente::cedulaVisible($cliente->cedula),
            'estado' => $this->estado($servicio?->estado),
            'saldo' => $saldos['saldo'],
            'dias_mora' => $saldos['dias_mora'],
            'plan_id' => $plan?->plan_id ? (int) $plan->plan_id : null,
            'plan_nombre' => $plan?->nombre,
            'plan_precio' => $plan ? (float) $plan->precio : null,
            'servicio_id' => $servicio?->servicio_id ? (int) $servicio->servicio_id : null,
            'router_id' => $servicio?->pool?->router?->router_id ? (int) $servicio->pool->router->router_id : null,
            'ticket_abierto' => Ticket::query()
                ->where('cliente_id', $cliente->cliente_id)
                ->whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado'])
                ->exists(),
            'whatsapp' => $wa,
            'es_antena' => $servicio ? HerramientasRedPayload::esAntena($servicio) : false,
        ];
    }

    public function servicioDe(?int $servicioId): ?Servicio
    {
        if (! $servicioId) {
            return null;
        }

        return Servicio::query()
            ->with(['plan.tipoTecnologia', 'pool.router', 'cliente'])
            ->find($servicioId);
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
     * @return array{saldo: float, dias_mora: int}
     */
    private function saldos(int $clienteId): array
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

        $masAntigua = $vencimientos->first(fn ($fecha) => $fecha && $fecha->toDateString() < $hoy);
        $dias = 0;
        if ($masAntigua) {
            $dias = max(0, (int) $masAntigua->startOfDay()->diffInDays(now()->startOfDay()));
        }

        return [
            'saldo' => round($saldo, 0),
            'dias_mora' => $dias,
        ];
    }

    private function estado(?string $estado): ?string
    {
        return match ($estado) {
            Servicio::ESTADO_ACTIVO => 'activo',
            Servicio::ESTADO_SUSPENDIDO => 'suspendido',
            Servicio::ESTADO_CORTADO => 'cortado',
            Servicio::ESTADO_CANCELADO => 'cancelado',
            default => $estado,
        };
    }
}
