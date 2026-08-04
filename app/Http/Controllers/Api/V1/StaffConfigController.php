<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

/**
 * Configuración runtime para la app ISP Staff (sin hardcodear secrets en el APK).
 */
class StaffConfigController extends ApiController
{
    /**
     * GET /api/v1/staff/config/maps
     * Misma key JS que el panel web (GOOGLE_MAPS_API_KEY / Maps JavaScript API).
     */
    public function maps(): JsonResponse
    {
        return $this->ok($this->mapsPayload());
    }

    /**
     * GET /api/v1/staff/config — bundle con maps (fallback de la app).
     */
    public function index(): JsonResponse
    {
        $maps = $this->mapsPayload();

        return $this->ok([
            'maps' => $maps,
            'google_maps_api_key' => $maps['google_maps_api_key'],
            'maps_api_key' => $maps['maps_api_key'],
            'map_id' => $maps['map_id'],
        ]);
    }

    /**
     * @return array{google_maps_api_key: string|null, maps_api_key: string|null, api_key: string|null, map_id: string|null}
     */
    public static function mapsPayload(): array
    {
        $key = config('services.google.maps_key');
        $key = is_string($key) && trim($key) !== '' ? trim($key) : null;
        $mapId = config('services.google.maps_map_id');
        $mapId = is_string($mapId) && trim($mapId) !== '' ? trim($mapId) : null;

        return [
            'google_maps_api_key' => $key,
            'maps_api_key' => $key,
            'api_key' => $key,
            'map_id' => $mapId,
        ];
    }
}
