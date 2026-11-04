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
            '/no anda|no funciona|sin internet|sin servicio|se corta|se corto|se cort[oó]|muy lento|esta lento|est[aá] lento|lento|no carga|no abre|no navega|sin se[nñ]al|se[nñ]al (de |del )?(mi )?(wifi|wi-?fi|internet|servicio)|se[nñ]al (wifi|wi-?fi)|como (esta|está) (mi |la )?se[nñ]al|estado de (la |mi )?se[nñ]al|no tengo (internet|wifi|wi-?fi)|no hay (internet|wifi|wi-?fi)|se ca[yi][oó]|p[aá]gina|pagina|no me anda|anda mal|intermitente|no da m[aá]s se[nñ]al|reclamo por (el )?internet|desde ayer|desde (la )?tarde|estamos sin|qued[oó] sin|dej[oó] de funcionar/u',
            $t
        );
    }

    /** Pregunta el titular del servicio. */
    public static function pareceConsultaTitular(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/a nombre de qui[eé]n|de qui[eé]n (esta|está|es)|qui[eé]n es el titular|nombre del titular|a nombre de quien/u',
            $t
        );
    }

    /** Consulta del plan/saldo/estado de la cuenta del cliente. */
    public static function pareceConsultaServicio(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/detalle (de )?(mi |su )?servicio|qu[eé] plan (tengo|tengo contratado|estoy)|mi plan|plan (actual|contratado)|cu[aá]nto (pago|debo|debo pagar)|mi saldo|saldo (pendiente|deuda)|estado (de )?(mi )?(cuenta|servicio)|datos (de )?(mi )?(cuenta|servicio)/u',
            $t
        );
    }

    /** Pedido de catálogo / precios de planes (no beneficios genéricos). */
    public static function pareceConsultaCatalogo(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/qu[eé] planes?( hay| tienen| ofrecen)?|planes? (de )?(fibra|antena|disponibles|vigentes)|precios?( de (los )?planes?)?|cu[aá]nto (cuesta|sale|vale).{0,20}(plan|fibra|antena|megas|mb)|cotiz(ar|ame|á)|lista (de )?planes/u',
            $t
        );
    }

    /** Pedido de listar equipos/dispositivos de su red (ACS/DHCP). No es escaneo de vecinos. */
    public static function pareceConsultaEquipos(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/list(ar|ame|á|a)?\s+(los\s+)?equipos|qu[eé] equipos (ves|ve|hay|tengo)|equipos (conectados|que ves|de la red)|dispositivos (conectados|en (el |la )?(wifi|red|router))|hay alg[uú]n vecino|redes? de vecinos?|vecinos?\s+(en\s+)?(el\s+)?wifi|qui[eé]n est[aá] conectado|aparatos conectados|qu[eé] (es lo que )?(ves|ve|hay) en (mi |la |su )?(red|wifi)|lo que ves en (mi |la )?red|que ves en (mi |la )?(red|wifi)/u',
            $t
        );
    }

    public static function pideVecinosWifi(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/vecinos?|redes? (ajenas|de al lado|cercanas)|ssid (ajen|vecin)|enganchad|robando (el )?wifi|usan mi wifi|alguien (esta |está )?(en|usando) (mi )?wifi|colgad(os)? (en|al) (el )?wifi|no reconozco/u',
            $t
        );
    }

    /** Confirmación corta para cambiar WiFi tras sospecha de vecinos. */
    public static function pareceConfirmaCambioWifi(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '' || self::pareceConsultaNombreWifi($t)) {
            return false;
        }
        // Preguntas ("cómo vas a cambiar?", "hace cuánto…") no confirman.
        if (preg_match('/^(como|c[oó]mo|hace|por que|por qu[eé]|cuando|cu[aá]ndo|que|qu[eé]|cual|cu[aá]l)\b/u', $t)) {
            return false;
        }

        return (bool) preg_match(
            '/^(si|sí|dale|ok|okay|listo|cambiar|cambie|cambiá|cambiar wifi|cambiar clave|nueva clave|hacelo|hágalo)\.?$/u',
            $t
        ) || (bool) preg_match('/^(si|sí)[,.]?\s+(cambiar|cambie|cambiá)/u', $t)
            || (bool) preg_match('/cambi(ar|e|á|ame)\s+(la\s+|el\s+)?(clave|wifi|contrase|ssid)/u', $t);
    }

    /** Seguimiento de un reclamo/zona ya avisado (no abrir diagnóstico de cero). */
    public static function pareceSeguimientoZona(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }
        if (preg_match('/^(gracias|muchas gracias|ok|listo|perfecto)\.?$/u', $t)) {
            return true;
        }

        return (bool) preg_match(
            '/alguna novedad|hay novedad|sigue sin|todav[ií]a sin|a[uú]n sin|sigue (el|la)?\s*(problema|falla|corte)|cuando vuelve|ya volvi[oó]|sigue ca[ií]do|sigue cortado|👀|novedades/u',
            $t
        );
    }

    /** Une textos recientes del mismo hilo para clasificar mejor ráfagas. */
    public static function unirHilo(array $textos): string
    {
        $partes = [];
        foreach ($textos as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $partes[] = $t;
            }
        }

        return trim(implode(' ', $partes));
    }

    /** Pregunta el SSID actual (no es pedido de cambio). */
    public static function pareceConsultaNombreWifi(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '') {
            return false;
        }

        // "cambiar el nombre del wifi" es cambio, no consulta.
        if (preg_match('/cambi(ar|a|ame|á|e)/u', $t)) {
            return false;
        }

        return (bool) preg_match(
            '/(qu[eé]|cual|cu[aá]l).{0,24}(nombre|ssid).{0,24}(wifi|wi-?fi|red)|(nombre|ssid).{0,20}(del |de la |de )?(wifi|wi-?fi|red)|(wifi|wi-?fi).{0,16}(nombre|ssid)|como se llama.{0,20}(wifi|wi-?fi|red)|nombre actual|(no lo ves|no ves|no se ve|no visualiz).{0,48}(nombre|wifi|ssid|red)/u',
            $t
        );
    }

    public static function pareceCambioWifi(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '' || self::pareceConsultaNombreWifi($t)) {
            return false;
        }

        // Solo intención de cambiar; no "nombre de wifi" / "clave del wifi" en consulta.
        return (bool) preg_match(
            '/cambi(ar|a|ame|á)\s+(la\s+|el\s+)?(clave|contrase[nñ]a|password|nombre|ssid).{0,24}(wifi|wi-?fi)|cambi(ar|a|ame|á)\s+(el\s+)?(wifi|wi-?fi)|nueva\s+(clave|contrase[nñ]a).{0,16}(wifi|wi-?fi)|(clave|contrase[nñ]a)\s+nueva.{0,16}(wifi|wi-?fi)|wifi\s+nuevo|reset(ear)?\s+(el\s+)?(wifi|wi-?fi)|vecinos?\s+colgad/u',
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

    public static function pareceTelevision(?string $texto): bool
    {
        $t = self::norm($texto);

        return (bool) preg_match('/\b(tv|televisor|televisi[oó]n|hdmi|decodificador)\b|\bdeco\b/u', $t);
    }

    public static function pareceLuz(?string $texto): bool
    {
        $t = self::norm($texto);

        return (bool) preg_match('/\b(sin luz|no hay luz|se cort[oó] la luz|sin energ[ií]a|no hay energ[ií]a)\b/u', $t);
    }

    /** El cliente dice que el servicio ya volvió. */
    public static function pareceYaFunciona(?string $texto): bool
    {
        $t = self::norm($texto);
        if ($t === '' || preg_match('/\bno (me |nos )?(funciona|anda)\b/u', $t)) {
            return false;
        }

        return (bool) preg_match(
            '/ya (me |nos )?(funciona|anda|volvi[oó]|hay se[nñ]al|tiene se[nñ]al|est[aá] bien)|volvi[oó] (el |la |mi )?(servicio|internet|tv|se[nñ]al|imagen)|se (solucion[oó]|arreg[oó])/u',
            $t
        );
    }

    public static function pareceSinSenal(?string $texto): bool
    {
        $t = self::norm($texto);

        return (bool) preg_match('/no hay se[nñ]al|sin se[nñ]al|ninguna se[nñ]al|no (tiene|tengo) se[nñ]al/u', $t);
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
        if ($raw === '' || self::pareceConsultaNombreWifi($raw)) {
            return null;
        }
        // Solo si pide nombre nuevo explícito (no "qué nombre de wifi tengo").
        if (preg_match('/(?:nombre|ssid)\s*(?:nuevo|nueva)?\s*(?:del?\s*wifi)?\s*(?:a|=|:)\s*[«"\']?([A-Za-z0-9][A-Za-z0-9 _.-]{1,31})[»"\']?/u', $raw, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/cambi(ar|e|á|ame)\s+(el\s+)?(nombre|ssid).{0,20}(?:a|=|:)\s*[«"\']?([A-Za-z0-9][A-Za-z0-9 _.-]{1,31})[»"\']?/u', $raw, $m)) {
            return trim($m[4]);
        }

        return null;
    }

    public static function telefonoEnTexto(?string $texto): ?string
    {
        $raw = trim((string) $texto);
        if ($raw === '') {
            return null;
        }

        $solo = preg_replace('/[\s().+-]+/', '', $raw) ?? '';
        if (preg_match('/^\d{9,14}$/', $solo)) {
            return self::normalizarTelefonoPy($solo);
        }

        $compacto = preg_replace('/\s+/', '', $raw) ?? '';
        if (preg_match('/(?:\+?595|0)?9\d{8}/', $compacto, $m)) {
            return self::normalizarTelefonoPy(preg_replace('/\D+/', '', $m[0]) ?? '');
        }

        return null;
    }

    public static function cedulaEnTexto(?string $texto): ?string
    {
        $t = self::norm($texto);
        if ($t === '') {
            return null;
        }

        if (preg_match('/(?:soy|ci|c[eé]dula|documento|nro?\.?|n[ºo°])\s*[:.]?\s*(\d{5,8})\b/u', $t, $m)) {
            return ltrim($m[1], '0') ?: $m[1];
        }

        if (preg_match('/^\d{5,8}$/', $t)) {
            return ltrim($t, '0') ?: $t;
        }

        return null;
    }

    public static function nombreEnTexto(?string $texto): ?string
    {
        $raw = trim((string) $texto);
        if ($raw === '') {
            return null;
        }

        $t = self::norm($raw);
        if (self::telefonoEnTexto($raw) || self::cedulaEnTexto($raw)) {
            return null;
        }

        if (preg_match(
            '/(?:soy|me llamo|mi nombre(?:\s+es)?|nombre)\s*[:.]?\s*([a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,4})/u',
            $t,
            $m
        )) {
            return self::limpiarNombre($m[1]);
        }

        if (preg_match(
            '/^([a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){1,4})\s+(?:no|sin|por|porque|anda|wifi|wi-fi|internet|señal|senal|reclamo|problema)\b/u',
            $t,
            $m
        )) {
            return self::limpiarNombre($m[1]);
        }

        if (preg_match('/^[a-záéíóúñ]+(?:\s+[a-záéíóúñ]+){0,4}$/u', $t)) {
            return self::limpiarNombre($t);
        }

        return null;
    }

    public static function esSoloIdentificador(?string $texto): bool
    {
        $raw = trim((string) $texto);
        if ($raw === '') {
            return false;
        }
        if (self::telefonoEnTexto($raw) && preg_match('/^[\d\s+().-]{8,20}$/', $raw)) {
            return true;
        }
        if (self::cedulaEnTexto($raw) && preg_match('/^(?:soy|ci|c[eé]dula|documento|nro?\.?|n[ºo°])?\s*[:.]?\s*\d{5,8}$/iu', $raw)) {
            return true;
        }
        $nombre = self::nombreEnTexto($raw);

        return $nombre !== null && self::norm($raw) === $nombre;
    }

    public static function limpiarNombre(string $nombre): ?string
    {
        $n = preg_replace('/\s+/u', ' ', trim($nombre)) ?? '';
        $n = trim($n, ".,;:");
        $n = preg_replace(
            '/\s+(?:no|sin|por|porque|anda|el|la|los|las|mi|su|wifi|wi-fi|internet|señal|senal|reclamo|problema|ayuda|favor)\b.*$/u',
            '',
            $n
        ) ?? $n;
        $n = trim($n, ".,;:");
        if (mb_strlen($n) < 3) {
            return null;
        }
        if (preg_match('/^(hola|buenas|buenos|gracias|ok|dale|listo|internet|wifi|wi-fi)$/u', $n)) {
            return null;
        }

        return $n;
    }

    public static function normalizarTelefonoPy(string $digitos): ?string
    {
        $d = preg_replace('/\D+/', '', $digitos) ?? '';
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0') && strlen($d) === 10) {
            $d = '595'.substr($d, 1);
        } elseif (! str_starts_with($d, '595') && strlen($d) === 9 && str_starts_with($d, '9')) {
            $d = '595'.$d;
        }
        if (strlen($d) < 11 || strlen($d) > 14) {
            return null;
        }

        return $d;
    }

    public static function norm(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        return $t;
    }
}
