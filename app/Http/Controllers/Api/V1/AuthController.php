<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\ClientePortalUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function __construct(
        protected ClientePortalUserService $portal,
        protected \App\Services\SolicitudAccesoService $solicitudAcceso
    ) {}

    /**
     * Login unificado.
     * - staff: usuario = email, password = contraseña
     * - cliente: usuario = documento, password = clave otorgada (PLUS****)
     */
    public function login(Request $request)
    {
        if (! $request->filled('usuario') && $request->filled('email')) {
            $request->merge(['usuario' => $request->input('email')]);
        }

        $validated = $request->validate([
            'usuario' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'in:staff,cliente'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'token' => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:40'],
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'usuario_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (empty($validated['push_token']) && ! empty($validated['token'])) {
            $validated['push_token'] = $validated['token'];
        }
        if (empty($validated['device_type']) && ! empty($validated['platform'])) {
            $validated['device_type'] = $validated['platform'];
        }

        $tipo = $validated['tipo'] ?? null;
        if ($tipo === null) {
            $tipo = str_contains($validated['usuario'], '@') ? 'staff' : 'cliente';
        }

        $user = $tipo === 'cliente'
            ? $this->portal->autenticarPorDocumento($validated['usuario'], $validated['password'])
            : $this->autenticarStaff($validated['usuario'], $validated['password']);

        if (! $user) {
            throw ValidationException::withMessages([
                'usuario' => ['Credenciales incorrectas o cuenta no disponible.'],
            ]);
        }

        if ($user->estado !== 'activo') {
            throw ValidationException::withMessages([
                'usuario' => ['Tu cuenta está pendiente de aprobación o suspendida.'],
            ]);
        }

        $user->registrarAcceso($request->ip());

        if (! empty($validated['push_token'])) {
            $this->persistirTokenPush(
                $user,
                $validated['push_token'],
                $validated['device_type'] ?? null,
                isset($validated['cliente_id']) ? (int) $validated['cliente_id'] : null,
                isset($validated['usuario_id']) ? (int) $validated['usuario_id'] : null
            );
        }

        if ($tipo === 'cliente' && $user->cliente_id) {
            $cliente = $user->cliente ?: $user->cliente()->first();
            if ($cliente) {
                $this->solicitudAcceso->registrarTelemetriaLogin(
                    $cliente,
                    $validated['device_name'] ?? null,
                    $validated['app_version'] ?? null
                );
            }
        }

        $device = $validated['device_name'] ?? ($tipo.'-app');
        $token = $user->createToken($device)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user->fresh(['rol', 'cliente'])),
        ], 'Inicio de sesión exitoso');
    }

    public function me(Request $request)
    {
        return $this->ok([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            // Evita seguir notificando un dispositivo que cerró sesión
            $user->push_token = null;
            $user->save();
        }

        $token = $user?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return $this->ok(null, 'Sesión cerrada');
    }

    /**
     * POST /api/v1/portal/save-push-token  (cliente)
     * POST /api/v1/staff/save-push-token   (staff) — mismo body
     */
    public function savePushToken(Request $request)
    {
        if (! $request->filled('push_token') && $request->filled('token')) {
            $request->merge(['push_token' => $request->input('token')]);
        }
        if (! $request->filled('device_type') && $request->filled('platform')) {
            $request->merge(['device_type' => $request->input('platform')]);
        }

        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:512'],
            'token' => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:40'],
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'usuario_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $this->persistirTokenPush(
            $user,
            $validated['push_token'],
            $validated['device_type'] ?? null,
            isset($validated['cliente_id']) ? (int) $validated['cliente_id'] : null,
            isset($validated['usuario_id']) ? (int) $validated['usuario_id'] : null
        );

        return $this->ok([
            'usuario_id' => $user->usuario_id,
            'cliente_id' => $user->cliente_id,
            'device_type' => $user->device_type,
        ], 'Push token guardado');
    }

    /**
     * Guarda el FCM en el usuario de la sesión, indexado por users.cliente_id
     * (el panel “Seleccionados” busca WHERE cliente_id IN (…)).
     *
     * cliente_id / usuario_id del body son informativos: no se cambia de cuenta.
     */
    private function persistirTokenPush(
        User $user,
        string $token,
        ?string $deviceType,
        ?int $clienteIdBody,
        ?int $usuarioIdBody
    ): void {
        if ($usuarioIdBody && (int) $usuarioIdBody !== (int) $user->usuario_id) {
            Log::info('save-push-token: usuario_id del body distinto de la sesión (se ignora)', [
                'sesion_usuario_id' => $user->usuario_id,
                'body_usuario_id' => $usuarioIdBody,
            ]);
        }

        $user->push_token = $token;
        if ($deviceType !== null && trim($deviceType) !== '') {
            $user->device_type = strtolower(trim($deviceType));
        }

        if ($user->esClientePortal() && $clienteIdBody && (int) $user->cliente_id !== $clienteIdBody) {
            Log::warning('save-push-token: cliente_id del body no coincide con users.cliente_id (se conserva el de la sesión)', [
                'usuario_id' => $user->usuario_id,
                'cliente_id_sesion' => $user->cliente_id,
                'cliente_id_body' => $clienteIdBody,
            ]);
        }

        $user->save();
    }

    private function autenticarStaff(string $usuario, string $password): ?User
    {
        $user = User::where('email', $usuario)
            ->whereNull('cliente_id')
            ->first();

        if (! $user || ! Hash::check($password, $user->contrasena)) {
            return null;
        }

        return $user;
    }
}
