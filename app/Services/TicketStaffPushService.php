<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketStaffPushService
{
    public function __construct(
        private readonly FcmPushService $fcm,
    ) {}

    public function ticketCreado(Ticket $ticket): void
    {
        $ticket->loadMissing(['cliente:cliente_id,nombre,apellido', 'ticketAsunto:id,nombre', 'asignado']);

        [$title, $body] = $this->tituloCuerpo($ticket, 'Nueva visita');

        $data = $this->dataVisita($ticket, $title, $body);

        if ($ticket->asignado_id) {
            $this->notificarAsignado($ticket, $title, $body, $data);

            return;
        }

        // Sin asignar: topic staff (admin/cajero ven el listado completo).
        $this->fcm->notifyStaff($title, $body, $data);
    }

    public function ticketAsignado(Ticket $ticket): void
    {
        if (! $ticket->asignado_id) {
            return;
        }

        $ticket->loadMissing(['cliente:cliente_id,nombre,apellido', 'ticketAsunto:id,nombre', 'asignado']);

        [$title, $body] = $this->tituloCuerpo($ticket, 'Visita asignada');
        $data = $this->dataVisita($ticket, $title, $body);

        $this->notificarAsignado($ticket, $title, $body, $data);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function tituloCuerpo(Ticket $ticket, string $tituloDefault): array
    {
        $asunto = trim((string) ($ticket->ticketAsunto?->nombre ?? ''));
        $descripcion = trim((string) ($ticket->descripcion ?? ''));
        $preview = $asunto !== ''
            ? $asunto
            : ($descripcion !== '' ? mb_strimwidth($descripcion, 0, 80, '…') : 'Sin descripción');

        $cliente = $ticket->cliente;
        $nombreCliente = $cliente
            ? trim((string) $cliente->nombre.' '.(string) $cliente->apellido)
            : '';

        $body = $nombreCliente !== ''
            ? "{$preview} — {$nombreCliente}"
            : $preview;

        return [$tituloDefault, $body];
    }

    /**
     * Payload data con deep-link (tipo + id) para ISP Staff.
     *
     * @return array<string, string>
     */
    private function dataVisita(Ticket $ticket, string $title, string $body): array
    {
        $id = (string) $ticket->id;

        return [
            'title' => $title,
            'body' => $body,
            'tipo' => 'visita',
            'id' => $id,
            'visita_id' => $id,
            'ticket_id' => $id,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function notificarAsignado(Ticket $ticket, string $title, string $body, array $data): void
    {
        $user = $ticket->asignado;
        if (! $user instanceof User) {
            $user = User::query()->whereKey($ticket->asignado_id)->first();
        }

        if (! $user || $user->esClientePortal()) {
            Log::info('FCM visita: asignado inválido', ['ticket_id' => $ticket->id, 'asignado_id' => $ticket->asignado_id]);

            return;
        }

        $ok = $this->fcm->notifyUser($user, $title, $body, $data);
        if (! $ok) {
            // Fallback: topic staff para que no se pierda el aviso si no hay token.
            $this->fcm->notifyStaff($title, $body, $data);
        }
    }
}
