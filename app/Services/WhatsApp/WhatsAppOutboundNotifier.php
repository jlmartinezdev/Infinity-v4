<?php

namespace App\Services\WhatsApp;

use App\Models\Cobro;
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
     *
     * Plantilla Meta factura_generada_cliente (ej.):
     * Hola {{1}}, Interplus generó tu factura #{{2}} correspondiente a {{3}}.
     * Monto: Gs. {{4}} / Vencimiento: {{5}}
     *
     * Para servicio especial, {{3}} es la descripción del ítem (no un período).
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

        $this->enviar(
            event: 'factura',
            telefono: (string) $cliente->telefono,
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
                );

                if ($mensaje->estado !== 'fallido') {
                    return true;
                }

                // Plantilla PENDING/idioma incorrecto → texto libre (solo útil con ventana 24h).
                Log::warning('[WhatsApp outbound] Plantilla falló, reintento con texto', [
                    'event' => $event,
                    'template' => $template,
                    'language' => $lang,
                    'error' => $mensaje->error_message,
                    'error_code' => $mensaje->error_code,
                    'meta' => $meta,
                ]);
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
}
