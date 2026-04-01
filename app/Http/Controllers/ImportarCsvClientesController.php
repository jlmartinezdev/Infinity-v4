<?php

namespace App\Http\Controllers;

use App\Services\ImportarCsvClientesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ImportarCsvClientesController extends Controller
{
    /**
     * Muestra la pantalla de importación desde CSV.
     */
    public function index(): View
    {
        return view('clientes.importar-csv');
    }

    /**
     * Procesa el archivo CSV subido.
     */
    public function store(Request $request, ImportarCsvClientesService $service): JsonResponse
    {
        set_time_limit(120);

        $validated = $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $file = $validated['archivo'];
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        $result = $service->procesar($file, $dryRun);

        $success = ($result['errores'] ?? 0) === 0;

        return response()->json([
            'success' => $success,
            'message' => $dryRun
                ? 'Simulación completada. Ningún dato fue guardado.'
                : ($success ? 'Importación completada correctamente.' : "Importación completada con {$result['errores']} error(es)."),
            'result' => $result,
        ]);
    }
}
