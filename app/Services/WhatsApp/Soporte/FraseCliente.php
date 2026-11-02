<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Frases permitidas al cliente. Si un texto se escapa con una medición, se descarta.
 */
class FraseCliente
{
    public const RESUELTO = 'Ya revisamos tu conexión y está funcionando bien. Si vuelve a fallar, escribinos.';

    public const ESCALADO = 'Agradecemos tu aviso. De inmediato estaremos verificando y monitoreando tu servicio para poder ayudarte.';

    public const ZONA = 'Detectamos un inconveniente en tu zona y ya lo estamos revisando.';

    public const CABLE = 'Revisá que el cable que entra al router esté bien enchufado. Cuando lo hagas, escribinos.';

    public const ACERCATE = 'Probá acercarte al router de tu casa y volvé a conectarte al WiFi.';

    public const PREGUNTA_SINTOMA = '¿No te anda nada o solo una página?';

    public const PREGUNTA_EQUIPO = '¿Te pasa en todos los aparatos o en uno? Decime el número del equipo:';

    public const SIN_CUENTA = 'No encuentro un servicio con este número. Escribí tu cédula, tu WhatsApp registrado o tu nombre completo tal como figura en la factura.';

    public const SITIO_MAL = 'Esa página no está respondiendo ahora. Tu conexión está bien.';

    public const SITIO_BIEN = 'Ya revisamos y esa página abre bien desde tu conexión.';

    public const IMPRESORA = 'Encontramos la impresora y la dejamos lista. Si en la computadora no aparece, avisanos y te pasamos un archivo para volver a conectarla.';

    public const IMPRESORA_NO = 'No vimos la impresora conectada en este momento. Revisá que esté encendida y en la misma red, y escribinos de nuevo.';

    public static function escalado(?string $cedula, ?string $nombre = null): string
    {
        $quien = self::nombreVisible($nombre);
        $apertura = $quien ? "{$quien}, agradecemos tu aviso." : 'Agradecemos tu aviso.';
        $visible = self::cedulaVisible($cedula);
        $registro = $visible ? " Registramos tu número de cédula {$visible}." : '';

        return $apertura
            .' De inmediato estaremos verificando y monitoreando tu servicio para poder ayudarte.'
            .$registro;
    }

    public static function nombreVisible(?string $nombre): ?string
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return null;
        }

        $primero = preg_split('/\s+/u', $nombre)[0] ?? '';
        $primero = trim((string) $primero, ".,");
        if ($primero === '') {
            return null;
        }

        return mb_convert_case(mb_strtolower($primero), MB_CASE_TITLE, 'UTF-8');
    }

    public static function cedulaVisible(?string $cedula): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $cedula) ?? '';
        $digitos = ltrim($digitos, '0');
        if ($digitos === '') {
            return null;
        }

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $digitos) ?: $digitos;
    }

    public static function cobranza(float $saldo): string
    {
        if ($saldo > 0) {
            $monto = number_format($saldo, 0, ',', '.');

            return "Tu servicio está suspendido. El saldo pendiente es de {$monto} guaraníes. Cuando el pago se acredite, se reactiva solo. Si ya pagaste, enviá el comprobante por acá.";
        }

        return 'Tu servicio está suspendido. Cuando el pago se acredite, se reactiva solo. Si ya pagaste, enviá el comprobante por acá.';
    }

    public static function wifiPendiente(string $clave, ?string $ssid): string
    {
        $red = $ssid ? " La red quedaría como «{$ssid}»." : '';

        return "Anoté el pedido para cambiar la clave del WiFi.{$red} La clave nueva sería {$clave}. En esta prueba todavía no la cambio.";
    }

    public static function wifiAplicado(string $clave, ?string $ssid): string
    {
        if ($ssid) {
            return "Listo. La red ahora se llama «{$ssid}» y la clave es {$clave}. Conectate de nuevo con esos datos.";
        }

        return "Listo. La clave nueva de tu WiFi es {$clave}. Conectate de nuevo con esa clave.";
    }

    public static function listaEquipos(array $nombres): string
    {
        $lineas = [self::PREGUNTA_EQUIPO];
        foreach (array_values($nombres) as $i => $nombre) {
            $lineas[] = ($i + 1).'. '.$nombre;
        }

        return implode("\n", $lineas);
    }

    /**
     * Si la frase menciona una medición de red, vuelve a la frase fija.
     */
    public static function filtrar(?string $reply, string $fallback): string
    {
        $reply = trim((string) $reply);
        if ($reply === '') {
            return $fallback;
        }

        if (preg_match('/\b\d+\s*(dBm|dbm|Mbps|mbps|Gbps)\b/u', $reply)) {
            return $fallback;
        }
        if (preg_match('/\b(ccq|pppoe|rssi|lanspeed|chain)\b/i', $reply)) {
            return $fallback;
        }
        if (preg_match('/\b\d{1,3}(?:\.\d{1,3}){3}\b/', $reply)) {
            return $fallback;
        }
        if (preg_match('/\bping\b|\bms\b/i', $reply)) {
            return $fallback;
        }
        if (preg_match('/\+?595[\s.-]*9\d{2}[\s.-]*\d{3}[\s.-]*\d{3}|\b971[\s.-]*714[\s.-]*322\b/u', $reply)) {
            return $fallback;
        }
        if (preg_match('/comunicarte al|atenci[oó]n directa|te saluda interplus|lamentamos/iu', $reply)) {
            return $fallback;
        }

        return $reply;
    }
}
