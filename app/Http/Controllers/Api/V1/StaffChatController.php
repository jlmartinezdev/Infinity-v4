<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ChatInternoException;
use App\Services\ChatInternoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffChatController extends ApiController
{
    public function __construct(private readonly ChatInternoService $chat) {}

    public function conversaciones(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->chat->listarConversaciones($request->user()));
    }

    public function usuarios(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->chat->listarUsuarios($request->user()));
    }

    public function pendientes(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->chat->pendientes($request->user()));
    }

    public function directos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario_id' => ['required', 'integer'],
        ]);

        return $this->responder(function () use ($request, $datos) {
            $resultado = $this->chat->abrirDirecto($request->user(), (int) $datos['usuario_id']);

            return $this->ok($resultado, $resultado['creada'] ? 'Conversación creada' : 'OK', $resultado['creada'] ? 201 : 200);
        });
    }

    public function grupos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'usuario_ids' => ['required', 'array', 'min:1'],
            'usuario_ids.*' => ['integer'],
        ]);

        return $this->responder(fn () => $this->ok(
            $this->chat->crearGrupo($request->user(), $datos['titulo'], $datos['usuario_ids']),
            'Grupo creado',
            201
        ));
    }

    public function mensajes(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'despues_de' => ['nullable', 'integer'],
        ]);

        return $this->responder(fn () => $this->chat->mensajes(
            $request->user(),
            $id,
            isset($datos['despues_de']) ? (int) $datos['despues_de'] : null,
        ));
    }

    public function enviar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'cuerpo' => ['nullable', 'string', 'max:4000'],
            'archivo' => ['nullable', 'file', 'max:15360'],
            'asignados' => ['nullable', 'array'],
            'asignados.*' => ['integer'],
        ]);

        return $this->responder(fn () => $this->ok($this->chat->enviar(
            $request->user(),
            $id,
            $datos['cuerpo'] ?? null,
            $request->file('archivo'),
            $request->boolean('requiere_accion'),
            $datos['asignados'] ?? [],
        ), 'Mensaje enviado', 201));
    }

    public function atender(Request $request, int $id): JsonResponse
    {
        return $this->responder(fn () => $this->chat->atender($request->user(), $id));
    }

    public function adjunto(Request $request, int $id)
    {
        try {
            $adjunto = $this->chat->adjuntoPara($request->user(), $id);
        } catch (ChatInternoException $e) {
            return $this->fail($e->getMessage(), $e->status);
        }

        $disco = Storage::disk($adjunto->disco ?: 'local');
        if (! $disco->exists($adjunto->ruta)) {
            return $this->fail('El archivo ya no está disponible.', 404);
        }

        if (str_starts_with((string) $adjunto->mime, 'image/')) {
            return response()->file($disco->path($adjunto->ruta), [
                'Content-Type' => $adjunto->mime,
                'Content-Disposition' => 'inline; filename="'.str_replace(['"', "\r", "\n"], '', $adjunto->nombre_original).'"',
            ]);
        }

        return $disco->download($adjunto->ruta, $adjunto->nombre_original);
    }

    /**
     * @param  callable(): mixed  $accion
     */
    private function responder(callable $accion): JsonResponse
    {
        try {
            $resultado = $accion();
            if ($resultado instanceof JsonResponse) {
                return $resultado;
            }

            return $this->ok($resultado);
        } catch (ChatInternoException $e) {
            return $this->fail($e->getMessage(), $e->status);
        }
    }
}
