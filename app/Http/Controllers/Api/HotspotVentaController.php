<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotspotPlan;
use App\Services\Hotspot\HotspotVentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class HotspotVentaController extends Controller
{
    public function planes(HotspotVentaService $ventas): JsonResponse
    {
        return $this->json(['planes' => $ventas->catalogoPublico()]);
    }

    public function comprar(Request $request, HotspotVentaService $ventas): JsonResponse
    {
        try {
            $datos = $request->validate([
                'plan_id' => ['required', 'integer'],
                'mac' => ['nullable', 'string', 'max:32'],
            ]);

            $plan = HotspotPlan::query()->where('activo', true)->find($datos['plan_id']);
            if (! $plan) {
                throw ValidationException::withMessages([
                    'plan_id' => ['Ese plan no está a la venta.'],
                ]);
            }

            $compra = $ventas->iniciar($plan, $datos['mac'] ?? null);
        } catch (ValidationException $e) {
            $mensaje = collect($e->errors())->flatten()->first() ?: 'Revisá el plan elegido.';

            return $this->json(['mensaje' => $mensaje], 422);
        } catch (Throwable $e) {
            Log::warning('[Hotspot venta] no se pudo abrir el pago', ['error' => $e->getMessage()]);

            return $this->json([
                'mensaje' => 'No se pudo abrir el pago. Intentá de nuevo en un momento.',
            ], 502);
        }

        return $this->json($compra);
    }

    public function estado(string $token, HotspotVentaService $ventas): JsonResponse
    {
        $estado = $ventas->estadoPublico($token);
        if ($estado === null) {
            return $this->json(['mensaje' => 'No está esa compra.'], 404);
        }

        return $this->json($estado);
    }

    public function opciones(): JsonResponse
    {
        return $this->json(null, 204);
    }

    private function json(mixed $data, int $status = 200): JsonResponse
    {
        $respuesta = response()->json($data, $status);

        return $respuesta
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Cache-Control', 'no-store');
    }
}
