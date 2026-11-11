<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RadiusHotspotSyncService;
use App\Support\HotspotSesionOrigen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotspotCuentaMacController extends Controller
{
    /**
     * El portal cautivo pide la cuenta cuando el router entró por MAC.
     * Solo responde si esa MAC tiene la sesión abierta, y no devuelve el PIN.
     */
    public function __invoke(Request $request, RadiusHotspotSyncService $radius): JsonResponse
    {
        $mac = trim((string) $request->query('mac', ''));
        $username = null;
        if (HotspotSesionOrigen::esMac($mac)) {
            $cuenta = $radius->usuariosRealesPorMacs([$mac], true);
            $username = $cuenta[HotspotSesionOrigen::macNormalizada($mac)] ?? null;
        }

        return response()
            ->json(['username' => $username])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'no-store');
    }
}
