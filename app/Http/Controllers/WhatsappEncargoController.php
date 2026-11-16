<?php

namespace App\Http\Controllers;

use App\Exceptions\WhatsappEncargoException;
use App\Services\WhatsApp\WhatsappEncargoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappEncargoController extends Controller
{
    public function __construct(private readonly WhatsappEncargoService $encargos) {}

    public function show(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->encargos->estado($request->user(), (string) $request->input('tel')));
    }

    public function index(Request $request): JsonResponse
    {
        return $this->responder(fn () => ['encargos' => $this->encargos->activos($request->user())]);
    }

    public function staff(Request $request): JsonResponse
    {
        return $this->responder(fn () => ['usuarios' => $this->encargos->staff($request->user())]);
    }

    public function tomar(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->encargos->encargarse($request->user(), (string) $request->input('telefono')));
    }

    public function soltar(Request $request): JsonResponse
    {
        return $this->responder(fn () => $this->encargos->soltar($request->user(), (string) $request->input('telefono')));
    }

    public function transferir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'telefono' => ['required', 'string', 'max:40'],
            'usuario_id' => ['required', 'integer'],
        ]);

        return $this->responder(fn () => $this->encargos->transferir(
            $request->user(),
            $datos['telefono'],
            (int) $datos['usuario_id'],
        ));
    }

    /**
     * @param  callable(): array<string, mixed>  $accion
     */
    private function responder(callable $accion): JsonResponse
    {
        try {
            return response()->json($accion());
        } catch (WhatsappEncargoException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }
}
