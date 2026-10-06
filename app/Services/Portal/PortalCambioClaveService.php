<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\User;
use App\Services\ClientePushNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de clave de ingreso a la app (PLUS**** → elegida por el cliente).
 * Registra ticket resuelto + push opcional por preferencia del cliente.
 */
class PortalCambioClaveService
{
    public const ASUNTO_NOMBRE = PortalAccionTicketService::ASUNTO_CLAVE_APP;

    public const CLAVE_MIN = 6;

    public const CLAVE_MAX = 64;

    public function __construct(
        private readonly PortalFeatureFlagsService $flags,
        private readonly PortalAccionTicketService $accionTickets,
        private readonly ClientePushNotifier $push,
    ) {}

    public function flagHabilitado(): bool
    {
        foreach ($this->flags->flags() as $flag) {
            if (($flag['key'] ?? '') === 'cambio_clave_app') {
                return ($flag['state'] ?? '') === 'enabled';
            }
        }

        return false;
    }

    /**
     * @return array{
     *   debe_cambiar_clave_app: bool,
     *   push_cambio_clave: bool,
     *   ticket: array{id: int, estado: string, fecha_cierre: string|null, asunto: string},
     *   push_enviado: bool
     * }
     */
    public function cambiar(User $user, string $claveActual, string $claveNueva): array
    {
        if (! $this->flagHabilitado()) {
            throw ValidationException::withMessages([
                'clave_nueva' => ['La función de cambio de clave no está disponible.'],
            ]);
        }

        if (! $user->esClientePortal() || ! $user->cliente_id) {
            throw ValidationException::withMessages([
                'clave_nueva' => ['Solo clientes de la app pueden cambiar la clave.'],
            ]);
        }

        $claveActual = trim($claveActual);
        $claveNueva = trim($claveNueva);

        if (mb_strlen($claveNueva) < self::CLAVE_MIN || mb_strlen($claveNueva) > self::CLAVE_MAX) {
            throw ValidationException::withMessages([
                'clave_nueva' => ['La nueva clave debe tener entre '.self::CLAVE_MIN.' y '.self::CLAVE_MAX.' caracteres.'],
            ]);
        }

        if ($claveActual === $claveNueva) {
            throw ValidationException::withMessages([
                'clave_nueva' => ['La nueva clave debe ser distinta a la actual.'],
            ]);
        }

        $hash = (string) ($user->contrasena ?? '');
        if ($hash === '' || ! Hash::check($claveActual, $hash)) {
            throw ValidationException::withMessages([
                'clave_actual' => ['La clave actual no es correcta.'],
            ]);
        }

        /** @var Cliente $cliente */
        $cliente = $user->cliente()->firstOrFail();

        $ticket = null;
        DB::transaction(function () use ($user, $cliente, $claveNueva, &$ticket) {
            $user->contrasena = Hash::make($claveNueva);
            $user->save();

            $cliente->debe_cambiar_clave_app = false;
            $cliente->save();

            $ticket = $this->accionTickets->crearResuelto(
                $cliente,
                PortalAccionTicketService::ASUNTO_CLAVE_APP,
                'Cambio de contraseña de ingreso a la app realizado por el cliente desde la aplicación.',
                [
                    'accion' => 'cambio_clave_app',
                    'usuario_id' => $user->usuario_id,
                ],
                (int) $user->usuario_id,
                'Resuelto automáticamente: cambio de clave confirmado por la app.'
            );
        });

        if (! $ticket) {
            throw ValidationException::withMessages([
                'clave_nueva' => ['La clave se actualizó pero no se pudo registrar el ticket. Contactá soporte.'],
            ]);
        }

        $pushEnviado = false;
        if ($cliente->push_cambio_clave !== false) {
            $pushEnviado = $this->push->cambioClaveApp($cliente, $ticket);
        }

        return [
            'debe_cambiar_clave_app' => false,
            'push_cambio_clave' => (bool) ($cliente->push_cambio_clave ?? true),
            'ticket' => $this->accionTickets->payload($ticket, PortalAccionTicketService::ASUNTO_CLAVE_APP),
            'push_enviado' => $pushEnviado,
        ];
    }

    /**
     * @return array{debe_cambiar_clave_app: bool, push_cambio_clave: bool}
     */
    public function preferencias(Cliente $cliente): array
    {
        return [
            'debe_cambiar_clave_app' => (bool) ($cliente->debe_cambiar_clave_app ?? false),
            'push_cambio_clave' => (bool) ($cliente->push_cambio_clave ?? true),
        ];
    }

    /**
     * @param  array{push_cambio_clave?: bool}  $data
     * @return array{debe_cambiar_clave_app: bool, push_cambio_clave: bool}
     */
    public function actualizarPreferencias(Cliente $cliente, array $data): array
    {
        if (array_key_exists('push_cambio_clave', $data)) {
            $cliente->push_cambio_clave = (bool) $data['push_cambio_clave'];
            $cliente->save();
        }

        return $this->preferencias($cliente->fresh());
    }
}
