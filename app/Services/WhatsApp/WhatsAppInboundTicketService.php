<?php

namespace App\Services\WhatsApp;

use App\Jobs\EnviarWhatsAppMensaje;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use App\Models\WhatsappMensaje;
use App\Services\FcmPushService;
use Illuminate\Support\Facades\Log;

/**
 * Convierte mensajes entrantes de WhatsApp en tickets (crear o actualizar).
 */
class WhatsAppInboundTicketService
{
    public function __construct(
        private readonly FcmPushService $fcm,
    ) {}

    /**
     * @return array{ticket: Ticket|null, created: bool}
     */
    public function handle(WhatsappMensaje $mensaje): array
    {
        if (! config('whatsapp.inbound_tickets_enabled', true)) {
            return ['ticket' => null, 'created' => false];
        }

        return $this->crearOAdjuntar($mensaje, true);
    }

    /**
     * Crea o adjunta ticket aunque inbound_tickets esté apagado (escalado del agente IA).
     *
     * @return array{ticket: Ticket|null, created: bool}
     */
    public function crearOAdjuntar(WhatsappMensaje $mensaje, bool $autoRespuesta = true): array
    {
        if (! $mensaje->esEntrada()) {
            return ['ticket' => null, 'created' => false];
        }

        try {
            $abierto = $this->buscarTicketAbierto($mensaje);
            if ($abierto) {
                $this->adjuntarMensaje($abierto, $mensaje);
                $mensaje->ticket_id = $abierto->id;
                $mensaje->save();

                return ['ticket' => $abierto->fresh(), 'created' => false];
            }

            $ticket = $this->crearTicket($mensaje);
            $mensaje->ticket_id = $ticket->id;
            $mensaje->save();

            if ($autoRespuesta) {
                $this->autoRespuestaSiCorresponde($mensaje, $ticket);
            }

            return ['ticket' => $ticket, 'created' => true];
        } catch (\Throwable $e) {
            Log::error('[WhatsApp inbound] Error ticket: '.$e->getMessage(), [
                'whatsapp_mensaje_id' => $mensaje->id,
                'exception' => $e,
            ]);

            return ['ticket' => null, 'created' => false];
        }
    }

    private function buscarTicketAbierto(WhatsappMensaje $mensaje): ?Ticket
    {
        $query = Ticket::query()
            ->where('reportado_desde', 'whatsapp')
            ->whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado']);

        if ($mensaje->cliente_id) {
            $query->where('cliente_id', $mensaje->cliente_id);
        } else {
            $ticketIds = WhatsappMensaje::query()
                ->where('telefono', $mensaje->telefono)
                ->whereNotNull('ticket_id')
                ->orderByDesc('id')
                ->limit(50)
                ->pluck('ticket_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            if ($ticketIds === []) {
                return null;
            }

            $query->whereIn('id', $ticketIds);
        }

        return $query->orderByDesc('updated_at')->first();
    }

    private function crearTicket(WhatsappMensaje $mensaje): Ticket
    {
        $linea = $this->formatoLinea($mensaje);
        $descripcion = "Mensaje WhatsApp desde {$mensaje->telefono}\n\n{$linea}";

        $asignadoId = config('whatsapp.inbound_ticket_asignado_id');
        $asignadoId = filled($asignadoId) ? (int) $asignadoId : null;

        return Ticket::query()->create([
            'cliente_id' => $mensaje->cliente_id,
            'ticket_asunto_id' => $this->resolverAsuntoId(),
            'descripcion' => $descripcion,
            'estado' => 'pendiente',
            'prioridad' => (string) config('whatsapp.inbound_ticket_prioridad', 'media'),
            'reportado_desde' => 'whatsapp',
            'observaciones' => $linea,
            'asignado_id' => $asignadoId,
        ]);
    }

    private function adjuntarMensaje(Ticket $ticket, WhatsappMensaje $mensaje): void
    {
        $linea = $this->formatoLinea($mensaje);
        $prev = trim((string) $ticket->observaciones);
        $ticket->observaciones = $prev === '' ? $linea : $prev."\n".$linea;

        // Si estaba pendiente y sigue abierto, mantener; si hace falta reabrir lógica futura.
        if ($ticket->estado === 'resuelto') {
            $ticket->estado = 'pendiente';
            $ticket->fecha_cierre = null;
        }

        $ticket->save();

        $this->fcm->notifyStaff(
            'WhatsApp — ticket #'.$ticket->id,
            mb_strimwidth($mensaje->cuerpo ?: ('['.$mensaje->tipo.']'), 0, 120, '…'),
            [
                'tipo' => 'visita',
                'id' => (string) $ticket->id,
                'visita_id' => (string) $ticket->id,
                'ticket_id' => (string) $ticket->id,
            ]
        );
    }

    private function formatoLinea(WhatsappMensaje $mensaje): string
    {
        $texto = trim((string) ($mensaje->cuerpo ?? ''));
        if ($texto === '') {
            $texto = '[mensaje '.$mensaje->tipo.']';
        }

        return '['.now()->format('d/m/Y H:i').'] '.$texto;
    }

    private function resolverAsuntoId(): int
    {
        $configured = config('whatsapp.inbound_ticket_asunto_id');
        if ($configured) {
            $id = (int) $configured;
            if (TicketAsunto::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $nombre = (string) config('whatsapp.inbound_ticket_asunto_nombre', 'WhatsApp');
        $asunto = TicketAsunto::query()->firstOrCreate(['nombre' => $nombre]);

        return (int) $asunto->id;
    }

    private function autoRespuestaSiCorresponde(WhatsappMensaje $mensaje, Ticket $ticket): void
    {
        if (! config('whatsapp.auto_reply_enabled', false)) {
            return;
        }

        $texto = trim((string) config('whatsapp.auto_reply_text', ''));
        if ($texto === '') {
            return;
        }

        $texto = str_replace('{ticket_id}', (string) $ticket->id, $texto);

        // Sync: con QUEUE_CONNECTION=database no depende de un worker corriendo.
        EnviarWhatsAppMensaje::dispatchSync(
            'text',
            $mensaje->telefono,
            $texto,
            null,
            null,
            [],
            [
                'cliente_id' => $mensaje->cliente_id,
                'ticket_id' => $ticket->id,
                'contexto_tipo' => 'whatsapp_auto_reply',
                'contexto_id' => $ticket->id,
            ]
        );
    }
}
