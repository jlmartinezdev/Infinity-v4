<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\NodoApWireless;
use App\Services\Monitoreo\ApWirelessPingStatusService;
use App\Services\Monitoreo\PingExecutor;
use App\Services\Ubnt\UbntAntenaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NodoApWirelessController extends Controller
{
    public function index(Request $request): View
    {
        return view('sistema.aps-wireless', [
            'config' => $this->payload($request),
        ]);
    }

    public function datos(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validar($request);
        $ap = NodoApWireless::create($validated);
        $ap->load('nodo');

        return response()->json([
            'success' => true,
            'message' => 'AP registrado.',
            'ap' => $ap->toArrayVista(),
        ], 201);
    }

    public function update(Request $request, NodoApWireless $ap): JsonResponse
    {
        $validated = $this->validar($request, $ap);
        $ap->update($validated);
        $ap->load('nodo');

        return response()->json([
            'success' => true,
            'message' => 'AP actualizado.',
            'ap' => $ap->toArrayVista(),
        ]);
    }

    public function destroy(NodoApWireless $ap): JsonResponse
    {
        $ap->delete();

        return response()->json([
            'success' => true,
            'message' => 'AP eliminado.',
        ]);
    }

    public function ping(NodoApWireless $ap, PingExecutor $ping, ApWirelessPingStatusService $status): JsonResponse
    {
        $ipInvalida = ! $ping->ipEsPinguable($ap->ip);
        $result = $ipInvalida
            ? ['ok' => false, 'latency_ms' => null, 'error' => 'IP inválida']
            : $ping->ping($ap->ip);

        $status->aplicarResultado($ap, $result, $ipInvalida);

        return response()->json([
            'success' => true,
            'ap' => $ap->fresh('nodo')?->toArrayVista(),
            'ok' => $result['ok'],
            'latency_ms' => $result['latency_ms'],
            'error' => $result['error'],
        ]);
    }

    public function ssh(NodoApWireless $ap, UbntAntenaService $ubnt): JsonResponse
    {
        set_time_limit(60);

        $datos = $ubnt->consultarApAirOs($ap->ip);
        $this->aplicarSsh($ap, $datos);

        return response()->json([
            'success' => (bool) ($datos['success'] ?? false),
            'message' => $datos['message'] ?? null,
            'ap' => $ap->fresh('nodo')?->toArrayVista(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $user = $request->user();
        $aps = NodoApWireless::query()
            ->with('nodo')
            ->orderBy('nodo_id')
            ->orderBy('nombre')
            ->get()
            ->map(fn (NodoApWireless $ap) => $ap->toArrayVista())
            ->values()
            ->all();

        $nodos = Nodo::query()
            ->orderByDesc('tecnologia_wireless')
            ->orderBy('descripcion')
            ->get()
            ->map(fn (Nodo $n) => $n->toArraySelect())
            ->values()
            ->all();

        return [
            'aps' => $aps,
            'nodos' => $nodos,
            'urlBase' => url('/sistema/aps-wireless'),
            'canCrear' => $user?->tienePermiso('sistema-aps-wireless.crear') ?? false,
            'canEditar' => $user?->tienePermiso('sistema-aps-wireless.editar') ?? false,
            'canEliminar' => $user?->tienePermiso('sistema-aps-wireless.eliminar') ?? false,
            'urlAvisos' => ($user?->esAdministrador() && $user->tienePermiso('sistema-aps-wireless-avisos.ver'))
                ? url('/sistema/aps-wireless-avisos')
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function aplicarSsh(NodoApWireless $ap, array $datos): void
    {
        $ok = (bool) ($datos['success'] ?? false);
        $ap->ssh_at = now();
        $ap->ssh_error = $ok ? null : $this->recortar((string) ($datos['message'] ?? 'Error SSH'), 255);

        if ($ok) {
            $ap->hostname = $this->recortar($datos['hostname'] ?? $ap->hostname, 120);
            $ap->ssid = $this->recortar($datos['ssid'] ?? $ap->ssid, 120);
            $ap->modo = $this->recortar($datos['modo'] ?? $ap->modo, 40);
            $ap->frecuencia = $this->recortar($datos['frecuencia'] ?? $ap->frecuencia, 20);
            $ap->canal = $this->recortar($datos['canal'] ?? $ap->canal, 20);
            $ap->chanbw = $this->recortar($datos['chanbw'] ?? $ap->chanbw, 20);
            $ap->firmware = $this->recortar($datos['firmware'] ?? $ap->firmware, 80);
            $ap->modelo = $this->recortar($datos['modelo'] ?? $ap->modelo, 80);
            $ap->mac = $this->recortar($datos['mac'] ?? $ap->mac, 32);
            $ap->uptime_segundos = $datos['uptime_segundos'] ?? $ap->uptime_segundos;
            $ap->estaciones = $datos['estaciones'] ?? $ap->estaciones;
            $ap->extra = $datos['extra'] ?? $ap->extra;
        }

        $ap->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?NodoApWireless $ap = null): array
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'integer', 'exists:nodos,nodo_id'],
            'nombre' => ['required', 'string', 'max:120'],
            'ip' => [
                'required',
                'ipv4',
                Rule::unique('nodo_aps_wireless', 'ip')->ignore($ap?->ap_id, 'ap_id'),
            ],
            'activo' => ['sometimes', 'boolean'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['activo'] = $request->boolean('activo', true);

        return $validated;
    }

    private function recortar(mixed $valor, int $max): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, $max);
    }
}
