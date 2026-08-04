<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\NuevaSolicitudAccesoNotification;
use App\Services\WhatsApp\WhatsAppRegistroOtpService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudAccesoService
{
    public function __construct(
        protected ClientePortalUserService $portal,
        protected FcmPushService $fcm,
        protected WhatsAppService $whatsapp,
        protected WhatsAppRegistroOtpService $otp,
    ) {}

    /**
     * Crea solicitud ya verificada por OTP WhatsApp (estado pendiente).
     *
     * @throws \InvalidArgumentException OTP inválido/expirado u otros datos
     */
    public function crear(array $data): SolicitudAcceso
    {
        $whatsapp = trim((string) ($data['whatsapp'] ?? ''));
        $codigoOtp = trim((string) ($data['codigo_otp'] ?? ''));

        $otp = $this->otp->validarYConsumir($whatsapp, $codigoOtp);
        if (! ($otp['ok'] ?? false)) {
            throw new \InvalidArgumentException(
                $otp['mensaje'] ?? 'Código de verificación inválido o expirado'
            );
        }

        $frentePath = $this->guardarFrenteBase64($data['frente'] ?? null, $data['cedula']);
        $telefonoFrom = $otp['telefono_normalizado'] ?? null;

        $solicitud = SolicitudAcceso::create([
            'cedula' => trim((string) $data['cedula']),
            'nombre' => trim((string) $data['nombre']),
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'direccion' => isset($data['direccion']) ? trim((string) $data['direccion']) : null,
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'frente_path' => $frentePath,
            'estado' => SolicitudAcceso::ESTADO_PENDIENTE,
            'codigo_verificacion' => preg_replace('/\D+/', '', $codigoOtp) ?: null,
            'telefono_verificado' => true,
            'telefono_verificado_at' => now(),
            'whatsapp_from' => $telefonoFrom,
        ]);

        $this->notificarStaff($solicitud);

        return $solicitud;
    }

    /**
     * @deprecated Flujo anterior (código post-alta). Mantener por solicitudes legacy.
     */
    public function marcarTelefonoVerificado(SolicitudAcceso $solicitud, ?string $whatsappFrom = null): SolicitudAcceso
    {
        if ($solicitud->estado !== SolicitudAcceso::ESTADO_PENDIENTE_VERIFICACION) {
            return $solicitud;
        }

        $solicitud->update([
            'estado' => SolicitudAcceso::ESTADO_PENDIENTE,
            'telefono_verificado' => true,
            'telefono_verificado_at' => now(),
            'whatsapp_from' => $whatsappFrom ? preg_replace('/\D+/', '', $whatsappFrom) : $solicitud->whatsapp_from,
        ]);

        $fresh = $solicitud->fresh();
        $this->notificarStaff($fresh);

        return $fresh;
    }

    public function clienteCoincidePorDocumento(string $cedula): ?Cliente
    {
        return $this->portal->buscarClientePorDocumento($cedula);
    }

    /**
     * Datos para que la app staff muestre pre-aprobación (qué cambiaría).
     *
     * @return array<string, mixed>
     */
    public function datosPreAprobacion(SolicitudAcceso $solicitud): array
    {
        $cliente = $this->clienteCoincidePorDocumento($solicitud->cedula);
        $urlPropuesta = $this->urlDesdeCoordenadas($solicitud->latitud, $solicitud->longitud);

        $clienteActual = null;
        if ($cliente) {
            $clienteActual = [
                'id' => $cliente->cliente_id,
                'nombre' => trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')),
                'documento' => $cliente->cedula,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'url_ubicacion' => $cliente->url_ubicacion,
            ];
        }

        $propuesta = [
            'telefono' => $solicitud->whatsapp,
            'direccion' => $solicitud->direccion,
            'latitud' => $solicitud->latitud,
            'longitud' => $solicitud->longitud,
            'url_ubicacion' => $urlPropuesta,
        ];

        $cambiaTelefono = $cliente
            && filled($solicitud->whatsapp)
            && trim((string) $solicitud->whatsapp) !== trim((string) ($cliente->telefono ?? ''));

        $cambiaUbicacion = $cliente && (
            (filled($solicitud->direccion) && trim((string) $solicitud->direccion) !== trim((string) ($cliente->direccion ?? '')))
            || (filled($urlPropuesta) && $urlPropuesta !== ($cliente->url_ubicacion ?? null))
        );

        return [
            'coincide_bd' => $cliente !== null,
            'cliente_actual' => $clienteActual,
            'solicitud_propuesta' => $propuesta,
            'requiere_confirmacion_actualizacion' => (bool) ($cambiaTelefono || $cambiaUbicacion),
            'cambios_sugeridos' => [
                'telefono' => $cambiaTelefono,
                'ubicacion' => $cambiaUbicacion,
            ],
        ];
    }

    /**
     * @param  array{
     *   cliente_id_vinculacion?: int|null,
     *   documento_corregido?: string|null,
     *   nombre_corregido?: string|null,
     *   actualizar_telefono?: bool,
     *   actualizar_ubicacion?: bool
     * }  $opciones
     * @return array{solicitud: SolicitudAcceso, clave: string, cliente: Cliente}
     */
    public function aprobar(SolicitudAcceso $solicitud, User $aprobador, array $opciones = []): array
    {
        if ($solicitud->estado !== SolicitudAcceso::ESTADO_PENDIENTE) {
            throw new \RuntimeException('La solicitud ya fue procesada.');
        }

        $actualizarTelefono = (bool) ($opciones['actualizar_telefono'] ?? false);
        $actualizarUbicacion = (bool) ($opciones['actualizar_ubicacion'] ?? false);

        $result = DB::transaction(function () use ($solicitud, $aprobador, $opciones, $actualizarTelefono, $actualizarUbicacion) {
            if (! empty($opciones['documento_corregido'])) {
                $solicitud->cedula = trim((string) $opciones['documento_corregido']);
            }
            if (! empty($opciones['nombre_corregido'])) {
                $solicitud->nombre = trim((string) $opciones['nombre_corregido']);
            }
            if ($solicitud->isDirty(['cedula', 'nombre'])) {
                $solicitud->save();
            }

            $clienteVinculacionId = isset($opciones['cliente_id_vinculacion'])
                ? (int) $opciones['cliente_id_vinculacion']
                : null;

            if ($clienteVinculacionId) {
                $cliente = Cliente::query()->whereKey($clienteVinculacionId)->first();
                if (! $cliente) {
                    throw new \RuntimeException('El cliente de vinculación no existe.');
                }
            } else {
                $cliente = $this->clienteCoincidePorDocumento($solicitud->cedula);
            }

            $urlUbicacion = $this->urlDesdeCoordenadas($solicitud->latitud, $solicitud->longitud);

            if ($cliente) {
                $data = [
                    'estado' => $cliente->estado === 'inactivo' ? 'activo' : $cliente->estado,
                    'fecha_otorgamiento' => now(),
                    'aprobado_por' => $aprobador->name,
                ];

                // Solo con pre-aprobación explícita desde la app/web.
                if ($actualizarTelefono && filled($solicitud->whatsapp)) {
                    $data['telefono'] = $solicitud->whatsapp;
                }
                if ($actualizarUbicacion) {
                    if (filled($solicitud->direccion)) {
                        $data['direccion'] = $solicitud->direccion;
                    }
                    if (filled($urlUbicacion)) {
                        $data['url_ubicacion'] = $urlUbicacion;
                    }
                }

                $cliente->update($data);
            } else {
                [$nombre, $apellido] = $this->separarNombre($solicitud->nombre);
                $cliente = Cliente::create([
                    'cedula' => $solicitud->cedula,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'telefono' => $solicitud->whatsapp,
                    'direccion' => $solicitud->direccion,
                    'url_ubicacion' => $urlUbicacion,
                    'estado' => 'activo',
                    'fecha_otorgamiento' => now(),
                    'aprobado_por' => $aprobador->name,
                ]);
            }

            $clave = $this->portal->generarClavePlus();
            $sync = $this->portal->syncParaCliente($cliente->fresh(), false);
            $user = $sync['user'];
            $user->contrasena = Hash::make($clave);
            $user->estado = 'activo';
            $user->save();

            $solicitud->update([
                'estado' => SolicitudAcceso::ESTADO_APROBADA,
                'cliente_id' => $cliente->cliente_id,
                'aprobado_por' => $aprobador->usuario_id,
                'aprobado_at' => now(),
            ]);

            return [
                'solicitud' => $solicitud->fresh(['cliente', 'aprobador']),
                'clave' => $clave,
                'cliente' => $cliente->fresh(),
            ];
        });

        try {
            app(\App\Services\Loyalty\PuntosService::class)->aplicarBienvenidaUnaVez(
                (int) $result['cliente']->cliente_id,
                [
                    'created_by' => $aprobador->usuario_id ?? null,
                    'meta' => [
                        'motivo' => 'aprobacion_solicitud_acceso',
                        'solicitud_id' => $result['solicitud']->id,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::info('[Loyalty] Bienvenida omitida: '.$e->getMessage(), [
                'cliente_id' => $result['cliente']->cliente_id ?? null,
                'solicitud_id' => $result['solicitud']->id ?? null,
            ]);
        }

        $this->avisarWhatsAppResultado($result['solicitud'], 'aprobada', $result['clave']);

        return $result;
    }

    /**
     * @return array{solicitud: SolicitudAcceso}
     */
    public function rechazar(SolicitudAcceso $solicitud, User $operador, ?string $motivo = null): array
    {
        if (! in_array($solicitud->estado, [
            SolicitudAcceso::ESTADO_PENDIENTE,
            SolicitudAcceso::ESTADO_PENDIENTE_VERIFICACION,
        ], true)) {
            throw new \RuntimeException('La solicitud ya fue procesada.');
        }

        $solicitud->update([
            'estado' => SolicitudAcceso::ESTADO_RECHAZADA,
            'aprobado_por' => $operador->usuario_id,
            'aprobado_at' => now(),
        ]);

        $fresh = $solicitud->fresh(['cliente', 'aprobador']);
        $this->avisarWhatsAppResultado($fresh, 'rechazada', null, $motivo);

        return ['solicitud' => $fresh];
    }

    /**
     * Regenera clave PLUS y reenvía WhatsApp al número de la solicitud.
     *
     * @return array{solicitud: SolicitudAcceso, clave: string}
     */
    public function regenerarYReenviarClave(SolicitudAcceso $solicitud): array
    {
        if ($solicitud->estado !== SolicitudAcceso::ESTADO_APROBADA) {
            throw new \RuntimeException('Solo se puede reenviar clave de solicitudes aprobadas.');
        }

        $cliente = $solicitud->cliente ?: ($solicitud->cliente_id
            ? Cliente::query()->find($solicitud->cliente_id)
            : null);

        if (! $cliente) {
            throw new \RuntimeException('La solicitud no tiene cliente vinculado.');
        }

        $clave = $this->portal->generarClavePlus();
        $sync = $this->portal->syncParaCliente($cliente->fresh(), false);
        $user = $sync['user'];
        $user->contrasena = Hash::make($clave);
        $user->estado = 'activo';
        $user->save();

        $fresh = $solicitud->fresh(['cliente', 'aprobador']);
        $this->avisarWhatsAppResultado($fresh, 'aprobada', $clave);

        return [
            'solicitud' => $fresh,
            'clave' => $clave,
        ];
    }

    public function registrarTelemetriaLogin(Cliente $cliente, ?string $deviceName, ?string $appVersion): void
    {
        $data = [
            'ultimo_ingreso' => now(),
            'dispositivo' => $deviceName ? Str::limit($deviceName, 120, '') : $cliente->dispositivo,
            'app_version' => $appVersion ? Str::limit($appVersion, 40, '') : $cliente->app_version,
        ];

        if (! $cliente->app_activa) {
            $data['app_activa'] = true;
            $data['fecha_activacion_app'] = now();
        }

        $cliente->update($data);
    }

    /**
     * Aviso al WhatsApp declarado en la solicitud (no al teléfono del cliente en BD).
     */
    public function avisarWhatsAppResultado(
        SolicitudAcceso $solicitud,
        string $resultado,
        ?string $clave = null,
        ?string $motivo = null,
    ): void {
        $eventKey = $resultado === 'aprobada' ? 'acceso_aprobado' : 'acceso_rechazado';
        if (! (bool) config("whatsapp.events.{$eventKey}", true)) {
            return;
        }

        if (! $this->whatsapp->isConfigured()) {
            return;
        }

        // Preferir el from de Meta (abrió la ventana 24h) sobre el declarado en el form.
        $telefono = trim((string) ($solicitud->whatsapp_from ?: $solicitud->whatsapp ?? ''));
        if ($telefono === '') {
            Log::info('[WhatsApp] Solicitud sin número WhatsApp; no se avisa', [
                'solicitud_id' => $solicitud->id,
                'resultado' => $resultado,
            ]);

            return;
        }

        $nombre = trim((string) $solicitud->nombre) ?: 'cliente';

        if ($resultado === 'aprobada') {
            $claveFmt = $clave ?: '(consultar con soporte)';
            $texto = "Hola {$nombre}. Tu solicitud de acceso al Portal Interplus ha sido aprobada. "
                ."Tu contraseña temporal es: *{$claveFmt}*. Por favor, cámbiala al iniciar sesión.";
            $contexto = 'acceso_aprobado';
        } else {
            $motivoTxt = filled($motivo) ? trim((string) $motivo) : 'Sin motivo indicado';
            $texto = "Hola {$nombre}. Lamentamos informarte que tu solicitud de acceso al Portal Interplus ha sido rechazada.\n"
                ."Motivo: {$motivoTxt}\n"
                .'Por favor, contacta con soporte para más detalles.';
            $contexto = 'acceso_rechazado';
        }

        try {
            $this->whatsapp->sendText($telefono, $texto, [
                'cliente_id' => $solicitud->cliente_id,
                'contexto_tipo' => $contexto,
                'contexto_id' => $solicitud->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Aviso solicitud omitido: '.$e->getMessage(), [
                'solicitud_id' => $solicitud->id,
                'resultado' => $resultado,
            ]);
        }
    }

    private function notificarStaff(SolicitudAcceso $solicitud): void
    {
        $title = 'Nueva Solicitud de Acceso';
        $body = "{$solicitud->nombre} ha solicitado acceso al portal";

        $staff = User::query()
            ->whereNull('cliente_id')
            ->where('estado', 'activo')
            ->with('rol')
            ->get()
            ->filter(fn (User $u) => $u->esAdministrador() || $u->tienePermiso('clientes.ver'))
            ->values();

        if ($staff->isNotEmpty()) {
            Notification::send($staff, new NuevaSolicitudAccesoNotification($solicitud));
        }

        $this->fcm->notifyStaff($title, $body, [
            'tipo' => 'solicitud',
            'id' => (string) $solicitud->id,
            'solicitud_id' => (string) $solicitud->id,
            'title' => $title,
            'body' => $body,
        ]);
    }

    private function guardarFrenteBase64(?string $frente, string $cedula): ?string
    {
        if ($frente === null || trim($frente) === '') {
            return null;
        }

        $frente = trim($frente);
        $extension = 'jpg';
        $raw = $frente;

        if (preg_match('#^data:image/(\w+);base64,#i', $frente, $m)) {
            $extension = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
            $raw = substr($frente, strpos($frente, ',') + 1);
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 32) {
            throw new \InvalidArgumentException('Imagen de cédula inválida.');
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('La imagen de cédula supera 5 MB.');
        }

        $digits = ClientePortalUserService::normalizarDocumento($cedula) ?: 'sin-doc';
        $filename = 'solicitudes-acceso/'.$digits.'_'.now()->format('YmdHis').'_'.Str::random(6).'.'.$extension;
        Storage::disk('public')->put($filename, $binary);

        return $filename;
    }

    private function separarNombre(string $nombreCompleto): array
    {
        $parts = preg_split('/\s+/', trim($nombreCompleto), 2) ?: [];

        return [
            $parts[0] ?? $nombreCompleto,
            $parts[1] ?? null,
        ];
    }

    private function urlDesdeCoordenadas(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return sprintf('https://www.google.com/maps?q=%s,%s', $lat, $lng);
    }
}
