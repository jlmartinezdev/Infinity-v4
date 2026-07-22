<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MapsUrlHelper
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Indica si la URL es un enlace corto de Google Maps que requiere seguir redirecciones.
     */
    public static function isShortMapsUrl(string $url): bool
    {
        return (bool) preg_match(
            '#^https?://(maps\.app\.goo\.gl|goo\.gl/maps)/#i',
            trim($url)
        );
    }

    /**
     * Resuelve una URL corta de Google Maps siguiendo redirecciones y devuelve la URL final.
     * Usa User-Agent de navegador para que Google devuelva la misma redirección que en el navegador.
     * Devuelve null si falla la petición o no se obtiene una URL válida.
     */
    public static function resolveShortMapsUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        return Cache::remember('maps_short_url:'.md5($url), 86400, function () use ($url) {
            try {
                $response = Http::withOptions([
                    'allow_redirects' => true,
                    'timeout' => 15,
                ])
                    ->withUserAgent(self::USER_AGENT)
                    ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                    ->get($url);

                $uri = $response->effectiveUri();

                return $uri !== null ? (string) $uri : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Extrae lat/lon de una URL corta de Google Maps (maps.app.goo.gl, goo.gl/maps).
     * Sigue la redirección HTTP (con caché) y parsea coordenadas de la URL final.
     *
     * @return array{lat: float|null, lon: float|null, resolved_url: string|null}
     */
    public static function extractLatLonFromShortMapsUrl(?string $url): array
    {
        $vacío = ['lat' => null, 'lon' => null, 'resolved_url' => null];
        $url = trim((string) $url);

        if ($url === '' || ! self::isShortMapsUrl($url)) {
            return $vacío;
        }

        $resolved = self::resolveShortMapsUrl($url);
        if ($resolved === null) {
            return $vacío;
        }

        $coords = self::parseLatLonFromUrlString($resolved);

        return [
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'resolved_url' => $resolved,
        ];
    }

    /**
     * Extrae latitud y longitud de una URL de Google Maps (o texto con coordenadas).
     * Si $resolveShortUrl es true y la URL es corta, delega en extractLatLonFromShortMapsUrl().
     *
     * @return array{lat: float|null, lon: float|null}
     */
    public static function extractLatLonFromMapsUrl(?string $url, bool $resolveShortUrl = true): array
    {
        if ($url === null || trim($url) === '') {
            return ['lat' => null, 'lon' => null];
        }

        $url = trim($url);

        if ($resolveShortUrl && self::isShortMapsUrl($url)) {
            $fromShort = self::extractLatLonFromShortMapsUrl($url);

            return [
                'lat' => $fromShort['lat'],
                'lon' => $fromShort['lon'],
            ];
        }

        return self::parseLatLonFromUrlString($url);
    }

    /**
     * Intenta obtener coordenadas: parse directo y, si falla, resolución de URL corta.
     *
     * @return array{lat: float|null, lon: float|null}
     */
    public static function extractLatLon(?string $url): array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return ['lat' => null, 'lon' => null];
        }

        $coords = self::parseLatLonFromUrlString($url);
        if ($coords['lat'] !== null && $coords['lon'] !== null) {
            return $coords;
        }

        if (self::isShortMapsUrl($url)) {
            $fromShort = self::extractLatLonFromShortMapsUrl($url);

            return [
                'lat' => $fromShort['lat'],
                'lon' => $fromShort['lon'],
            ];
        }

        return $coords;
    }

    /**
     * Parsea coordenadas de una URL ya resuelta o texto "lat, lon" (sin seguir redirecciones).
     *
     * @return array{lat: float|null, lon: float|null}
     */
    private static function parseLatLonFromUrlString(string $url): array
    {
        $result = ['lat' => null, 'lon' => null];
        $url = trim($url);

        if ($url === '') {
            return $result;
        }

        // Formato ?ll=lat,lon o &ll=lat,lon
        if (preg_match('/[?&]ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        // Formato /search/lat,+lon o /search/lat,lon (URL resuelta de maps.app.goo.gl)
        if (preg_match('#/search/(-?\d+\.?\d*)\s*,\s*\+?(-?\d+\.?\d*)#', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        // Formato @lat,lon (ej. /@-25.123,-54.456,15z o /place/.../@-25.123,-54.456)
        if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        // Formato !3dLAT!4dLON (Google Maps place)
        if (preg_match('/!3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        // Formato ?q=lat,lon o &q=lat,lon
        if (preg_match('/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        // Formato ?query=lat,lon o &query=lat,lon (Google Maps search)
        if (preg_match('/[?&]query=(-?\d+\.?\d*)%2C(-?\d+\.?\d*)/', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }
        if (preg_match('/[?&]query=(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        // Texto que parezca "lat, lon" (ej. -25.123, -54.456)
        if (preg_match('/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/u', $url, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            if (self::isValidLatLon($lat, $lon)) {
                $result['lat'] = $lat;
                $result['lon'] = $lon;

                return $result;
            }
        }

        return $result;
    }

    protected static function isValidLatLon(float $lat, float $lon): bool
    {
        return $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180;
    }

    /**
     * URL absoluta de Google Maps a partir de coordenadas o texto/URL guardado en pedido.
     */
    public static function toGoogleMapsUrl(?string $mapsGps = null, ?float $lat = null, ?float $lon = null): ?string
    {
        if ($lat !== null && $lon !== null && self::isValidLatLon($lat, $lon)) {
            return 'https://www.google.com/maps?q='.$lat.','.$lon;
        }

        $gps = trim((string) ($mapsGps ?? ''));
        if ($gps === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $gps)) {
            return $gps;
        }

        $extracted = self::extractLatLon($gps);
        if ($extracted['lat'] !== null && $extracted['lon'] !== null) {
            return 'https://www.google.com/maps?q='.$extracted['lat'].','.$extracted['lon'];
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($gps);
    }
}
