<?php

namespace App\Services\WhatsApp;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\NodoApWireless;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TvCuenta;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Support\FacturaReclamoMensaje;
use App\Support\PendientesResumenPublico;
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
     * Avisa al cliente cuando su ticket queda resuelto.
     *
     * Plantilla Meta ticket_resuelto (ej.):
     * Hola {{1}}, tu solicitud #{{2}} ({{3}}) fue resuelta.
     * Si necesitás ayuda, respondé este mensaje o contactanos.
     *
     * Ver docs/whatsapp-plantilla-ticket-resuelto.md
     */
    public function ticketResuelto(Ticket $ticket): void
    {
        if (! $this->eventEnabled('ticket_resuelto')) {
            return;
        }

        $ticket->loadMissing(['cliente', 'ticketAsunto']);
        $cliente = $ticket->cliente;
        if (! $cliente || ! filled($cliente->telefono)) {
            Log::info('[WhatsApp outbound] ticket_resuelto sin teléfono de cliente', [
                'ticket_id' => $ticket->id,
                'cliente_id' => $ticket->cliente_id,
            ]);

            return;
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $asunto = $ticket->ticketAsunto?->nombre ?? 'Soporte';
        $texto = sprintf(
            "Hola %s, tu solicitud #%d (%s) fue resuelta.\nSi necesitás ayuda, respondé este mensaje o contactanos.",
            $nombre !== '' ? $nombre : 'cliente',
            $ticket->id,
            $asunto
        );

        $this->enviar(
            event: 'ticket_resuelto',
            telefono: (string) $cliente->telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $nombre !== '' ? $nombre : 'cliente'],
                ['type' => 'text', 'text' => (string) $ticket->id],
                ['type' => 'text', 'text' => $asunto],
            ],
            meta: [
                'ticket_id' => $ticket->id,
                'cliente_id' => $ticket->cliente_id,
                'contexto_tipo' => 'ticket_resuelto',
                'contexto_id' => $ticket->id,
            ]
        );
    }

    /**
     * Avisa al cliente que su servicio fue suspendido por falta de pago.
     *
     * Plantilla Meta servicio_suspendido_falta_pago (ej.):
     * Hola {{1}}, te informamos que tu servicio de internet fue suspendido por falta de pago.
     * Factura: #{{2}} / Saldo pendiente: Gs. {{3}} / Vencimiento: {{4}}
     * Regularizá tu pago para reactivar el servicio.
     *
     * Ver docs/whatsapp-plantilla-servicio-suspendido.md
     */
    public function servicioSuspendidoPorFaltaPago(Servicio $servicio, ?FacturaInterna $factura = null): bool
    {
        if (! $this->eventEnabled('servicio_suspendido')) {
            return false;
        }

        $servicio->loadMissing(['cliente']);
        $cliente = $servicio->cliente;
        if (! $cliente || ! filled($cliente->telefono)) {
            Log::info('[WhatsApp outbound] servicio_suspendido sin teléfono de cliente', [
                'servicio_id' => $servicio->servicio_id,
                'cliente_id' => $servicio->cliente_id,
            ]);

            return false;
        }

        if ($factura === null) {
            $factura = FacturaInterna::query()
                ->where('cliente_id', $cliente->cliente_id)
                ->whereIn('estado', ['pendiente', 'emitida'])
                ->where('fecha_vencimiento', '<', now()->toDateString())
                ->orderBy('fecha_vencimiento')
                ->get()
                ->first(fn (FacturaInterna $f) => $f->saldo_pendiente > 0);
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $saludo = $nombre !== '' ? $nombre : 'cliente';
        $facturaRef = $factura ? (string) $factura->id : 'pendiente';
        $saldo = $factura
            ? number_format((float) $factura->saldo_pendiente, 0, ',', '.')
            : '-';
        $vencimiento = $factura?->fecha_vencimiento?->format('d/m/Y') ?? '-';

        $texto = sprintf(
            "Hola %s, te informamos que tu servicio de internet fue suspendido por falta de pago.\nFactura: #%s\nSaldo pendiente: Gs. %s\nVencimiento: %s\n\nRegularizá tu pago para reactivar el servicio. Ante dudas, respondé este mensaje o contactanos.",
            $saludo,
            $facturaRef,
            $saldo,
            $vencimiento
        );

        return $this->enviar(
            event: 'servicio_suspendido',
            telefono: (string) $cliente->telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $saludo],
                ['type' => 'text', 'text' => $facturaRef],
                ['type' => 'text', 'text' => $saldo],
                ['type' => 'text', 'text' => $vencimiento],
            ],
            meta: [
                'cliente_id' => $cliente->cliente_id,
                'contexto_tipo' => 'servicio_suspendido',
                'contexto_id' => $servicio->servicio_id,
            ]
        );
    }

    /**
     * Un aviso por cliente a partir del listado de suspensiones del corte.
     *
     * @param  list<array{servicio_id: int, cliente_id: int, factura_id?: int|null}>  $suspendidos
     * @return int Cantidad de avisos intentados/enviados OK
     */
    public function avisarSuspensionesPorFaltaPago(array $suspendidos): int
    {
        if (! $this->eventEnabled('servicio_suspendido') || $suspendidos === []) {
            return 0;
        }

        $enviados = 0;
        $vistos = [];

        foreach ($suspendidos as $item) {
            $clienteId = (int) ($item['cliente_id'] ?? 0);
            $servicioId = (int) ($item['servicio_id'] ?? 0);
            if ($clienteId <= 0 || $servicioId <= 0 || isset($vistos[$clienteId])) {
                continue;
            }
            $vistos[$clienteId] = true;

            $servicio = Servicio::query()->with('cliente')->find($servicioId);
            if (! $servicio) {
                continue;
            }

            $factura = null;
            if (! empty($item['factura_id'])) {
                $factura = FacturaInterna::query()->find((int) $item['factura_id']);
            }

            if ($this->servicioSuspendidoPorFaltaPago($servicio, $factura)) {
                $enviados++;
            }
        }

        return $enviados;
    }

    /**
     * Avisa al cliente con detalle de factura interna.
     *
     * Plantilla Meta factura_generada_cliente (ej.):
     * Hola {{1}}, Interplus generó tu factura #{{2}} correspondiente a {{3}}.
     * Monto: Gs. {{4}} / Vencimiento: {{5}}
     *
     * Para servicio especial, {{3}} es la descripción del ítem (no un período).
     *
     * @return array{ok: bool, message: string}
     */
    public function facturaGenerada(FacturaInterna $factura, bool $forzar = false, ?string $telefonoOverride = null): array
    {
        if (! $forzar && ! $this->eventEnabled('factura')) {
            return ['ok' => false, 'message' => 'El aviso automático de factura está desactivado.'];
        }

        if (! $this->whatsapp->isConfigured()) {
            return ['ok' => false, 'message' => 'WhatsApp no está configurado.'];
        }

        $factura->loadMissing(['cliente', 'detalles']);
        $cliente = $factura->cliente;
        if (! $cliente) {
            return ['ok' => false, 'message' => 'La factura no tiene cliente asociado.'];
        }

        $telefono = filled($telefonoOverride)
            ? trim((string) $telefonoOverride)
            : (string) ($cliente->telefono ?? '');

        if ($telefono === '') {
            Log::info('[WhatsApp outbound] factura sin teléfono de cliente', [
                'factura_id' => $factura->id,
                'cliente_id' => $factura->cliente_id,
            ]);

            return ['ok' => false, 'message' => 'El cliente no tiene teléfono. Indicá un número para enviar.'];
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $esEspecial = $factura->esServicioEspecial();
        $concepto = $this->conceptoFacturaParaWhatsApp($factura);
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
        if ($esEspecial) {
            $texto = sprintf(
                "Hola %s, Interplus ha generado Factura por %s. Monto: Gs. %s.",
                $saludo,
                $concepto,
                $total
            );
        } else {
            $texto = sprintf(
                "Hola %s, te informamos que Interplus generó tu factura #%d correspondiente al periodo %s. El monto total a abonar es de Gs. %s y la fecha de vencimiento es %s.",
                $saludo,
                $factura->id,
                $concepto,
                $total,
                $vencimiento
            );
        }
        if ($detalleLineas !== '' && ! $esEspecial) {
            $texto .= "\n".$detalleLineas;
        }

        $ok = $this->enviar(
            event: 'factura',
            telefono: $telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $saludo],
                ['type' => 'text', 'text' => (string) $factura->id],
                ['type' => 'text', 'text' => $concepto],
                ['type' => 'text', 'text' => $total],
                ['type' => 'text', 'text' => $vencimiento],
            ],
            meta: [
                'cliente_id' => $cliente->cliente_id,
                'contexto_tipo' => 'factura',
                'contexto_id' => $factura->id,
            ]
        );

        $telNorm = $this->whatsapp->normalizePhone($telefono) ?? $telefono;
        $fallo = $this->ultimoFallo('factura', (int) $factura->id, (int) $cliente->cliente_id);

        return $ok
            ? ['ok' => true, 'message' => 'Aviso de factura enviado por WhatsApp a '.$telNorm.'.']
            : ['ok' => false, 'message' => $this->mensajeFallo('No se pudo enviar el aviso de factura a '.$telNorm.'.', $fallo), 'fallo' => $fallo];
    }

    /**
     * Reclamo de mora (facturas vencidas) con link al PDF resumen.
     *
     * @param  Collection<int, FacturaInterna>  $facturas
     * @return array{ok: bool, message: string, ya_enviado?: bool, fallo?: array<string, mixed>|null}
     */
    public function facturaReclamo(
        Cliente $cliente,
        Collection $facturas,
        bool $adjuntarResumen = true,
        bool $forzarDia = false,
        ?string $telefonoOverride = null,
    ): array {
        if (! $this->whatsapp->isConfigured()) {
            return ['ok' => false, 'message' => 'WhatsApp no está configurado.'];
        }

        $telefono = filled($telefonoOverride)
            ? trim((string) $telefonoOverride)
            : (string) ($cliente->telefono ?? '');

        if ($telefono === '') {
            return ['ok' => false, 'message' => 'Indicá un teléfono o cargá el del cliente.'];
        }

        $vencidas = $facturas->filter(function (FacturaInterna $f) {
            return $f->fecha_vencimiento && $f->fecha_vencimiento->lt(now()->startOfDay());
        })->values();

        if ($vencidas->isEmpty()) {
            return ['ok' => false, 'message' => 'Este cliente no tiene facturas vencidas para reclamar.'];
        }

        if (! $forzarDia) {
            $yaHoy = WhatsappMensaje::query()
                ->where('contexto_tipo', 'factura_reclamo')
                ->where('cliente_id', $cliente->cliente_id)
                ->where('created_at', '>=', now()->startOfDay())
                ->whereIn('estado', [
                    WhatsappMensaje::ESTADO_PENDIENTE,
                    WhatsappMensaje::ESTADO_ENVIADO,
                    WhatsappMensaje::ESTADO_ENTREGADO,
                    WhatsappMensaje::ESTADO_LEIDO,
                ])
                ->exists();
            if ($yaHoy) {
                return [
                    'ok' => false,
                    'ya_enviado' => true,
                    'message' => 'Ya se envió un reclamo a este cliente hoy. ¿Reenviar de todos modos?',
                ];
            }
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $saludo = $nombre !== '' ? mb_substr($nombre, 0, 60) : 'cliente';
        $primera = $vencidas->first();
        $cantidad = $vencidas->count();
        $cantidadLabel = (string) $cantidad;
        $vencimiento = $primera->fecha_vencimiento?->format('d/m/Y') ?? '-';
        $vencimientoLabel = $vencimiento.' (vencido)';
        $saldo = (float) $vencidas->sum(fn (FacturaInterna $f) => (float) $f->saldo_pendiente);
        $saldoFmt = number_format($saldo, 0, ',', '.');

        $urlPublica = PendientesResumenPublico::url((int) $cliente->cliente_id);
        $sufijoBoton = PendientesResumenPublico::sufijo((int) $cliente->cliente_id);

        $texto = FacturaReclamoMensaje::cuerpo(
            $saludo,
            $cantidad,
            $vencimiento,
            $saldoFmt,
            $adjuntarResumen ? $urlPublica : null,
        );

        $plantilla = trim((string) config('whatsapp.templates.factura_reclamo', ''));

        $ok = $this->enviar(
            event: 'factura_reclamo',
            telefono: $telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $saludo],
                ['type' => 'text', 'text' => $cantidadLabel],
                ['type' => 'text', 'text' => $vencimientoLabel],
                ['type' => 'text', 'text' => $saldoFmt],
            ],
            meta: [
                'cliente_id' => $cliente->cliente_id,
                'contexto_tipo' => 'factura_reclamo',
                'contexto_id' => (int) $primera->id,
            ],
            urlButtonParameters: $adjuntarResumen
                ? [['type' => 'text', 'text' => $sufijoBoton]]
                : [],
            fallbackTexto: true,
        );

        $telNorm = $this->whatsapp->normalizePhone($telefono) ?? $telefono;
        $fallo = $this->ultimoFallo('factura_reclamo', (int) $primera->id, (int) $cliente->cliente_id);

        if ($ok) {
            $msg = 'Reclamo enviado por WhatsApp a '.$telNorm.'.';
            if ($plantilla === '') {
                $msg .= ' Fue texto libre (no hay plantilla Meta). Si no llega al teléfono, el número está fuera de la ventana de 24 h: hay que crear y aprobar factura_reclamo_mora.';
            }

            return ['ok' => true, 'message' => $msg, 'sin_plantilla' => $plantilla === ''];
        }

        $base = 'No se pudo enviar el reclamo a '.$telNorm.'.';
        if ($plantilla === '') {
            $base .= ' No hay plantilla Meta de reclamo (WHATSAPP_TEMPLATE_FACTURA_RECLAMO). El texto libre no llega si el destinatario no escribió en las últimas 24 h.';
        }

        return [
            'ok' => false,
            'message' => $this->mensajeFallo($base, $fallo),
            'fallo' => $fallo,
            'sin_plantilla' => $plantilla === '',
        ];
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
        bool $esPrueba = false,
    ): bool {
        if (! $this->whatsapp->isConfigured()) {
            return false;
        }

        $app = TvCuenta::aplicaciones()[$cuenta->aplicacion ?? TvCuenta::APP_NEBULA] ?? ($cuenta->aplicacion ?? 'TV');
        $estado = $dias < 0
            ? ('vencida hace '.abs($dias).' día'.(abs($dias) === 1 ? '' : 's'))
            : ($dias === 0 ? 'vence HOY' : ('vence en '.$dias.' día'.($dias === 1 ? '' : 's')));

        $texto = sprintf(
            "%sAviso TV streaming\nCuenta: %s\nApp: %s\nUsuario: %s\nVencimiento: %s (%s)\nRevisá el panel de Cuentas TV.",
            $esPrueba ? "[PRUEBA]\n" : '',
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

            $okEnvio = $this->enviar(
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
            if ($okEnvio) {
                $enviados++;
            }
        }

        return $enviados > 0;
    }

    /**
     * Avisa a staff seleccionados que un router no responde ping.
     * Ver docs/whatsapp-plantilla-router-caido.md
     *
     * @param  Collection<int, User>  $destinatarios
     */
    public function routerCaido(Router $router, Collection $destinatarios, bool $esPrueba = false): bool
    {
        if (! $this->whatsapp->isConfigured()) {
            return false;
        }

        // El panel (RouterCaidaAvisoConfig) controla el envío automático;
        // el flag de evento permite desactivar globalmente vía .env.
        if (! $esPrueba && ! (bool) config('whatsapp.events.router_caido', true)) {
            return false;
        }

        $nombre = trim((string) ($router->nombre ?: ('#'.$router->router_id)));
        $ip = trim((string) ($router->ip ?: '-'));
        $fallos = (int) ($router->ping_fallos_seguidos ?? 0);
        $cuando = $router->ping_at
            ? $router->ping_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
            : now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s');

        $texto = sprintf(
            "%sAlerta de red\nRouter: %s\nIP: %s\nEstado: sin respuesta al ping\nFallos seguidos: %d\nÚltimo chequeo: %s\nRevisá Monitoreo de red en el panel.",
            $esPrueba ? "[PRUEBA]\n" : '',
            $nombre,
            $ip,
            $fallos,
            $cuando
        );

        $enviados = 0;
        foreach ($destinatarios as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $telefono = $this->telefonoStaff($user);
            if (! $telefono) {
                Log::info('[WhatsApp outbound] router_caido sin teléfono', [
                    'usuario_id' => $user->usuario_id,
                    'router_id' => $router->router_id,
                ]);

                continue;
            }

            $this->enviar(
                event: 'router_caido',
                telefono: $telefono,
                texto: $texto,
                templateParams: [
                    ['type' => 'text', 'text' => $nombre],
                    ['type' => 'text', 'text' => $ip],
                    ['type' => 'text', 'text' => (string) max(1, $fallos)],
                    ['type' => 'text', 'text' => $cuando],
                ],
                meta: [
                    'contexto_tipo' => 'router_caido',
                    'contexto_id' => $router->router_id,
                ]
            );
            $enviados++;
        }

        return $enviados > 0;
    }

    /**
     * Avisa a staff que un AP wireless (airOS) no responde ping.
     *
     * @param  Collection<int, User>  $destinatarios
     */
    public function apWirelessCaido(NodoApWireless $ap, Collection $destinatarios, bool $esPrueba = false): bool
    {
        if (! $this->whatsapp->isConfigured()) {
            return false;
        }

        if (! $esPrueba && ! (bool) config('whatsapp.events.ap_wireless_caido', true)) {
            return false;
        }

        $ap->loadMissing('nodo');
        $nodo = trim((string) ($ap->nodo?->descripcion ?: ''));
        $nombreAp = trim((string) ($ap->nombre ?: ('#'.$ap->ap_id)));
        $nombre = $nodo !== '' ? "AP {$nombreAp} ({$nodo})" : "AP {$nombreAp}";
        $ip = trim((string) ($ap->ip ?: '-'));
        $fallos = (int) ($ap->ping_fallos_seguidos ?? 0);
        $cuando = $ap->ping_at
            ? $ap->ping_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
            : now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s');

        $texto = sprintf(
            "%sAlerta de red\nAP wireless: %s\nIP: %s\nSSID: %s\nEstado: sin respuesta al ping\nFallos seguidos: %d\nÚltimo chequeo: %s\nRevisá APs wireless en el panel.",
            $esPrueba ? "[PRUEBA]\n" : '',
            $nombre,
            $ip,
            trim((string) ($ap->ssid ?: '-')),
            $fallos,
            $cuando
        );

        $enviados = 0;
        foreach ($destinatarios as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $telefono = $this->telefonoStaff($user);
            if (! $telefono) {
                Log::info('[WhatsApp outbound] ap_wireless_caido sin teléfono', [
                    'usuario_id' => $user->usuario_id,
                    'ap_id' => $ap->ap_id,
                ]);

                continue;
            }

            $this->enviar(
                event: 'ap_wireless_caido',
                telefono: $telefono,
                texto: $texto,
                templateParams: [
                    ['type' => 'text', 'text' => $nombre],
                    ['type' => 'text', 'text' => $ip],
                    ['type' => 'text', 'text' => (string) max(1, $fallos)],
                    ['type' => 'text', 'text' => $cuando],
                ],
                meta: [
                    'contexto_tipo' => 'ap_wireless_caido',
                    'contexto_id' => $ap->ap_id,
                ]
            );
            $enviados++;
        }

        return $enviados > 0;
    }

    /**
     * Aviso al staff: salida ISP 1 caída (failover) o recuperada (failback).
     *
     * @param  Collection<int, User>  $destinatarios
     */
    public function ispFailover(
        string $titulo,
        string $pingHost,
        string $routerNombre,
        Collection $destinatarios,
        bool $esPrueba = false
    ): bool {
        if (! $this->whatsapp->isConfigured()) {
            return false;
        }

        if (! $esPrueba && ! (bool) config('whatsapp.events.isp_failover', true)) {
            return false;
        }

        $cuando = now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s');
        $host = trim($pingHost) !== '' ? trim($pingHost) : '1.1.1.1';
        $routerNombre = trim($routerNombre) !== '' ? trim($routerNombre) : 'router borde';
        $titulo = trim($titulo) !== '' ? trim($titulo) : 'Cambio de salida ISP';

        $texto = sprintf(
            "%sAlerta de salida ISP\n%s\nPing: %s\nRouter: %s\nHora: %s\nRevisá Failover ISP en Infinity.",
            $esPrueba ? "[PRUEBA]\n" : '',
            $titulo,
            $host,
            $routerNombre,
            $cuando
        );

        $enviados = 0;
        foreach ($destinatarios as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $telefono = $this->telefonoStaff($user);
            if (! $telefono) {
                Log::info('[WhatsApp outbound] isp_failover sin teléfono', [
                    'usuario_id' => $user->usuario_id,
                ]);

                continue;
            }

            $this->enviar(
                event: 'isp_failover',
                telefono: $telefono,
                texto: $texto,
                templateParams: [
                    ['type' => 'text', 'text' => ($esPrueba ? '[PRUEBA] ' : '').$titulo],
                    ['type' => 'text', 'text' => $host],
                    ['type' => 'text', 'text' => $routerNombre],
                    ['type' => 'text', 'text' => $cuando],
                ],
                meta: [
                    'contexto_tipo' => 'isp_failover',
                    'contexto_id' => 0,
                ]
            );
            $enviados++;
        }

        return $enviados > 0;
    }

    /**
     * Envía recibo de pago de factura/cobro al WhatsApp del cliente.
     *
     * Plantilla Meta (header: "Pago Recibido", botón URL "Descargar Recibo"):
     * Hola {{1}}, desde interplus confirmamos la recepción de tu pago.
     * Recibo: {{2}} / Monto: Gs. {{3}} / Fecha: {{4}} / Forma de pago: {{5}} / Detalle: {{6}}
     * ¡Gracias por su pago!
     * Botón URL base: {APP_URL}/recibo/{{1}}  → se envía sufijo "{id}/{token}"
     *
     * @param  bool  $forzar  Si true, envía aunque WHATSAPP_EVENT_RECIBO=false (botón manual).
     * @param  string|null  $telefonoOverride  Si se indica, se usa en lugar del teléfono del cliente.
     * @return array{ok: bool, message: string}
     */
    public function reciboPago(Cobro $cobro, bool $forzar = false, ?string $telefonoOverride = null): array
    {
        if (! $forzar && ! $this->eventEnabled('recibo')) {
            return ['ok' => false, 'message' => 'Envío automático de recibo desactivado (WHATSAPP_EVENT_RECIBO).'];
        }

        if (! $this->whatsapp->isConfigured()) {
            return ['ok' => false, 'message' => 'WhatsApp no está configurado.'];
        }

        $cobro->loadMissing(['cliente', 'facturaInternas']);
        $cliente = $cobro->cliente;
        if (! $cliente) {
            return ['ok' => false, 'message' => 'El cobro no tiene cliente asociado.'];
        }

        $telefono = filled($telefonoOverride)
            ? trim((string) $telefonoOverride)
            : (string) ($cliente->telefono ?? '');

        if ($telefono === '') {
            Log::info('[WhatsApp outbound] recibo sin teléfono', [
                'cobro_id' => $cobro->id,
                'cliente_id' => $cobro->cliente_id,
            ]);

            return ['ok' => false, 'message' => 'Indicá un teléfono o cargá el del cliente.'];
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $saludo = $nombre !== '' ? mb_strtoupper($nombre, 'UTF-8') : 'cliente';
        $numeroRecibo = (string) ($cobro->numero_recibo ?: (string) $cobro->id);
        $monto = number_format((float) $cobro->monto, 0, ',', '.');
        $fecha = $cobro->fecha_pago?->format('Y-m-d') ?? now()->format('Y-m-d');
        $formaPago = Cobro::formasPago()[$cobro->forma_pago] ?? ($cobro->forma_pago ?: 'Otro');

        $detalle = trim((string) ($cobro->concepto ?? ''));
        if ($detalle === '' && $cobro->facturaInternas->isNotEmpty()) {
            $detalle = $cobro->facturaInternas
                ->map(function (FacturaInterna $f) {
                    if ($f->esServicioEspecial()) {
                        $f->loadMissing('detalles');

                        return $this->conceptoFacturaParaWhatsApp($f);
                    }
                    $desde = $f->periodo_desde?->format('d/m/Y');
                    $hasta = $f->periodo_hasta?->format('d/m/Y');
                    if ($desde && $hasta) {
                        return "Desde {$desde} hasta {$hasta}";
                    }

                    return 'Factura #'.$f->id;
                })
                ->implode('; ');
        }
        if ($detalle === '') {
            $detalle = 'Pago de servicio';
        }
        $detalle = mb_substr(preg_replace('/\s+/u', ' ', $detalle) ?? $detalle, 0, 200);

        $urlPublica = $cobro->urlPublicaPdf();
        $sufijoBoton = $cobro->urlPublicaSufijo();

        $texto = sprintf(
            "Hola %s, desde interplus confirmamos la recepción de tu pago.\n\nRecibo: %s\nMonto: Gs. %s\nFecha: %s\nForma de pago: %s\nDetalle: %s\n\n¡Gracias por su pago!\n\nDescargar recibo: %s",
            $saludo,
            $numeroRecibo,
            $monto,
            $fecha,
            $formaPago,
            $detalle,
            $urlPublica
        );

        $ok = $this->enviar(
            event: 'recibo',
            telefono: $telefono,
            texto: $texto,
            templateParams: [
                ['type' => 'text', 'text' => $saludo],
                ['type' => 'text', 'text' => $numeroRecibo],
                ['type' => 'text', 'text' => $monto],
                ['type' => 'text', 'text' => $fecha],
                ['type' => 'text', 'text' => $formaPago],
                ['type' => 'text', 'text' => $detalle],
            ],
            meta: [
                'cliente_id' => $cliente->cliente_id,
                'contexto_tipo' => 'recibo',
                'contexto_id' => $cobro->id,
            ],
            urlButtonParameters: [
                ['type' => 'text', 'text' => $sufijoBoton],
            ]
        );

        $telNorm = $this->whatsapp->normalizePhone($telefono) ?? $telefono;

        return $ok
            ? ['ok' => true, 'message' => 'Recibo enviado por WhatsApp a '.$telNorm.'.']
            : ['ok' => false, 'message' => 'No se pudo enviar el recibo por WhatsApp a '.$telNorm.'. Revisá el panel de mensajes.'];
    }

    /**
     * Aviso staff → cliente: técnico en camino (visita / instalación).
     * Solo plantilla Meta (sin fallback a texto libre).
     *
     * Plantilla sugerida: staff_tecnico_en_camino_v1
     * Hola {{1}}. Le informamos que nuestro técnico {{2}} está en camino… {{3}}.
     *
     * @param  array{cliente_id?: int|null, ticket_id?: int|null, contexto_tipo?: string|null, contexto_id?: int|null}  $meta
     * @param  array<string, mixed>  $auditExtra  Se guarda en payload.audit (usuario, tipo, recurso, base legal)
     * @return array{ok: bool, message_id?: string|null, status?: int, message?: string, error?: string|null}
     */
    public function tecnicoEnCamino(
        string $telefono,
        string $nombreCliente,
        string $nombreTecnico,
        string $tarea,
        array $meta = [],
        array $auditExtra = [],
        ?string $trackingUrlSuffix = null,
    ): array {
        if (! $this->whatsapp->isConfigured()) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'WhatsApp no está configurado.',
                'error' => 'not_configured',
            ];
        }

        $template = trim((string) config('whatsapp.templates.en_camino', ''));
        if ($template === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Plantilla de aviso en camino no configurada.',
                'error' => 'template_missing',
            ];
        }

        $langPreferido = (string) (
            config('whatsapp.template_languages.en_camino')
            ?: config('whatsapp.default_template_language', 'es')
        );

        $nombreCliente = trim($nombreCliente) !== '' ? trim($nombreCliente) : 'cliente';
        $nombreTecnico = trim($nombreTecnico) !== '' ? trim($nombreTecnico) : 'nuestro técnico';
        $tarea = trim($tarea) !== '' ? trim($tarea) : 'la visita técnica programada';

        // Meta limita variables de cuerpo (~1024); recortar con margen.
        $nombreCliente = mb_substr($nombreCliente, 0, 60);
        $nombreTecnico = mb_substr($nombreTecnico, 0, 60);
        $tarea = mb_substr($tarea, 0, 120);

        try {
            $lang = $this->whatsapp->resolverIdiomaPlantilla($template, $langPreferido) ?: $langPreferido;

            $urlButtonParameters = [];
            if (filled($trackingUrlSuffix) && (bool) config('whatsapp.en_camino_tracking_enabled', false)) {
                $urlButtonParameters[] = ['type' => 'text', 'text' => (string) $trackingUrlSuffix];
            }

            $textoVisible = sprintf(
                "Hola %s.\n\nLe informamos que nuestro técnico %s está en camino a su domicilio para realizar %s.\n\nEste es un aviso automático de sistema Interplus. No es necesario responder este mensaje.",
                $nombreCliente,
                $nombreTecnico,
                $tarea
            );

            $mensaje = $this->whatsapp->sendTemplate(
                $telefono,
                $template,
                $lang,
                [
                    ['type' => 'text', 'text' => $nombreCliente],
                    ['type' => 'text', 'text' => $nombreTecnico],
                    ['type' => 'text', 'text' => $tarea],
                ],
                $meta,
                $urlButtonParameters,
                0,
                $textoVisible,
            );

            $payload = is_array($mensaje->payload) ? $mensaje->payload : [];
            $payload['audit'] = array_merge([
                'base_legal' => 'interes_legitimo_prestacion_servicio',
                'consentimiento' => 'aviso_operativo_campo',
            ], $auditExtra);
            $mensaje->payload = $payload;
            $mensaje->save();

            $wamid = is_string($mensaje->wamid) ? $mensaje->wamid : null;
            if ($mensaje->estado !== WhatsappMensaje::ESTADO_FALLIDO && filled($wamid)) {
                return [
                    'ok' => true,
                    'message_id' => $wamid,
                    'status' => 200,
                ];
            }

            // Omitido/fallido sin llegar a Meta → config; rechazo Meta → 502
            $error = (string) ($mensaje->error_message ?? 'WhatsApp rechazó o no respondió el aviso.');
            $esConfig = str_contains(mb_strtolower($error), 'deshabilitado')
                || str_contains(mb_strtolower($error), 'credenciales')
                || str_contains(mb_strtolower($error), 'config');

            return [
                'ok' => false,
                'status' => $esConfig ? 422 : 502,
                'message' => $esConfig
                    ? 'Plantilla o configuración de WhatsApp incompleta.'
                    : 'WhatsApp rechazó o no respondió el aviso.',
                'message_id' => $wamid,
                'error' => $error,
            ];
        } catch (\Throwable $e) {
            Log::error('[WhatsApp outbound] Error enviando en_camino: '.$e->getMessage(), [
                'exception' => $e,
                'meta' => $meta,
            ]);

            return [
                'ok' => false,
                'status' => 502,
                'message' => 'WhatsApp rechazó o no respondió el aviso.',
                'error' => $e->getMessage(),
            ];
        }
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
     * @param  list<array{type: string, text?: string}>  $urlButtonParameters
     */
    private function enviar(
        string $event,
        string $telefono,
        string $texto,
        array $templateParams,
        array $meta,
        array $urlButtonParameters = [],
        bool $fallbackTexto = true,
    ): bool {
        $template = trim((string) config("whatsapp.templates.{$event}", ''));
        $langPreferido = (string) (
            config("whatsapp.template_languages.{$event}")
            ?: config('whatsapp.default_template_language', 'es')
        );

        try {
            if ($template !== '') {
                $lang = $this->whatsapp->resolverIdiomaPlantilla($template, $langPreferido) ?: $langPreferido;

                $mensaje = $this->whatsapp->sendTemplate(
                    $telefono,
                    $template,
                    $lang,
                    $templateParams,
                    $meta,
                    $urlButtonParameters,
                    0,
                    $texto,
                );

                if ($mensaje->estado !== 'fallido') {
                    return true;
                }

                Log::warning('[WhatsApp outbound] Plantilla falló'.($fallbackTexto ? ', reintento con texto' : ''), [
                    'event' => $event,
                    'template' => $template,
                    'language' => $lang,
                    'error' => $mensaje->error_message,
                    'error_code' => $mensaje->error_code,
                    'meta' => $meta,
                ]);

                if (! $fallbackTexto) {
                    return false;
                }
            }

            $mensaje = $this->whatsapp->sendText($telefono, $texto, $meta);

            return $mensaje->estado !== 'fallido';
        } catch (\Throwable $e) {
            Log::error('[WhatsApp outbound] Error enviando '.$event.': '.$e->getMessage(), [
                'exception' => $e,
                'meta' => $meta,
            ]);

            return false;
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

    /**
     * Concepto para plantillas WhatsApp: descripción del ítem en servicio especial;
     * período "d/m/Y - d/m/Y" en facturas normales.
     */
    private function conceptoFacturaParaWhatsApp(FacturaInterna $factura): string
    {
        if ($factura->esServicioEspecial()) {
            $factura->loadMissing('detalles');
            $descripciones = $factura->detalles
                ->map(fn ($d) => trim((string) ($d->descripcion ?? $d->concepto ?? '')))
                ->filter(fn (string $d) => $d !== '')
                ->unique()
                ->values();

            if ($descripciones->isNotEmpty()) {
                $texto = $descripciones->implode('; ');

                return mb_substr(preg_replace('/\s+/u', ' ', $texto) ?? $texto, 0, 200);
            }

            $obs = trim((string) ($factura->observaciones ?? ''));
            if ($obs !== '') {
                return mb_substr(preg_replace('/\s+/u', ' ', $obs) ?? $obs, 0, 200);
            }

            return 'Servicio especial';
        }

        $desde = $factura->periodo_desde?->format('d/m/Y');
        $hasta = $factura->periodo_hasta?->format('d/m/Y');
        if ($desde && $hasta) {
            return "{$desde} - {$hasta}";
        }

        return '-';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ultimoFallo(string $contextoTipo, int $contextoId, int $clienteId): ?array
    {
        $ultimo = WhatsappMensaje::query()
            ->where('contexto_tipo', $contextoTipo)
            ->where('cliente_id', $clienteId)
            ->when($contextoId > 0, fn ($q) => $q->where('contexto_id', $contextoId))
            ->latest('id')
            ->first();

        if (! $ultimo || ! $ultimo->esFallido()) {
            return null;
        }

        return $ultimo->detalleFallo();
    }

    /**
     * @param  array<string, mixed>|null  $fallo
     */
    private function mensajeFallo(string $base, ?array $fallo): string
    {
        if (! $fallo) {
            return $base.' Revisá el historial en el modal.';
        }

        $partes = [$base];
        if (! empty($fallo['codigo'])) {
            $partes[] = 'Código Meta '.$fallo['codigo'];
        }
        if (! empty($fallo['mensaje'])) {
            $partes[] = (string) $fallo['mensaje'];
        }
        if (! empty($fallo['tip'])) {
            $partes[] = (string) $fallo['tip'];
        }

        return implode(' — ', $partes);
    }
}
