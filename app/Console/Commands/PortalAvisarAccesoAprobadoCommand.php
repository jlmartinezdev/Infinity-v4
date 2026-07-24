<?php

namespace App\Console\Commands;

use App\Models\SolicitudAcceso;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Avisa por WhatsApp al cliente que su solicitud de acceso fue aprobada,
 * incluyendo la contraseña PLUS de acceso al portal/app.
 *
 * Uso:
 *   php artisan portal:avisar-acceso-aprobado {solicitud_id} --clave=PLUS1234
 *
 * Activación automática al aprobar (opcional):
 *   WHATSAPP_EVENT_ACCESO_APROBADO=true
 */
class PortalAvisarAccesoAprobadoCommand extends Command
{
    protected $signature = 'portal:avisar-acceso-aprobado
                            {solicitud_id : ID de solicitudes_acceso}
                            {--clave= : Contraseña PLUS a enviar (obligatoria)}
                            {--telefono= : Forzar teléfono destino}';

    protected $description = 'Envía WhatsApp al cliente con acceso aprobado y su contraseña PLUS';

    public function handle(WhatsAppService $whatsapp): int
    {
        if (! $whatsapp->isConfigured()) {
            $this->error('WhatsApp no configurado.');

            return self::FAILURE;
        }

        $clave = trim((string) $this->option('clave'));
        if ($clave === '') {
            $this->error('Indicá --clave=PLUS....');

            return self::FAILURE;
        }

        $solicitud = SolicitudAcceso::query()
            ->with('cliente')
            ->find((int) $this->argument('solicitud_id'));

        if (! $solicitud) {
            $this->error('Solicitud no encontrada.');

            return self::FAILURE;
        }

        $telefono = trim((string) ($this->option('telefono') ?: $solicitud->whatsapp ?: $solicitud->cliente?->telefono));
        if ($telefono === '') {
            $this->error('Sin teléfono (solicitud.whatsapp / cliente.telefono / --telefono).');

            return self::FAILURE;
        }

        $nombre = trim((string) (
            $solicitud->cliente
                ? trim(($solicitud->cliente->nombre ?? '').' '.($solicitud->cliente->apellido ?? ''))
                : $solicitud->nombre
        ));
        $documento = $solicitud->cliente?->cedula ?: $solicitud->cedula;

        $texto = "Hola {$nombre},\n"
            ."Tu acceso a la app/portal Infinity fue aprobado.\n"
            ."Usuario (documento): {$documento}\n"
            ."Contraseña: {$clave}\n"
            .'Podés ingresar desde la aplicación con tu documento y esta clave.';

        $mensaje = $whatsapp->sendText($telefono, $texto, [
            'cliente_id' => $solicitud->cliente_id,
            'contexto_tipo' => 'acceso_aprobado',
            'contexto_id' => $solicitud->id,
        ]);

        $this->table(
            ['id', 'telefono', 'estado', 'error'],
            [[
                $mensaje->id,
                $mensaje->telefono,
                $mensaje->estado,
                $mensaje->error_message ?? '-',
            ]]
        );

        return $mensaje->estado === 'fallido' ? self::FAILURE : self::SUCCESS;
    }
}
