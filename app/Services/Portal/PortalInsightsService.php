<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;

class PortalInsightsService
{
    /**
     * Insights sin LLM (reglas mínimas del contrato app 3.2).
     *
     * @return array{title: string, subtitle: string|null, items: list<array<string, mixed>>, generated_at: string}
     */
    public function forCliente(Cliente $cliente): array
    {
        $items = [];
        $totalPendiente = $this->totalPendiente((int) $cliente->cliente_id);

        if ($totalPendiente > 0.009) {
            $items[] = [
                'id' => 'billing_due',
                'text' => 'Tenés saldo pendiente: Gs. '.number_format($totalPendiente, 0, ',', '.').'.',
                'severity' => 'warn',
                'action' => 'pay',
            ];
        }

        $servicios = Servicio::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->get(['servicio_id', 'estado']);

        $tieneInternet = $servicios->contains(
            fn (Servicio $s) => ($s->estado ?? '') === Servicio::ESTADO_ACTIVO
        );

        if ($servicios->isNotEmpty() && ! $tieneInternet) {
            $items[] = [
                'id' => 'service_down',
                'text' => 'Tu servicio de internet no está activo. Si necesitás ayuda, abrí Soporte.',
                'severity' => 'alert',
                'action' => 'support',
            ];
        }

        $ticketAbierto = Ticket::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', Ticket::estadosStaffCerrados())
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->first();

        if ($ticketAbierto) {
            $items[] = [
                'id' => 'ticket_open',
                'text' => 'Tenés un ticket de soporte abierto. Podés ver el estado en Soporte.',
                'severity' => 'info',
                'action' => 'support',
            ];
        }

        if ($items === []) {
            $items[] = [
                'id' => 'all_good',
                'text' => 'Estás al día. ¡Gracias por confiar en Interplus!',
                'severity' => 'ok',
                'action' => null,
            ];
        }

        // Sugerencia opcional Smart Check si no hay alerta crítica de servicio.
        if ($tieneInternet || $servicios->isEmpty()) {
            $items[] = [
                'id' => 'suggest_diag',
                'text' => 'Si notás lentitud, corré un Smart Check.',
                'severity' => 'info',
                'action' => 'smart_check',
            ];
        }

        return [
            'title' => 'Interplus IA',
            'subtitle' => null,
            'items' => $items,
            'generated_at' => now()->utc()->toIso8601String(),
        ];
    }

    private function totalPendiente(int $clienteId): float
    {
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();

        return (float) (FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->selectRaw('SUM('.$saldoExpr.') as total')
            ->value('total') ?? 0);
    }
}
