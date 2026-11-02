<?php

namespace App\Console\Commands;

use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\WhatsappMensaje;
use App\Services\Ubnt\UbntAntenaService;
use App\Services\WhatsApp\Soporte\FraseCliente;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

class VigilarPuertosSoporteCommand extends Command
{
    protected $signature = 'soporte:vigilar-puertos';

    protected $description = 'Cierra el ticket de cable cuando el puerto de la antena negocia a 1000';

    public function handle(UbntAntenaService $ubnt, WhatsAppService $whatsapp): int
    {
        $tickets = Ticket::query()
            ->whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado'])
            ->where('reportado_desde', 'whatsapp')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->filter(fn (Ticket $ticket) => (bool) data_get($ticket->datos_diagnostico, 'vigilar_puerto'));

        foreach ($tickets as $ticket) {
            $servicioId = (int) data_get($ticket->datos_diagnostico, 'servicio_id');
            $servicio = Servicio::query()->find($servicioId);
            $ip = trim((string) ($servicio->ip ?? ''));
            if (! $servicio || $ip === '') {
                continue;
            }

            try {
                $ap = $ubnt->consultarApAirOs($ip);
            } catch (\Throwable) {
                continue;
            }

            $lan = $ap['lan_speed'] ?? null;
            $conectado = $ap['lan_conectado'] ?? null;
            if ($conectado === false || ! is_numeric($lan) || (int) $lan < 1000) {
                continue;
            }

            $datos = is_array($ticket->datos_diagnostico) ? $ticket->datos_diagnostico : [];
            $datos['vigilar_puerto'] = false;
            $datos['puerto_ok_at'] = now()->toIso8601String();
            $ticket->omitirAvisoCliente = true;
            $ticket->forceFill([
                'estado' => 'resuelto',
                'fecha_cierre' => now(),
                'datos_diagnostico' => $datos,
                'observaciones' => trim((string) $ticket->observaciones)."\n[".now()->format('d/m/Y H:i').'] El cable quedó bien.',
            ])->save();

            if ((bool) config('whatsapp.agent.auto_send', false) && $ticket->cliente_id) {
                $telefono = WhatsappMensaje::query()
                    ->where('ticket_id', $ticket->id)
                    ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
                    ->orderByDesc('id')
                    ->value('telefono');
                if (is_string($telefono) && $telefono !== '') {
                    $whatsapp->sendText($telefono, FraseCliente::RESUELTO, [
                        'cliente_id' => $ticket->cliente_id,
                        'ticket_id' => $ticket->id,
                        'contexto_tipo' => 'wa_soporte',
                    ]);
                }
            }
        }

        return self::SUCCESS;
    }
}
