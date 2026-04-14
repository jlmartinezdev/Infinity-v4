<?php

namespace App\Helpers;

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
    }

    /**
     * Extrae latitud y longitud de una URL de Google Maps (o texto con coordenadas).
     * Soporta formatos:
     * - https://maps.app.goo.gl/XXXX (URL corta; se resuelve por redirección)
     * - https://www.google.com/maps?q=-25.123,-54.456
     * - https://www.google.com/maps/@-25.123,-54.456,15z
     * - https://maps.google.com/?q=-25.123,-54.456
     * - https://www.google.com/maps/place/.../@-25.123,-54.456,17z
     * - Texto simple: -25.123, -54.456
     *
     * @return array{lat: float|null, lon: float|null}
     */
    public static function extractLatLonFromMapsUrl(?string $url, bool $resolveShortUrl = true): array
    {
        $result = ['lat' => null, 'lon' => null];
        if ($url === null || trim($url) === '') {
            return $result;
        }

        $url = trim($url);

        // URL corta: resolver redirección y extraer de la URL final
        if ($resolveShortUrl && self::isShortMapsUrl($url)) {
            $resolved = self::resolveShortMapsUrl($url);
            if ($resolved !== null) {
                $url = $resolved;
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
}
