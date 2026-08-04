<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Staff\StaffPedidoInstalacionService;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function fail(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function userPayload(User $user): array
    {
        $user->loadMissing(['rol', 'cliente']);

        return [
            'usuario_id' => $user->usuario_id,
            'name' => $user->name,
            'email' => $user->email,
            'estado' => $user->estado,
            'tipo' => $user->esClientePortal() ? 'cliente' : 'staff',
            'cliente_id' => $user->cliente_id,
            'rol' => $user->rol ? [
                'rol_id' => $user->rol->rol_id,
                'descripcion' => $user->rol->descripcion,
            ] : null,
            // Lista legacy (checkboxes Infinity). Flags tipados para app ISP Staff.
            'permisos' => is_array($user->permisos) ? $user->permisos : [],
            'permisos_flags' => $user->esClientePortal()
                ? []
                : StaffPedidoInstalacionService::permisosFlags($user),
            'es_administrador' => $user->esAdministrador(),
            'cliente' => $user->cliente ? [
                'cliente_id' => $user->cliente->cliente_id,
                'cedula' => $user->cliente->cedula,
                'nombre' => $user->cliente->nombre,
                'apellido' => $user->cliente->apellido,
                'email' => $user->cliente->email,
                'telefono' => $user->cliente->telefono,
                'direccion' => $user->cliente->direccion,
                'estado' => $user->cliente->estado,
            ] : null,
        ];
    }
}
