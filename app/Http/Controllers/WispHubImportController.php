<?php

namespace App\Http\Controllers;

use App\Services\WispHubApiService;
use App\Services\WispHubImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WispHubImportController extends Controller
{
    /**
     * Muestra la pantalla de importación desde WispHub.
     */
    public function index(WispHubImportService $importService): View
    {
        $wisphub = app(\App\Services\WispHubApiService::class);
        return view('sistema.importar-wisphub.index', [
            'configured' => $wisphub->isConfigured(),
            'baseUrl' => config('services.wisphub.base_url', ''),
        ]);
    }

    /**
     * Ejecuta la importación (AJAX). Máximo 500 por petición para evitar timeouts.
     */
    public function run(Request $request, WispHubImportService $importService): JsonResponse
    {
        set_time_limit(120);
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max' => ['nullable', 'integer', 'min:1', 'max:500'],
            'estado' => ['nullable', 'integer', 'in:1,2,3,4'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $options = [
            'limit' => $validated['limit'] ?? 50,
            'max' => $validated['max'] ?? 200,
            'dry_run' => (bool) ($validated['dry_run'] ?? false),
        ];
        if (isset($validated['estado'])) {
            $options['estado'] = (int) $validated['estado'];
        }

        $result = $importService->run($options);

        if (! ($result['configured'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'WispHub no configurado',
                'result' => $result,
            ], 400);
        }

        return response()->json([
            'success' => ($result['errores'] ?? 0) === 0,
            'message' => ($result['errores'] ?? 0) > 0
                ? "Importación completada con {$result['errores']} error(es)."
                : 'Importación completada correctamente.',
            'result' => $result,
        ]);
    }

    /**
     * Exporta todos los clientes de WispHub a un archivo CSV con datos crudos (sin formatear).
     */
    public function exportarExcel(Request $request, WispHubApiService $wisphub): StreamedResponse|JsonResponse
    {
        if (! $wisphub->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'WispHub no está configurado. Configure WISPHUB_API_KEY en .env',
            ], 400);
        }

        $estado = $request->query('estado');
        $estadoInt = $estado !== null && $estado !== '' ? (int) $estado : null;
        if ($estadoInt !== null && ! in_array($estadoInt, [1, 2, 3, 4], true)) {
            $estadoInt = null;
        }

        $clientes = $wisphub->getAllClientes($estadoInt);

        $filename = 'clientes-wisphub-raw-' . date('Y-m-d-His') . '.csv';

        return new StreamedResponse(function () use ($clientes) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM para Excel

            $allKeys = [];
            $flatItems = [];
            foreach ($clientes as $item) {
                $flat = $this->aplanarArray($item);
                $flatItems[] = $flat;
                $allKeys = array_unique(array_merge($allKeys, array_keys($flat)));
            }
            sort($allKeys);

            fputcsv($output, $allKeys, ';');

            foreach ($flatItems as $flat) {
                $row = [];
                foreach ($allKeys as $key) {
                    $row[] = $flat[$key] ?? '';
                }
                fputcsv($output, $row, ';');
            }

            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Aplana un array recursivamente. Valores escalares se pasan tal cual.
     * Arrays/objetos anidados se convierten a JSON.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $prefix
     * @return array<string, string>
     */
    private function aplanarArray(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? $prefix . '_' . $key : $key;
            if (is_array($value) && ! $this->esListaIndexada($value)) {
                $result = array_merge($result, $this->aplanarArray($value, $fullKey));
            } else {
                $result[$fullKey] = is_array($value) || is_object($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : (string) $value;
            }
        }
        return $result;
    }

    private function esListaIndexada(array $arr): bool
    {
        if (empty($arr)) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
