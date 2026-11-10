<?php

namespace App\Http\Controllers;

use App\Exceptions\ChatInternoException;
use App\Services\ChatInternoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatInternoController extends Controller
{
    public function __construct(private readonly ChatInternoService $chat) {}

    public function index(Request $request)
    {
        return view('chat.index', [
            'config' => [
                'usuarioId' => (int) $request->user()->usuario_id,
                'abrir' => $request->integer('c') ?: null,
                'urls' => [
                    'conversaciones' => route('chat.conversaciones'),
                    'usuarios' => route('chat.usuarios'),
                    'directos' => route('chat.directos'),
                    'grupos' => route('chat.grupos'),
                    'mensajes' => url('/chat/conversaciones'),
                    'atender' => url('/chat/mensajes'),
                ],
            ],
        ]);
    }

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

            return response()->json($resultado, $resultado['creada'] ? 201 : 200);
        });
    }

    public function grupos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'usuario_ids' => ['required', 'array', 'min:1'],
            'usuario_ids.*' => ['integer'],
        ]);

        return $this->responder(fn () => response()->json(
            $this->chat->crearGrupo($request->user(), $datos['titulo'], $datos['usuario_ids']),
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

        return $this->responder(fn () => response()->json($this->chat->enviar(
            $request->user(),
            $id,
            $datos['cuerpo'] ?? null,
            $request->file('archivo'),
            $request->boolean('requiere_accion'),
            $datos['asignados'] ?? [],
        ), 201));
    }

    public function atender(Request $request, int $id): JsonResponse
    {
        return $this->responder(fn () => $this->chat->atender($request->user(), $id));
    }

    public function adjunto(Request $request, int $id): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        try {
            $adjunto = $this->chat->adjuntoPara($request->user(), $id);
        } catch (ChatInternoException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $disco = Storage::disk($adjunto->disco ?: 'local');
        if (! $disco->exists($adjunto->ruta)) {
            return response()->json(['message' => 'El archivo ya no está disponible.'], 404);
        }

        if (str_starts_with((string) $adjunto->mime, 'image/')) {
            return response()->file($disco->path($adjunto->ruta), [
                'Content-Type' => $adjunto->mime,
                'Content-Disposition' => 'inline; filename="'.$this->nombreDescarga($adjunto->nombre_original).'"',
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

            return response()->json($resultado);
        } catch (ChatInternoException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }

    private function nombreDescarga(string $nombre): string
    {
        return str_replace(['"', "\r", "\n"], '', $nombre);
    }
}
