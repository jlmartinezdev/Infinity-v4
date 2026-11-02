<?php

namespace App\Services\WhatsApp\Soporte;

use App\Jobs\NotificarZonaRestablecidaJob;
use App\Models\Router;
use App\Models\WaIncidenteZona;
use App\Models\WaZonaAviso;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppAgentService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\Log;

class IncidenteZonaService
{
    public function abrir(?int $routerId, ?string $host, string $origen, string $resumen): void
    {
        $query = WaIncidenteZona::query()->where('estado', 'abierto');
        if ($routerId) {
            $query->where('router_id', $routerId);
        } elseif (filled($host)) {
            $query->where('host', $host);
        } else {
            return;
        }

        if ($query->exists()) {
            return;
        }

        WaIncidenteZona::query()->create([
            'router_id' => $routerId,
            'host' => $host,
            'origen' => $origen,
            'estado' => 'abierto',
            'resumen' => mb_substr($resumen, 0, 255),
            'abierto_at' => now(),
        ]);
    }

    public function cerrar(?int $routerId, ?string $host): void
    {
        $query = WaIncidenteZona::query()->where('estado', 'abierto');
        if ($routerId) {
            $query->where('router_id', $routerId);
        } elseif (filled($host)) {
            $query->where('host', $host);
        } else {
            return;
        }

        $abiertos = $query->get(['id']);
        if ($abiertos->isEmpty()) {
            return;
        }

        $query->update([
            'estado' => 'cerrado',
            'cerrado_at' => now(),
        ]);

        foreach ($abiertos as $incidente) {
            NotificarZonaRestablecidaJob::dispatch((int) $incidente->id);
        }
    }

    public function hayAbierto(?int $routerId): bool
    {
        if (! $routerId) {
            return false;
        }

        $abierto = WaIncidenteZona::query()
            ->where('estado', 'abierto')
            ->where('router_id', $routerId)
            ->exists();

        if (! $abierto) {
            return false;
        }

        // Si el ping ya recuperó el router, cerramos el incidente stale.
        $router = Router::query()->find($routerId);
        if ($router && $router->estaConectado()) {
            $this->cerrar($routerId, (string) $router->ip);

            return false;
        }

        return true;
    }

    public function abiertoDe(?int $routerId): ?WaIncidenteZona
    {
        if (! $routerId) {
            return null;
        }

        return WaIncidenteZona::query()
            ->where('estado', 'abierto')
            ->where('router_id', $routerId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Anota al cliente para avisarle cuando cierre el incidente de zona.
     *
     * @param  array<string, mixed>  $cuenta
     */
    public function registrarAviso(array $cuenta, string $telefono, bool $sandbox = false): void
    {
        $routerId = isset($cuenta['router_id']) ? (int) $cuenta['router_id'] : null;
        $incidente = $this->abiertoDe($routerId);
        if (! $incidente) {
            return;
        }

        $tel = preg_replace('/\D+/', '', $telefono) ?: trim($telefono);
        if ($tel === '') {
            return;
        }

        WaZonaAviso::query()->updateOrCreate(
            [
                'incidente_id' => $incidente->id,
                'telefono' => $tel,
            ],
            [
                'router_id' => $routerId,
                'cliente_id' => isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'nombre_pila' => is_string($cuenta['nombre_pila'] ?? null) ? $cuenta['nombre_pila'] : (is_string($cuenta['nombre'] ?? null) ? $cuenta['nombre'] : null),
                'apellido' => is_string($cuenta['apellido'] ?? null) ? $cuenta['apellido'] : null,
                'sandbox' => $sandbox,
                'notificado_at' => null,
            ]
        );

        try {
            app(\App\Services\WhatsApp\WaAgentAnalyticsService::class)->registrarEvento(
                \App\Models\WaAgentEvento::TIPO_ZONA_AVISO,
                $tel,
                'soporte_zona',
                ['incidente_id' => $incidente->id, 'router_id' => $routerId],
                null,
                isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                null,
                null,
                null,
                null,
                null,
                $sandbox,
            );
        } catch (\Throwable) {
            //
        }
    }

    public function notificarCierre(int $incidenteId): void
    {
        $avisos = WaZonaAviso::query()
            ->where('incidente_id', $incidenteId)
            ->whereNull('notificado_at')
            ->orderBy('id')
            ->get();

        if ($avisos->isEmpty()) {
            return;
        }

        $whatsapp = app(WhatsAppService::class);

        foreach ($avisos as $aviso) {
            $texto = FraseCliente::zonaRestablecida(
                is_string($aviso->nombre_pila) ? $aviso->nombre_pila : null,
                is_string($aviso->apellido) ? $aviso->apellido : null,
            );

            try {
                if ($aviso->sandbox || WhatsAppAgentService::esTelefonoTest($aviso->telefono)) {
                    WhatsappMensaje::query()->create([
                        'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
                        'telefono' => $aviso->telefono,
                        'contacto_nombre' => 'Zona restablecida',
                        'tipo' => 'text',
                        'cuerpo' => $texto,
                        'wamid' => 'zona-ok-'.uniqid('', true),
                        'estado' => WhatsappMensaje::ESTADO_ENVIADO,
                        'cliente_id' => $aviso->cliente_id,
                        'contexto_tipo' => WhatsappMensaje::CONTEXTO_TEST_N8N,
                        'contexto_id' => $aviso->incidente_id,
                        'payload' => [
                            'origen' => 'zona_restablecida',
                            'no_meta' => true,
                            'incidente_id' => $aviso->incidente_id,
                        ],
                    ]);
                } elseif ($whatsapp->isConfigured() && (bool) config('whatsapp.agent.zona_aviso', true)) {
                    $whatsapp->sendText($aviso->telefono, $texto, [
                        'cliente_id' => $aviso->cliente_id,
                        'contexto_tipo' => 'wa_zona_restablecida',
                        'contexto_id' => $aviso->incidente_id,
                    ]);
                } else {
                    continue;
                }

                $aviso->notificado_at = now();
                $aviso->save();

                try {
                    app(\App\Services\WhatsApp\WaAgentAnalyticsService::class)->registrarEvento(
                        \App\Models\WaAgentEvento::TIPO_ZONA_OK,
                        (string) $aviso->telefono,
                        'soporte_zona',
                        ['incidente_id' => $incidenteId, 'aviso_id' => $aviso->id],
                        null,
                        $aviso->cliente_id ? (int) $aviso->cliente_id : null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        (bool) $aviso->sandbox,
                    );
                } catch (\Throwable) {
                    //
                }
            } catch (\Throwable $e) {
                Log::warning('[soporte] zona aviso', [
                    'aviso_id' => $aviso->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function abrirDesdeRouter(Router $router, string $origen): void
    {
        $this->abrir(
            (int) $router->router_id,
            (string) $router->ip,
            $origen,
            'Router '.$router->nombre.' sin respuesta',
        );
    }

    public function cerrarDesdeRouter(Router $router): void
    {
        $this->cerrar((int) $router->router_id, (string) $router->ip);
    }
}
