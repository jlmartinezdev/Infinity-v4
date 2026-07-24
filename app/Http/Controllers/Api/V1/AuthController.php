<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\ClientePortalUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
     * - cliente: usuario = documento, password = PLUS**** (o documento legacy)
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'usuario' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'in:staff,cliente'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'push_token' => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'max:40'],
        ]);

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
            $user->push_token = $validated['push_token'];
            if (! empty($validated['device_type'])) {
                $user->device_type = strtolower(trim((string) $validated['device_type']));
            }
            $user->save();
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
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:512'],
            'device_type' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $user->push_token = $validated['push_token'];
        $user->device_type = isset($validated['device_type'])
            ? strtolower(trim((string) $validated['device_type']))
            : $user->device_type;
        $user->save();

        return $this->ok([
            'usuario_id' => $user->usuario_id,
            'cliente_id' => $user->cliente_id,
            'device_type' => $user->device_type,
        ], 'Push token guardado');
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
