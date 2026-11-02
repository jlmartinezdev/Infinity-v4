<?php

namespace App\Services\WhatsApp\Soporte;

/**
 * Frases permitidas al cliente. Voz formal (usted). Sin mediciones ni teléfono.
 */
class FraseCliente
{
    public const RESUELTO = 'Ya revisamos su conexión y está funcionando bien. Fue un placer atender su reclamo. Quedamos a las órdenes.';

    public const ESCALADO = 'Recibimos su aviso. De inmediato estaremos verificando y monitoreando su servicio para poder ayudarle.';

    public const ZONA = 'Gracias por avisarnos. Detectamos un inconveniente en su zona y ya lo estamos revisando. Apenas tengamos novedades, se lo estaremos comunicando. Quedamos a las órdenes.';

    public const ZONA_RESTABLECIDA = 'El servicio en su zona ya está restablecido. Puede volver a utilizar internet con normalidad.';

    public const COMPROBANTE = 'Gracias por enviarnos su comprobante. Lo recibimos correctamente. Un asesor lo registrará en breve. Quedamos a las órdenes.';

    public const CABLE = 'Revise que el cable que entra al router esté bien enchufado. Cuando lo haga, escribanos por aquí.';

    public const ACERCATE = 'Pruebe acercarse al router de su casa y vuelva a conectarse al WiFi.';

    public const PREGUNTA_SINTOMA = '¿No le anda nada o solo una página?';

    public const PREGUNTA_EQUIPO = '¿Le pasa en todos los aparatos o en uno? Indíquenos el número del equipo:';

    public const SIN_CUENTA = 'No encontramos un servicio con este número. Escriba su cédula, su WhatsApp registrado o su nombre completo tal como figura en la factura.';

    public const SITIO_MAL = 'Esa página no está respondiendo ahora. Su conexión está bien.';

    public const SITIO_BIEN = 'Ya revisamos y esa página abre bien desde su conexión.';

    public const IMPRESORA = 'Encontramos la impresora y la dejamos lista. Si en la computadora no aparece, avísenos y le pasamos un archivo para volver a conectarla.';

    public const IMPRESORA_NO = 'No vimos la impresora conectada en este momento. Revise que esté encendida y en la misma red, y escribanos de nuevo.';

    public const CIERRE = 'Fue un placer atender su reclamo. Quedamos a las órdenes.';

    public static function zona(?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        if ($vocativo) {
            return $vocativo.', gracias por avisarnos. Detectamos un inconveniente en su zona y ya lo estamos revisando. Apenas tengamos novedades, se lo estaremos comunicando. Quedamos a las órdenes.';
        }

        return self::ZONA;
    }

    public static function comprobante(?string $nombre = null, ?string $apellido = null, ?bool $enHorario = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $pref = $vocativo ? "{$vocativo}, " : '';
        $enHorario ??= true;
        if ($enHorario) {
            return "{$pref}gracias por enviarnos su comprobante. Lo recibimos correctamente. Un asesor lo registrará en breve. Quedamos a las órdenes.";
        }

        return "{$pref}gracias por enviarnos su comprobante. Lo recibimos correctamente. Un asesor lo registra de lunes a viernes, de 9 a 18. Quedamos a las órdenes.";
    }

    public static function zonaRestablecida(?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $cuerpo = self::ZONA_RESTABLECIDA;
        if ($vocativo) {
            $cuerpo = "{$vocativo}, ".mb_strtolower(mb_substr($cuerpo, 0, 1)).mb_substr($cuerpo, 1);
        }

        return $cuerpo.' '.self::CIERRE;
    }

    public static function escalado(?string $cedula, ?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $apertura = $vocativo ? "{$vocativo}, recibimos su aviso." : 'Recibimos su aviso.';
        $visible = self::cedulaVisible($cedula);
        $registro = $visible ? " Registramos su número de cédula {$visible}." : '';

        return $apertura
            .' De inmediato estaremos verificando y monitoreando su servicio para poder ayudarle.'
            .$registro;
    }

    public static function resuelto(?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $cuerpo = 'Ya revisamos su conexión y está funcionando bien.';
        if ($vocativo) {
            $cuerpo = "{$vocativo}, ya revisamos su conexión y está funcionando bien.";
        }

        return $cuerpo.' '.self::CIERRE;
    }

    public static function nombreVisible(?string $nombre): ?string
    {
        return TratoCliente::primerNombre($nombre);
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

    public static function cobranza(float $saldo, ?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $pref = $vocativo ? "{$vocativo}, " : '';
        if ($saldo > 0) {
            $monto = number_format($saldo, 0, ',', '.');

            return "{$pref}su servicio está suspendido. El saldo pendiente es de {$monto} guaraníes. Cuando el pago se acredite, se reactiva solo. Si ya pagó, envíe el comprobante por aquí.";
        }

        return "{$pref}su servicio está suspendido. Cuando el pago se acredite, se reactiva solo. Si ya pagó, envíe el comprobante por aquí.";
    }

    public static function wifiPendiente(string $clave, ?string $ssid): string
    {
        $red = $ssid ? " La red quedaría como «{$ssid}»." : '';

        return "Anotamos el pedido para cambiar la clave del WiFi.{$red} La clave nueva sería {$clave}. En esta prueba todavía no la cambiamos.";
    }

    public static function wifiAplicado(string $clave, ?string $ssid): string
    {
        if ($ssid) {
            return "Listo. La red ahora se llama «{$ssid}» y la clave es {$clave}. Conéctese de nuevo con esos datos.";
        }

        return "Listo. La clave nueva de su WiFi es {$clave}. Conéctese de nuevo con esa clave.";
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
     * Si la frase menciona una medición de red o tono prohibido, vuelve a la frase fija.
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
        if (preg_match('/comunicarte al|atenci[oó]n directa|te saluda interplus|lamentamos|agradecemos (tu|su) aviso|recibimos tu comprobante|¡hola!/iu', $reply)) {
            return $fallback;
        }

        return $reply;
    }
}
