<?php

namespace App\Http\Controllers;

use App\Services\Monitoreo\WidgetRedEstadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class WidgetRedPreviewController extends Controller
{
    public function index(WidgetRedEstadoService $estado): View
    {
        return view('sistema.widget-red', [
            'payload' => $estado->payload(),
            'urlDatos' => route('sistema.widget-red.datos'),
        ]);
    }

    public function datos(WidgetRedEstadoService $estado): JsonResponse
    {
        return response()
            ->json($estado->payload())
            ->header('Cache-Control', 'no-store');
    }
}
