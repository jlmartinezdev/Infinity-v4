<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Monitoreo\WidgetRedEstadoService;
use Illuminate\Http\JsonResponse;

class WidgetRedController extends Controller
{
    public function show(WidgetRedEstadoService $estado): JsonResponse
    {
        return response()
            ->json($estado->payload())
            ->header('Cache-Control', 'no-store');
    }
}
