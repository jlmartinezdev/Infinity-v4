<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Clasifica el texto del cliente para el soporte. No llama a la red ni al modelo.
 */
class SoporteTexto
{
    public static function pareceFalla(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/no anda|no funciona|sin internet|sin servicio|se corta|se corto|se cort[oó]|muy lento|esta lento|est[aá] lento|lento|no carga|no abre|no navega|sin se[nñ]al|no tengo (internet|wifi|wi-?fi)|no hay (internet|wifi|wi-?fi)|se ca[yi][oó]|p[aá]gina|pagina|no me anda|anda mal|intermitente/u',
            $t
        );
    }

    public static function pareceCambioWifi(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/cambi(ar|a|ame|á)\s+(la\s+|el\s+)?(clave|contrase[nñ]a|password|nombre|ssid).{0,24}(wifi|wi-?fi)|(clave|contrase[nñ]a|nombre)\s+(del?\s+)?(wifi|wi-?fi)|wifi\s+nuevo|vecinos?\s+colgad/u',
            $t
        );
    }

    public static function pareceImpresora(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match('/impresora|printer|no imprime|dej[oó] de imprimir/u', $t);
    }

    public static function pareceCierre(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '' || mb_strlen($t) > 40) {
            return false;
        }

        return (bool) preg_match('/^(gracias|muchas gracias|ok|okay|listo|perfecto|dale|buen[ií]simo|genial|nada mas|nada más)\.?$/u', $t);
    }

    public static function pareceCableListo(?string $texto): bool
    {
        $t = self::norm($texto);

        return (bool) preg_match('/enchuf|conect[eé]|ya (lo |la )?puse|ya esta|ya está|listo el cable|revise el cable|revis[eé] el cable/u', $t);
    }

    public static function pareceNada(?string $texto): bool
    {
        $t = self::norm($texto);

        return (bool) preg_match('/\b(nada|todo|todos|ningun sitio|ningún sitio|no anda nada|internet entero|en todo)\b/u', $t);
    }

    public static function sitio(?string $texto): ?string
    {
        $t = self::norm($texto);
        if ($t === '') {
            return null;
        }

        $conocidos = [
            'facebook' => 'facebook.com',
            'instagram' => 'instagram.com',
            'whatsapp' => 'whatsapp.com',
            'youtube' => 'youtube.com',
            'google' => 'google.com',
            'tiktok' => 'tiktok.com',
            'netflix' => 'netflix.com',
        ];
        foreach ($conocidos as $nombre => $host) {
            if (str_contains($t, $nombre)) {
                return $host;
            }
        }

        if (preg_match('/\b((?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,})\b/', $t, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function indiceEquipo(?string $texto, int $cantidad): ?int
    {
        if ($cantidad < 1) {
            return null;
        }
        $t = self::norm($texto);
        if (preg_match('/\b(\d{1,2})\b/', $t, $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= $cantidad) {
                return $n - 1;
            }
        }

        return null;
    }

    public static function claveWifi(?string $texto): ?string
    {
        $raw = trim((string) $texto);
        if (preg_match('/(?:clave|contrase[nñ]a|password)\s*(?:nueva|wifi)?\s*[:=]?\s*([A-Za-z0-9]{8,32})/u', $raw, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function nombreWifi(?string $texto): ?string
    {
        $raw = trim((string) $texto);
        if (preg_match('/(?:nombre|ssid)\s*(?:nuevo|wifi)?\s*[:=]?\s*([A-Za-z0-9][A-Za-z0-9 _.-]{1,31})/u', $raw, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    public static function norm(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return $t;
    }
}
