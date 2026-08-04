<?php

namespace App\Services\Loyalty;

use App\Models\FacturaInterna;
use App\Models\Plan;
use App\Models\PlanUpsell;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use App\Models\User;
use App\Notifications\SolicitudCambioPlanNotification;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class UpsellService
{
    public function __construct(
        private readonly FacturacionService $facturacion,
        private readonly MikroTikService $mikrotik,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function catalogoPortal(): array
    {
        return PlanUpsell::publicados()
            ->with('plan')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->filter(fn (PlanUpsell $p) => $p->plan && ($p->plan->estado ?? '') === 'activo')
            ->map(fn (PlanUpsell $p) => $p->toPortalArray())
            ->values()
            ->all();
    }

    public function totalPendienteCliente(int $clienteId): float
    {
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();

        return (float) (FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->selectRaw('SUM('.$saldoExpr.') as total')
            ->value('total') ?? 0);
    }

    /**
     * @return array{aplicado: bool, tipo_cambio: string, mensaje: string, ticket_id?: int, servicio_id?: int, plan_id?: int}
     */
    public function solicitarCambioPlan(int $clienteId, int $servicioId, int $planId): array
    {
        $servicio = Servicio::with(['plan', 'cliente', 'pool.router'])
            ->where('servicio_id', $servicioId)
            ->where('cliente_id', $clienteId)
            ->first();

        if (! $servicio) {
            throw new \InvalidArgumentException('Servicio no encontrado.');
        }

        $planNuevo = Plan::find($planId);
        if (! $planNuevo || ($planNuevo->estado ?? '') !== 'activo') {
            throw new \InvalidArgumentException('Plan no disponible.');
        }

        $publicado = PlanUpsell::publicados()->where('plan_id', $planId)->exists();
        if (! $publicado) {
            throw new \InvalidArgumentException('El plan no está publicado para cambio desde la app.');
        }

        $planActual = $servicio->plan;
        if (! $planActual) {
            throw new \InvalidArgumentException('El servicio no tiene plan actual.');
        }

        if ((int) $planActual->plan_id === (int) $planNuevo->plan_id) {
            throw new \InvalidArgumentException('Ya tenés ese plan.');
        }

        $precioActual = (float) ($planActual->precio ?? 0);
        $precioNuevo = (float) ($planNuevo->precio ?? 0);
        $esUpgrade = $precioNuevo > $precioActual;
        $tipo = $esUpgrade ? 'upgrade' : 'downgrade';

        if ($esUpgrade) {
            $pendiente = $this->totalPendienteCliente($clienteId);
            if ($pendiente > 0.009) {
                throw new \InvalidArgumentException(
                    'Tenés saldo pendiente (Gs. '.number_format($pendiente, 0, ',', '.').'). Regularizá tu cuenta para subir de plan.'
                );
            }

            $this->aplicarUpgrade($servicio, $planActual, $planNuevo);

            return [
                'aplicado' => true,
                'tipo_cambio' => 'upgrade',
                'mensaje' => 'Plan actualizado correctamente',
                'servicio_id' => $servicio->servicio_id,
                'plan_id' => $planNuevo->plan_id,
            ];
        }

        $ticket = $this->crearTicketDowngrade($servicio, $planActual, $planNuevo);
        $this->avisarWhatsAppCliente($servicio, $planActual, $planNuevo, $ticket);
        $this->avisarStaff($servicio, $planActual, $planNuevo, $ticket);

        return [
            'aplicado' => false,
            'tipo_cambio' => 'downgrade',
            'ticket_id' => $ticket->id,
            'mensaje' => 'Solicitud creada. Te contactamos por WhatsApp.',
            'servicio_id' => $servicio->servicio_id,
            'plan_id' => $planNuevo->plan_id,
        ];
    }

    private function aplicarUpgrade(Servicio $servicio, Plan $planAnterior, Plan $planNuevo): void
    {
        DB::transaction(function () use ($servicio, $planAnterior, $planNuevo) {
            $servicio->update(['plan_id' => $planNuevo->plan_id]);
            $servicio->refresh();
            $servicio->load(['plan.perfilPppoe', 'pool.router', 'cliente']);

            try {
                $this->facturacion->registrarPostCambioPlanServicio(
                    $servicio,
                    $planAnterior,
                    null,
                    true
                );
            } catch (\Throwable $e) {
                Log::warning('[Upsell] Factura/ticket post cambio: '.$e->getMessage(), [
                    'servicio_id' => $servicio->servicio_id,
                ]);
            }
        });

        $servicio->refresh()->load(['plan.perfilPppoe', 'pool.router', 'cliente']);
        if ($servicio->usuario_pppoe && $servicio->pool?->router && $servicio->estaActivo()) {
            try {
                $sync = $this->mikrotik->syncPppoeServicio($servicio);
                if (! ($sync['success'] ?? false)) {
                    Log::warning('[Upsell] Sync MikroTik falló', [
                        'servicio_id' => $servicio->servicio_id,
                        'error' => $sync['error'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[Upsell] Sync MikroTik: '.$e->getMessage());
            }
        }
    }

    private function crearTicketDowngrade(Servicio $servicio, Plan $planActual, Plan $planNuevo): Ticket
    {
        $asuntoId = TicketAsunto::query()
            ->where(function ($q) {
                $q->where('nombre', 'like', '%cambio de plan%')
                    ->orWhere('nombre', 'like', '%Cambio de plan%');
            })
            ->value('id');

        if (! $asuntoId) {
            $asunto = TicketAsunto::firstOrCreate(['nombre' => 'Cambio de plan']);
            $asuntoId = $asunto->id;
        }

        return Ticket::create([
            'cliente_id' => $servicio->cliente_id,
            'ticket_asunto_id' => $asuntoId,
            'descripcion' => sprintf(
                "Solicitud de baja de plan desde la app.\nServicio #%d\nPlan actual: %s (Gs. %s)\nPlan solicitado: %s (Gs. %s)",
                $servicio->servicio_id,
                $planActual->nombre,
                number_format((float) $planActual->precio, 0, ',', '.'),
                $planNuevo->nombre,
                number_format((float) $planNuevo->precio, 0, ',', '.')
            ),
            'prioridad' => 'media',
            'estado' => 'pendiente',
            'reportado_desde' => 'app',
            'datos_diagnostico' => [
                'tipo' => 'solicitud_downgrade_plan',
                'servicio_id' => $servicio->servicio_id,
                'plan_actual_id' => $planActual->plan_id,
                'plan_solicitado_id' => $planNuevo->plan_id,
            ],
        ]);
    }

    private function avisarWhatsAppCliente(Servicio $servicio, Plan $planActual, Plan $planNuevo, Ticket $ticket): void
    {
        $tel = $servicio->cliente?->telefono;
        if (! $tel) {
            return;
        }

        $texto = sprintf(
            'Hola! Recibimos tu solicitud para cambiar del plan %s al plan %s (ticket #%d). Un asesor te contactará pronto.',
            $planActual->nombre,
            $planNuevo->nombre,
            $ticket->id
        );

        try {
            $this->whatsapp->sendText($tel, $texto, [
                'cliente_id' => $servicio->cliente_id,
                'ticket_id' => $ticket->id,
                'contexto_tipo' => 'solicitud_cambio_plan',
                'contexto_id' => $ticket->id,
            ]);
        } catch (\Throwable $e) {
            Log::info('[Upsell] WhatsApp cliente no enviado: '.$e->getMessage());
        }
    }

    private function avisarStaff(Servicio $servicio, Plan $planActual, Plan $planNuevo, Ticket $ticket): void
    {
        $staffIds = DB::table('upsell_staff_aviso')->pluck('usuario_id')->all();
        if ($staffIds === []) {
            return;
        }

        $users = User::query()
            ->whereIn('usuario_id', $staffIds)
            ->whereNull('cliente_id')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, new SolicitudCambioPlanNotification(
                $ticket,
                $servicio,
                $planActual,
                $planNuevo
            ));
        } catch (\Throwable $e) {
            Log::warning('[Upsell] Notificación staff: '.$e->getMessage());
        }
    }
}
