<?php

namespace App\Services\WhatsApp\Soporte;

use App\Jobs\NotificarZonaRestablecidaJob;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\WaIncidenteZona;
use App\Models\WaServicioAviso;
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

        $incidente = WaIncidenteZona::query()->create([
            'router_id' => $routerId,
            'host' => $host,
            'origen' => $origen,
            'estado' => 'abierto',
            'resumen' => mb_substr($resumen, 0, 255),
            'abierto_at' => now(),
        ]);
        $this->inscribirReclamosRecientes($incidente);
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
            try {
                NotificarZonaRestablecidaJob::dispatchSync((int) $incidente->id);
            } catch (\Throwable $e) {
                Log::warning('[soporte] zona aviso job', [
                    'incidente_id' => $incidente->id,
                    'error' => $e->getMessage(),
                ]);
            }
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

    /** El teléfono ya quedó anotado en un incidente de zona aún abierto. */
    public function telefonoConAvisoAbierto(?string $telefono): bool
    {
        $tel = preg_replace('/\D+/', '', (string) $telefono) ?: '';
        if ($tel === '') {
            return false;
        }

        return WaZonaAviso::query()
            ->where('telefono', $tel)
            ->whereHas('incidente', fn ($q) => $q->where('estado', 'abierto'))
            ->exists();
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

    /**
     * El cliente reclamó y su sesión o equipo estaba caído. Se avisa cuando vuelva a conectar.
     *
     * @param  array<string, mixed>  $cuenta
     */
    public function anotarServicioCaido(int $servicioId, array $cuenta, string $telefono, bool $sandbox): void
    {
        $tel = preg_replace('/\D+/', '', $telefono) ?: trim($telefono);
        if ($servicioId <= 0 || $tel === '') {
            return;
        }

        WaServicioAviso::query()->updateOrCreate(
            ['servicio_id' => $servicioId],
            [
                'cliente_id' => isset($cuenta['cliente_id']) ? (int) $cuenta['cliente_id'] : null,
                'telefono' => $tel,
                'nombre_pila' => is_string($cuenta['nombre_pila'] ?? null) ? $cuenta['nombre_pila'] : (is_string($cuenta['nombre'] ?? null) ? $cuenta['nombre'] : null),
                'apellido' => is_string($cuenta['apellido'] ?? null) ? $cuenta['apellido'] : null,
                'sandbox' => $sandbox || WhatsAppAgentService::esTelefonoTest($tel),
                'notificado_at' => null,
            ]
        );
    }

    public function notificarServicioEnLinea(int $servicioId): void
    {
        $aviso = WaServicioAviso::query()
            ->where('servicio_id', $servicioId)
            ->whereNull('notificado_at')
            ->first();
        if (! $aviso) {
            return;
        }

        $texto = FraseCliente::servicioRestablecido(
            is_string($aviso->nombre_pila) ? $aviso->nombre_pila : null,
            is_string($aviso->apellido) ? $aviso->apellido : null,
        );
        if (! $this->enviarAviso($aviso->telefono, $texto, $aviso->cliente_id ? (int) $aviso->cliente_id : null, (bool) $aviso->sandbox, $servicioId)) {
            return;
        }

        $aviso->notificado_at = now();
        $aviso->save();
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

    private function inscribirReclamosRecientes(WaIncidenteZona $incidente): void
    {
        $routerId = (int) ($incidente->router_id ?? 0);
        if ($routerId <= 0) {
            return;
        }

        try {
            $clientes = Servicio::query()
                ->where('estado', Servicio::ESTADO_ACTIVO)
                ->whereHas('pool', fn ($q) => $q->where('router_id', $routerId))
                ->pluck('cliente_id')
                ->filter()
                ->unique()
                ->values();
            if ($clientes->isEmpty()) {
                return;
            }

            $mensajes = WhatsappMensaje::query()
                ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
                ->where('created_at', '>=', now()->subHours(6))
                ->whereIn('cliente_id', $clientes)
                ->orderByDesc('id')
                ->limit(200)
                ->get(['cliente_id', 'telefono', 'cuerpo']);

            foreach ($mensajes->groupBy('cliente_id') as $grupo) {
                $falla = $grupo->first(fn ($m) => SoporteTexto::pareceFalla((string) $m->cuerpo));
                if (! $falla) {
                    continue;
                }
                $cuenta = app(CuentaWhatsappService::class)->buscar((string) $falla->telefono) ?? [
                    'cliente_id' => (int) $falla->cliente_id,
                    'router_id' => $routerId,
                ];
                $cuenta['router_id'] = $routerId;
                $this->registrarAviso($cuenta, (string) $falla->telefono, WhatsAppAgentService::esTelefonoTest((string) $falla->telefono));
            }
        } catch (\Throwable $e) {
            Log::warning('[soporte] inscribir reclamos zona', ['error' => $e->getMessage()]);
        }
    }

    private function enviarAviso(string $telefono, string $texto, ?int $clienteId, bool $sandbox, int $contextoId): bool
    {
        $whatsapp = app(WhatsAppService::class);
        if ($sandbox || WhatsAppAgentService::esTelefonoTest($telefono)) {
            WhatsappMensaje::query()->create([
                'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
                'telefono' => $telefono,
                'contacto_nombre' => 'Servicio restablecido',
                'tipo' => 'text',
                'cuerpo' => $texto,
                'wamid' => 'servicio-ok-'.uniqid('', true),
                'estado' => WhatsappMensaje::ESTADO_ENVIADO,
                'cliente_id' => $clienteId,
                'contexto_tipo' => WhatsappMensaje::CONTEXTO_TEST_N8N,
                'contexto_id' => $contextoId,
                'payload' => ['origen' => 'servicio_restablecido', 'no_meta' => true],
            ]);

            return true;
        }

        if (! $whatsapp->isConfigured() || ! (bool) config('whatsapp.agent.zona_aviso', true)) {
            return false;
        }

        $whatsapp->sendText($telefono, $texto, [
            'cliente_id' => $clienteId,
            'contexto_tipo' => 'wa_servicio_restablecido',
            'contexto_id' => $contextoId,
        ]);

        return true;
    }
}
