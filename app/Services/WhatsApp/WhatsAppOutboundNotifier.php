<?php

namespace App\Services\WhatsApp;

use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TvCuenta;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Avisos WhatsApp salientes organizados (Infinity → Meta).
 * No atiende chats entrantes: eso lo hace la IA de WhatsApp Business.
 */
class WhatsAppOutboundNotifier
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function eventEnabled(string $event): bool
    {
        return (bool) config("whatsapp.events.{$event}", false)
            && $this->whatsapp->isConfigured();
    }

    /**
     * Avisa al técnico cuando se le asigna un ticket.
     */
    public function ticketAsignado(Ticket $ticket): void
    {
        if (! $this->eventEnabled('ticket_asignado')) {
            return;
        }

        if (! $ticket->asignado_id) {
            return;
        }

        $ticket->loadMissing(['asignado', 'cliente', 'ticketAsunto']);
        $tecnico = $ticket->asignado;
        if (! $tecnico instanceof User) {
            return;
        }

        $telefono = $this->telefonoStaff($tecnico);
        if (! $telefono) {
            Log::info('[WhatsApp outbound] ticket_asignado sin teléfono de técnico', [
                'ticket_id' => $ticket->id,
                'asignado_id' => $ticket->asignado_id,
            ]);

            return;
        }

        $cliente = $ticket->cliente
            ? trim(($ticket->cliente->nombre ?? '').' '.($ticket->cliente->apellido ?? ''))
            : 'Sin cliente';
        $asunto = $ticket->ticketAsunto?->nombre ?? 'Ticket';
        $texto = sprintf(
            "Ticket #%d asignado\nAsunto: %s\nCliente: %s\nPrioridad: %s\nEstado: %s",
            $ticket->id,
            $asunto,
            $cliente !== '' ? $cliente : 'Sin cliente',
            $ticket->prioridad ?? '-',
            $ticket->estado ?? '-'
        );

        $this->enviar(
            event: 'ticket_asignado',
            telefono: $telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => (string) $ticket->id],
                ['type' => 'text', 'text' => $asunto],
                ['type' => 'text', 'text' => $cliente !== '' ? $cliente : 'Sin cliente'],
                ['type' => 'text', 'text' => (string) ($ticket->prioridad ?? '-')],
            ],
            meta: [
                'ticket_id' => $ticket->id,
                'cliente_id' => $ticket->cliente_id,
                'contexto_tipo' => 'ticket_asignado',
                'contexto_id' => $ticket->id,
            ]
        );
    }

    /**
     * Avisa al cliente con detalle de factura interna.
     */
    public function facturaGenerada(FacturaInterna $factura): void
    {
        if (! $this->eventEnabled('factura')) {
            return;
        }

        $factura->loadMissing(['cliente', 'detalles']);
        $cliente = $factura->cliente;
        if (! $cliente || ! filled($cliente->telefono)) {
            Log::info('[WhatsApp outbound] factura sin teléfono de cliente', [
                'factura_id' => $factura->id,
                'cliente_id' => $factura->cliente_id,
            ]);

            return;
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $periodo = trim(
            ($factura->periodo_desde?->format('d/m/Y') ?? '-').
            ' - '.
            ($factura->periodo_hasta?->format('d/m/Y') ?? '-')
        );
        $total = number_format((float) $factura->total, 0, ',', '.');
        $vencimiento = $factura->fecha_vencimiento?->format('d/m/Y') ?? '-';
        $detalleLineas = $factura->detalles
            ? $factura->detalles->take(5)->map(function ($d) {
                $desc = (string) ($d->descripcion ?? $d->concepto ?? 'Ítem');
                $monto = number_format((float) ($d->total ?? $d->subtotal ?? 0), 0, ',', '.');

                return "• {$desc}: Gs. {$monto}";
            })->implode("\n")
            : '';

        $saludo = $nombre !== '' ? $nombre : 'cliente';
        $texto = sprintf(
            "Hola %s, te informamos que Interplus generó tu factura #%d correspondiente al periodo %s. El monto total a abonar es de Gs. %s y la fecha de vencimiento es %s.",
            $saludo,
            $factura->id,
            $periodo,
            $total,
            $vencimiento
        );
        if ($detalleLineas !== '') {
            $texto .= "\n".$detalleLineas;
        }

        $this->enviar(
            event: 'factura',
            telefono: (string) $cliente->telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $saludo],
                ['type' => 'text', 'text' => (string) $factura->id],
                ['type' => 'text', 'text' => $periodo],
                ['type' => 'text', 'text' => $total],
                ['type' => 'text', 'text' => $vencimiento],
            ],
            meta: [
                'cliente_id' => $cliente->cliente_id,
                'contexto_tipo' => 'factura',
                'contexto_id' => $factura->id,
            ]
        );
    }

    /**
     * Avisa a staff seleccionados sobre vencimiento de una cuenta TV streaming.
     *
     * @param  Collection<int, User>  $destinatarios
     */
    public function tvVencimiento(
        TvCuenta $cuenta,
        Collection $destinatarios,
        int $dias,
        Carbon $vencimiento,
    ): bool {
        if (! $this->whatsapp->isConfigured()) {
            return false;
        }

        $app = TvCuenta::aplicaciones()[$cuenta->aplicacion ?? TvCuenta::APP_NEBULA] ?? ($cuenta->aplicacion ?? 'TV');
        $estado = $dias < 0
            ? ('vencida hace '.abs($dias).' día'.(abs($dias) === 1 ? '' : 's'))
            : ($dias === 0 ? 'vence HOY' : ('vence en '.$dias.' día'.($dias === 1 ? '' : 's')));

        $texto = sprintf(
            "Aviso TV streaming\nCuenta: %s\nApp: %s\nUsuario: %s\nVencimiento: %s (%s)\nRevisá el panel de Cuentas TV.",
            $cuenta->nombre ?: ('#'.$cuenta->id),
            $app,
            $cuenta->usuario_app ?: '-',
            $vencimiento->format('d/m/Y'),
            $estado
        );

        $enviados = 0;
        foreach ($destinatarios as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $telefono = $this->telefonoStaff($user);
            if (! $telefono) {
                Log::info('[WhatsApp outbound] tv_vencimiento sin teléfono', [
                    'usuario_id' => $user->usuario_id,
                    'tv_cuenta_id' => $cuenta->id,
                ]);

                continue;
            }

            $this->enviar(
                event: 'tv_vencimiento',
                telefono: $telefono,
                texto: $texto,
                templateParams: [
                    ['type' => 'text', 'text' => $cuenta->nombre ?: ('#'.$cuenta->id)],
                    ['type' => 'text', 'text' => $cuenta->usuario_app ?: '-'],
                    ['type' => 'text', 'text' => $vencimiento->format('d/m/Y')],
                    ['type' => 'text', 'text' => $estado],
                ],
                meta: [
                    'contexto_tipo' => 'tv_vencimiento',
                    'contexto_id' => $cuenta->id,
                ]
            );
            $enviados++;
        }

        return $enviados > 0;
    }

    /**
     * Avisa al cliente que su enlace/servicio cayó (PPPoE down).
     */
    public function enlaceCaido(Servicio $servicio): void
    {
        if (! $this->eventEnabled('enlace_caido')) {
            return;
        }

        $servicio->loadMissing(['cliente']);
        $cliente = $servicio->cliente;
        if (! $cliente || ! filled($cliente->telefono)) {
            Log::info('[WhatsApp outbound] enlace_caido sin teléfono de cliente', [
                'servicio_id' => $servicio->servicio_id,
            ]);

            return;
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $servicioLabel = $servicio->usuario_pppoe
            ?: ('Servicio #'.$servicio->servicio_id);
        $texto = sprintf(
            "Aviso de conexión\nHola %s, detectamos que tu enlace (%s) está desconectado. Ya estamos al tanto; si se restablece solo, no hace falta que hagas nada.",
            $nombre !== '' ? $nombre : 'cliente',
            $servicioLabel
        );

        $this->enviar(
            event: 'enlace_caido',
            telefono: (string) $cliente->telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $nombre !== '' ? $nombre : 'cliente'],
                ['type' => 'text', 'text' => $servicioLabel],
            ],
            meta: [
                'cliente_id' => $cliente->cliente_id,
                'contexto_tipo' => 'enlace_caido',
                'contexto_id' => $servicio->servicio_id,
            ]
        );
    }

    /**
     * @param  list<array{type: string, text?: string}>  $templateParams
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     */
    private function enviar(
        string $event,
        string $telefono,
        string $texto,
        array $templateParams,
        array $meta,
    ): void {
        $template = trim((string) config("whatsapp.templates.{$event}", ''));

        try {
            if ($template !== '') {
                $mensaje = $this->whatsapp->sendTemplate(
                    $telefono,
                    $template,
                    (string) config('whatsapp.default_template_language', 'es'),
                    $templateParams,
                    $meta
                );

                if ($mensaje->estado !== 'fallido') {
                    return;
                }

                // Plantilla PENDING/rechazada → texto libre (sirve si hay ventana 24h).
                Log::warning('[WhatsApp outbound] Plantilla falló, reintento con texto', [
                    'event' => $event,
                    'template' => $template,
                    'error' => $mensaje->error_message,
                    'meta' => $meta,
                ]);
            }

            $this->whatsapp->sendText($telefono, $texto, $meta);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp outbound] Error enviando '.$event.': '.$e->getMessage(), [
                'exception' => $e,
                'meta' => $meta,
            ]);
        }
    }

    private function telefonoStaff(User $user): ?string
    {
        $fromUser = $user->telefono ?? null;
        if (filled($fromUser)) {
            return (string) $fromUser;
        }

        // Mapa opcional: WHATSAPP_STAFF_PHONES=3:0981...,5:0971...
        $map = (string) config('whatsapp.staff_phones', '');
        if ($map === '') {
            return null;
        }

        foreach (explode(',', $map) as $pair) {
            $parts = array_map('trim', explode(':', $pair, 2));
            if (count($parts) === 2 && (int) $parts[0] === (int) $user->usuario_id && $parts[1] !== '') {
                return $parts[1];
            }
        }

        return null;
    }
}
