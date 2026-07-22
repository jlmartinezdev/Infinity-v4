<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\SolicitudAcceso;
use App\Models\User;
use App\Notifications\NuevaSolicitudAccesoNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudAccesoService
{
    public function __construct(
        protected ClientePortalUserService $portal,
        protected FcmPushService $fcm
    ) {}

    public function crear(array $data): SolicitudAcceso
    {
        $frentePath = $this->guardarFrenteBase64($data['frente'] ?? null, $data['cedula']);

        $solicitud = SolicitudAcceso::create([
            'cedula' => trim((string) $data['cedula']),
            'nombre' => trim((string) $data['nombre']),
            'whatsapp' => isset($data['whatsapp']) ? trim((string) $data['whatsapp']) : null,
            'direccion' => isset($data['direccion']) ? trim((string) $data['direccion']) : null,
            'latitud' => $data['latitud'] ?? null,
            'longitud' => $data['longitud'] ?? null,
            'frente_path' => $frentePath,
            'estado' => SolicitudAcceso::ESTADO_PENDIENTE,
        ]);

        $this->notificarStaff($solicitud);

        return $solicitud;
    }

    public function clienteCoincidePorDocumento(string $cedula): ?Cliente
    {
        return $this->portal->buscarClientePorDocumento($cedula);
    }

    /**
     * @return array{solicitud: SolicitudAcceso, clave: string, cliente: Cliente}
     */
    public function aprobar(SolicitudAcceso $solicitud, User $aprobador): array
    {
        if ($solicitud->estado !== SolicitudAcceso::ESTADO_PENDIENTE) {
            throw new \RuntimeException('La solicitud ya fue procesada.');
        }

        return DB::transaction(function () use ($solicitud, $aprobador) {
            $cliente = $this->clienteCoincidePorDocumento($solicitud->cedula);
            $urlUbicacion = $this->urlDesdeCoordenadas($solicitud->latitud, $solicitud->longitud);

            if ($cliente) {
                $cliente->update([
                    'telefono' => $solicitud->whatsapp ?: $cliente->telefono,
                    'direccion' => $solicitud->direccion ?: $cliente->direccion,
                    'url_ubicacion' => $urlUbicacion ?: $cliente->url_ubicacion,
                    'estado' => $cliente->estado === 'inactivo' ? 'activo' : $cliente->estado,
                ]);
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
            'tipo' => 'solicitud_acceso',
            'solicitud_id' => (string) $solicitud->id,
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
