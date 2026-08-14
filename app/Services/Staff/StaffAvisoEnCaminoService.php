<?php

namespace App\Services\Staff;

use App\Models\Auditoria;
use App\Models\Pedido;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Services\WhatsApp\WhatsAppPhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Aviso "técnico en camino" vía WhatsApp Cloud API (número oficial Infinity).
 * Contrato: POST /api/v1/staff/avisos/en-camino
 */
class StaffAvisoEnCaminoService
{
    public const TIPO_VISITA = 'visita';

    public const TIPO_INSTALACION = 'instalacion';

    public const IDEMPOTENCIA_MINUTOS = 5;

    public const CONTEXTO_TIPO = 'aviso_en_camino';

    public function __construct(
        private readonly StaffVisitaService $visitas,
        private readonly StaffPedidoInstalacionService $pedidos,
        private readonly StaffUbicacionService $ubicaciones,
        private readonly WhatsAppOutboundNotifier $whatsappOutbound,
        private readonly WhatsAppPhoneNormalizer $phones,
    ) {}

    /**
     * @return array{ok: true, data: array{enviado: bool, canal: string, message_id: string|null}}|array{ok: false, status: int, message: string}
     */
    public function enviar(User $user, string $tipo, int $recursoId, ?float $lat = null, ?float $lng = null): array
    {
        $tipo = mb_strtolower(trim($tipo));
        if (! in_array($tipo, [self::TIPO_VISITA, self::TIPO_INSTALACION], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'tipo debe ser visita o instalacion.'];
        }

        if ($tipo === self::TIPO_VISITA) {
            if (! $user->tienePermiso('tickets.crear')) {
                return ['ok' => false, 'status' => 403, 'message' => 'No tenés permiso para avisar visitas.'];
            }
            $resolved = $this->resolverVisita($user, $recursoId);
        } else {
            if (! $user->tienePermiso('pedidos.editar')) {
                return ['ok' => false, 'status' => 403, 'message' => 'No tenés permiso para avisar instalaciones.'];
            }
            $resolved = $this->resolverInstalacion($user, $recursoId);
        }

        if (! ($resolved['ok'] ?? false)) {
            return $resolved;
        }

        /** @var array{ok: true, cliente_nombre: string, telefono: string, tarea: string, cliente_id: int|null, ticket_id: int|null} $resolved */
        if ($this->avisoReciente($user, $tipo, $recursoId)) {
            return ['ok' => false, 'status' => 409, 'message' => 'Aviso equivalente enviado recientemente. Esperá unos minutos.'];
        }

        // Estado en_camino antes del aviso; si WhatsApp falla, se conserva.
        $this->marcarEnCamino($user, $tipo, $recursoId, $resolved);

        if ($lat !== null && $lng !== null) {
            $this->registrarUbicacionOpcional($user, $lat, $lng, $tipo === self::TIPO_VISITA ? $recursoId : null);
        }

        $tecnicoNombre = trim((string) ($user->name ?? ''));
        if ($tecnicoNombre === '') {
            $tecnicoNombre = 'nuestro técnico';
        }

        $template = trim((string) config('whatsapp.templates.en_camino', ''));
        if (! config('whatsapp.enabled') || ! filled(config('whatsapp.token')) || ! filled(config('whatsapp.phone_number_id')) || $template === '') {
            $this->auditar($user, $tipo, $recursoId, $resolved['telefono'], $template, [], null, 'config_incompleta', false);

            return ['ok' => false, 'status' => 422, 'message' => 'Plantilla o configuración de WhatsApp incompleta.'];
        }

        $variables = [
            $resolved['cliente_nombre'],
            $tecnicoNombre,
            $resolved['tarea'],
        ];

        $result = $this->whatsappOutbound->tecnicoEnCamino(
            telefono: $resolved['telefono'],
            nombreCliente: $resolved['cliente_nombre'],
            nombreTecnico: $tecnicoNombre,
            tarea: $resolved['tarea'],
            meta: [
                'cliente_id' => $resolved['cliente_id'],
                'ticket_id' => $resolved['ticket_id'],
                'contexto_tipo' => self::CONTEXTO_TIPO,
                'contexto_id' => $recursoId,
            ],
            auditExtra: [
                'usuario_id' => (int) $user->usuario_id,
                'tipo' => $tipo,
                'recurso_id' => $recursoId,
                'base_legal' => 'interes_legitimo_prestacion_servicio',
                'consentimiento' => 'aviso_operativo_campo',
            ],
        );

        $messageId = $result['message_id'] ?? null;
        $estado = ($result['ok'] ?? false) ? 'enviado' : 'fallido';

        $this->auditar(
            $user,
            $tipo,
            $recursoId,
            $resolved['telefono'],
            $template,
            $variables,
            is_string($messageId) ? $messageId : null,
            $estado,
            (bool) ($result['ok'] ?? false),
            $result['error'] ?? null,
        );

        if (! ($result['ok'] ?? false)) {
            $status = (int) ($result['status'] ?? 502);

            return [
                'ok' => false,
                'status' => $status,
                'message' => (string) ($result['message'] ?? 'WhatsApp rechazó o no respondió el aviso.'),
            ];
        }

        $this->marcarIdempotencia($user, $tipo, $recursoId);

        return [
            'ok' => true,
            'data' => [
                'enviado' => true,
                'canal' => 'whatsapp',
                'message_id' => is_string($messageId) ? $messageId : null,
            ],
        ];
    }

    /**
     * @return array{ok: true, cliente_nombre: string, telefono: string, tarea: string, cliente_id: int|null, ticket_id: int|null}|array{ok: false, status: int, message: string}
     */
    private function resolverVisita(User $user, int $id): array
    {
        $ticket = Ticket::query()->with(['cliente', 'ticketAsunto'])->find($id);
        if (! $ticket) {
            return ['ok' => false, 'status' => 404, 'message' => 'Visita no encontrada.'];
        }

        if (! $this->visitas->encontrarAccesible($user, $id)) {
            return ['ok' => false, 'status' => 403, 'message' => 'No tenés acceso a esta visita.'];
        }

        $cliente = $ticket->cliente;
        $telefonoRaw = trim((string) ($cliente?->telefono ?? ''));
        $telefono = $this->phones->normalize($telefonoRaw);
        if (! $telefono) {
            return ['ok' => false, 'status' => 400, 'message' => 'La visita no tiene un teléfono válido para WhatsApp.'];
        }

        $nombre = trim(implode(' ', array_filter([
            $cliente?->nombre,
            $cliente?->apellido,
        ])));
        if ($nombre === '') {
            $nombre = 'cliente';
        }

        $asunto = trim((string) ($ticket->ticketAsunto?->nombre ?? ''));
        $tarea = $asunto !== ''
            ? 'la visita técnica por '.$asunto
            : 'la visita técnica programada';

        return [
            'ok' => true,
            'cliente_nombre' => $nombre,
            'telefono' => $telefono,
            'tarea' => $tarea,
            'cliente_id' => $cliente?->cliente_id ? (int) $cliente->cliente_id : null,
            'ticket_id' => (int) $ticket->id,
            'ticket' => $ticket,
        ];
    }

    /**
     * @return array{ok: true, cliente_nombre: string, telefono: string, tarea: string, cliente_id: int|null, ticket_id: int|null}|array{ok: false, status: int, message: string}
     */
    private function resolverInstalacion(User $user, int $id): array
    {
        $pedido = Pedido::query()->with('cliente')->find($id);
        if (! $pedido) {
            return ['ok' => false, 'status' => 404, 'message' => 'Pedido no encontrado.'];
        }

        if (! $this->pedidos->encontrar($user, $id)) {
            return ['ok' => false, 'status' => 403, 'message' => 'No tenés acceso a este pedido.'];
        }

        $cliente = $pedido->cliente;
        $telefonoRaw = trim((string) ($cliente?->telefono ?? ''));
        $telefono = $this->phones->normalize($telefonoRaw);
        if (! $telefono) {
            return ['ok' => false, 'status' => 400, 'message' => 'El pedido no tiene un teléfono válido para WhatsApp.'];
        }

        $nombre = trim(implode(' ', array_filter([
            $cliente?->nombre,
            $cliente?->apellido,
        ])));
        if ($nombre === '') {
            $nombre = 'cliente';
        }

        return [
            'ok' => true,
            'cliente_nombre' => $nombre,
            'telefono' => $telefono,
            'tarea' => 'la instalación de su servicio',
            'cliente_id' => $cliente?->cliente_id ? (int) $cliente->cliente_id : null,
            'ticket_id' => null,
            'pedido' => $pedido,
        ];
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function marcarEnCamino(User $user, string $tipo, int $recursoId, array $resolved): void
    {
        try {
            if ($tipo === self::TIPO_VISITA) {
                /** @var Ticket|null $ticket */
                $ticket = $resolved['ticket'] ?? Ticket::query()->find($recursoId);
                if ($ticket && Ticket::normalizarEstado((string) $ticket->estado) !== 'en_camino') {
                    $this->visitas->actualizar($ticket, $user, ['estado' => 'en_camino']);
                }

                return;
            }

            /** @var Pedido|null $pedido */
            $pedido = $resolved['pedido'] ?? Pedido::query()->find($recursoId);
            if (! $pedido || $pedido->estado_instalado) {
                return;
            }

            $base = trim((string) ($pedido->observaciones ?? ''));
            if (! str_contains(mb_strtolower($base), '[en_camino]')) {
                $pedido->observaciones = trim($base."\n[en_camino]");
                $pedido->save();
            }
        } catch (\Throwable $e) {
            Log::warning('[Staff aviso en camino] No se pudo marcar estado en_camino', [
                'tipo' => $tipo,
                'recurso_id' => $recursoId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function registrarUbicacionOpcional(User $user, float $lat, float $lng, ?int $visitaId): void
    {
        try {
            $this->ubicaciones->reportar((int) $user->usuario_id, [
                'lat' => $lat,
                'lng' => $lng,
                'en_turno' => true,
                'visita_id' => $visitaId,
            ]);
        } catch (\Throwable $e) {
            Log::info('[Staff aviso en camino] Ubicación omitida', ['error' => $e->getMessage()]);
        }
    }

    private function avisoReciente(User $user, string $tipo, int $recursoId): bool
    {
        if (Cache::has($this->idempotenciaKey($user, $tipo, $recursoId))) {
            return true;
        }

        $desde = now()->subMinutes(self::IDEMPOTENCIA_MINUTOS);

        $enMensajes = WhatsappMensaje::query()
            ->where('direccion', WhatsappMensaje::DIRECCION_SALIDA)
            ->where('contexto_tipo', self::CONTEXTO_TIPO)
            ->where('contexto_id', $recursoId)
            ->whereIn('estado', [
                WhatsappMensaje::ESTADO_PENDIENTE,
                WhatsappMensaje::ESTADO_ENVIADO,
                WhatsappMensaje::ESTADO_ENTREGADO,
                WhatsappMensaje::ESTADO_LEIDO,
            ])
            ->where('created_at', '>=', $desde)
            ->where('payload->audit->usuario_id', (int) $user->usuario_id)
            ->where('payload->audit->tipo', $tipo)
            ->exists();

        if ($enMensajes) {
            return true;
        }

        return Auditoria::query()
            ->where('usuario_id', $user->usuario_id)
            ->where('tabla', 'avisos_en_camino')
            ->where('accion', 'enviado')
            ->where('registro_id', $recursoId)
            ->where('registro_key', $tipo)
            ->where('created_at', '>=', $desde)
            ->exists();
    }

    private function marcarIdempotencia(User $user, string $tipo, int $recursoId): void
    {
        Cache::put(
            $this->idempotenciaKey($user, $tipo, $recursoId),
            1,
            now()->addMinutes(self::IDEMPOTENCIA_MINUTOS)
        );
    }

    private function idempotenciaKey(User $user, string $tipo, int $recursoId): string
    {
        return sprintf('staff:aviso_en_camino:%s:%d:%d', $tipo, $recursoId, (int) $user->usuario_id);
    }

    /**
     * @param  list<string>  $variables
     */
    private function auditar(
        User $user,
        string $tipo,
        int $recursoId,
        string $telefono,
        string $plantilla,
        array $variables,
        ?string $messageId,
        string $estado,
        bool $enviado,
        ?string $error = null,
    ): void {
        try {
            $ua = request()?->userAgent();
            if (is_string($ua) && strlen($ua) > 255) {
                $ua = substr($ua, 0, 255);
            }

            Auditoria::create([
                'usuario_id' => $user->usuario_id,
                'tabla' => 'avisos_en_camino',
                'accion' => $enviado ? 'enviado' : 'fallido',
                'registro_id' => $recursoId,
                'registro_key' => $tipo,
                'detalles' => json_encode([
                    'tipo' => $tipo,
                    'recurso_id' => $recursoId,
                    'destinatario' => $this->enmascararTelefono($telefono),
                    'plantilla' => $plantilla,
                    'variables' => $variables,
                    'message_id' => $messageId,
                    'estado' => $estado,
                    'canal' => 'whatsapp',
                    'base_legal' => 'interes_legitimo_prestacion_servicio',
                    'consentimiento' => 'aviso_operativo_campo',
                    'error' => $error,
                    'timestamp' => now()->toIso8601String(),
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => request()?->ip(),
                'user_agent' => $ua,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Staff aviso en camino] Auditoría falló: '.$e->getMessage());
        }
    }

    private function enmascararTelefono(string $telefono): string
    {
        $digits = preg_replace('/\D+/', '', $telefono) ?? '';
        if (strlen($digits) < 6) {
            return '***';
        }

        return substr($digits, 0, 3).str_repeat('*', max(0, strlen($digits) - 6)).substr($digits, -3);
    }
}
