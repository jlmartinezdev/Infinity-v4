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

    public const SERVICIO_RESTABLECIDO = 'Su servicio ya volvió a conectar. Puede probar internet con normalidad.';

    public const COMPROBANTE = 'Gracias por enviarnos su comprobante. Lo recibimos correctamente. Un asesor lo registrará en breve. Quedamos a las órdenes.';

    public const CABLE = 'Revise que el cable que entra al router esté bien enchufado. Cuando lo haga, escribanos por aquí.';

    public const TV = 'Si el inconveniente es el televisor, pruebe el cable HDMI en la entrada 1 y, si no hay imagen, en la entrada 2. Confirme que el televisor esté en esa entrada HDMI.';

    public const LUZ = 'Si no hay luz en el equipo, el servicio vuelve cuando regresa la energía. Cuando tenga luz, escríbanos si sigue sin internet.';

    public const YA_FUNCIONA = 'Nos alegra que ya funcione. Quedamos a las órdenes.';

    public const AGRADECIMIENTO = 'Un placer atenderle. Quedamos a las órdenes.';

    public const PLAZO_PAGO = 'La fecha de pago es del 1 al 5 de cada mes. Si no se registra en ese plazo, el servicio se suspende y no se descuentan los días suspendidos. Cuando el pago se acredita, vuelve solo.';

    public const ACERCATE = 'Pruebe acercarse al router de su casa y vuelva a conectarse al WiFi.';

    public const PREGUNTA_SINTOMA = '¿No le anda nada o solo una página?';

    /** Enlace medido OK: informar análisis real y luego pedir síntoma. */
    public const ENLACE_OK_PREGUNTA = 'Ya revisamos su enlace desde nuestra red y responde bien. ¿No le anda nada o solo una página?';

    public const CPE_CAIDO = 'Detectamos que su equipo de casa no responde en este momento. De inmediato estaremos verificando y monitoreando su servicio para poder ayudarle.';

    public const OPTICA = 'Detectamos un problema en la señal de su fibra. De inmediato estaremos verificando y monitoreando su servicio para poder ayudarle.';

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

    /** Seguimiento cuando el cliente vuelve a preguntar por el mismo corte de zona. */
    public static function zonaSeguimiento(?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $cuerpo = 'seguimos con el inconveniente de zona en revisión. Apenas tengamos novedades se lo comunicamos por aquí. Quedamos a las órdenes.';
        if ($vocativo) {
            return $vocativo.', '.$cuerpo;
        }

        return 'Seguimos con el inconveniente de zona en revisión. Apenas tengamos novedades se lo comunicamos por aquí. Quedamos a las órdenes.';
    }

    public static function comprobante(?string $nombre = null, ?string $apellido = null, ?bool $enHorario = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $enHorario ??= true;
        $cuerpo = $enHorario
            ? 'gracias por enviarnos su comprobante. Lo recibimos correctamente. Un asesor lo registrará en breve. Quedamos a las órdenes.'
            : 'gracias por enviarnos su comprobante. Lo recibimos correctamente. Un asesor lo registra de lunes a viernes, de 9 a 18. Quedamos a las órdenes.';

        if ($vocativo) {
            return $vocativo.', '.$cuerpo;
        }

        return mb_strtoupper(mb_substr($cuerpo, 0, 1)).mb_substr($cuerpo, 1);
    }

    public static function zonaRestablecida(?string $nombre = null, ?string $apellido = null): string
    {
        return self::conVocativo(self::ZONA_RESTABLECIDA, $nombre, $apellido);
    }

    public static function servicioRestablecido(?string $nombre = null, ?string $apellido = null): string
    {
        return self::conVocativo(self::SERVICIO_RESTABLECIDO, $nombre, $apellido);
    }

    private static function conVocativo(string $cuerpo, ?string $nombre, ?string $apellido): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        if ($vocativo) {
            $cuerpo = "{$vocativo}, ".mb_strtolower(mb_substr($cuerpo, 0, 1)).mb_substr($cuerpo, 1);
        }

        return $cuerpo.' '.self::CIERRE;
    }

    public static function escalado(?string $cedula, ?string $nombre = null, ?string $apellido = null): string
    {
        $vocativo = TratoCliente::vocativo($nombre, $apellido);
        $apertura = $vocativo ? "{$vocativo}, recibimos su aviso." : 'Recibimos su aviso.';

        return $apertura
            .' De inmediato estaremos verificando y monitoreando su servicio para poder ayudarle.';
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

    public static function wifiNoVemosClaveActual(): string
    {
        return 'Por privacidad no vemos la contraseña actual del WiFi. Si desea cambiarla, escríbanos la clave nueva (mínimo 8 caracteres, letras y números) y un asesor la carga.';
    }

    public static function wifiPedirClave(): string
    {
        return 'Para cambiar la clave del WiFi, escríbanos la contraseña que desea usar (mínimo 8 caracteres, letras y números). Un asesor la carga; desde este chat no la inventamos ni la cambiamos.';
    }

    public static function wifiPendiente(string $clave, ?string $ssid): string
    {
        $red = $ssid ? " La red quedaría como «{$ssid}»." : '';

        return "Anotamos el pedido para cambiar la clave del WiFi.{$red} La clave quedaría {$clave}. Un asesor la aplica; desde este chat no la cambiamos.";
    }

    /**
     * @param  array<string, mixed>  $cuenta
     */
    public static function titularServicio(array $cuenta): string
    {
        $nombre = trim((string) ($cuenta['nombre'] ?? ''));
        $cedula = self::cedulaVisible(isset($cuenta['cedula']) ? (string) $cuenta['cedula'] : null);
        if ($nombre === '') {
            return 'No tenemos el nombre del titular cargado. Puede indicarnos la cédula para confirmarlo.';
        }
        $doc = $cedula ? ' Cédula '.$cedula.'.' : '';

        return 'El servicio está a nombre de '.$nombre.'.'.$doc;
    }

    /** @param  list<string>  $ssids */
    public static function nombreWifiActual(array $ssids): string
    {
        $ssids = array_values(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $ssids
        )));
        if ($ssids === []) {
            return 'Por ahora no vemos el nombre de su WiFi desde el sistema. Puede revisarlo en la etiqueta del router o en los WiFi guardados de su celular.';
        }
        if (count($ssids) === 1) {
            return 'El nombre de su red WiFi es «'.$ssids[0].'».';
        }
        $lineas = ['Estos son los nombres de WiFi que vemos en su equipo:'];
        foreach ($ssids as $i => $ssid) {
            $lineas[] = ($i + 1).'. «'.$ssid.'»';
        }

        return implode("\n", $lineas);
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

    /** Lista equipos vistos en su red (sin dBm). Ofrece cambio de clave si sospecha vecinos. */
    public static function listaEquiposVista(array $nombres, bool $pidioVecinos = false): string
    {
        $cantidad = count($nombres);
        if ($cantidad === 0) {
            $base = 'Por ahora no vemos equipos conectados en su red desde nuestro sistema.';
            if ($pidioVecinos) {
                $base .= ' No listamos redes WiFi ajenas; sí podemos cambiarle la clave del WiFi para cortar accesos no deseados.';
            }

            return $base.' Si está cerca del router, pruebe conectar su celular y escribanos de nuevo'
                .($pidioVecinos ? ', o escriba CAMBIAR WIFI.' : '.');
        }

        $lineas = [
            $cantidad === 1
                ? 'Vemos 1 equipo conectado ahora en su red:'
                : "Vemos {$cantidad} equipos conectados ahora en su red:",
        ];
        foreach (array_values($nombres) as $i => $nombre) {
            $lineas[] = ($i + 1).'. '.$nombre;
        }
        if ($pidioVecinos) {
            $lineas[] = 'No listamos redes WiFi de vecinos; lo que sí vemos son los aparatos usando su servicio.';
            $lineas[] = 'Si no reconoce alguno, escriba CAMBIAR WIFI y la contraseña que desea usar.';
        } else {
            $lineas[] = 'Si no reconoce alguno o sospecha que un vecino usa su WiFi, escriba CAMBIAR WIFI.';
        }

        return implode("\n", $lineas);
    }

    /** Prompt / lineamiento interno que nunca debe llegar al cliente. */
    public static function pareceFugaLineamiento(?string $reply): bool
    {
        $t = mb_strtolower(trim((string) $reply));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/venta suave|planes vigentes\s*\(|salida whatsapp|enrutado:|cotice solo|no invente precios|estilo interplus|obligatorio:|reglas fijas|historial_texto|instruccion_|·\s*prioridad\s*\d|gancho por conversaci|menosprecie|mensaje_hilo_reciente|planes_prompt|hechos\s*\(solo/u',
            $t
        );
    }

    /**
     * @param  array<string, mixed>  $cuenta
     */
    public static function detalleServicio(array $cuenta): string
    {
        $plan = trim((string) ($cuenta['plan_nombre'] ?? ''));
        $precio = isset($cuenta['plan_precio']) && is_numeric($cuenta['plan_precio'])
            ? (int) $cuenta['plan_precio']
            : null;
        $estado = trim((string) ($cuenta['estado'] ?? ''));
        $saldo = isset($cuenta['saldo']) ? (float) $cuenta['saldo'] : 0.0;
        $mora = isset($cuenta['dias_mora']) ? (int) $cuenta['dias_mora'] : 0;
        $antena = (bool) ($cuenta['es_antena'] ?? false);
        $cedula = self::cedulaVisible($cuenta['cedula'] ?? null);

        $lineas = ['Detalle de su servicio:'];
        if ($plan !== '') {
            $tech = $antena ? ' (antena)' : ' (fibra)';
            $precioTxt = $precio !== null ? ' — Gs. '.number_format($precio, 0, ',', '.') : '';
            $lineas[] = '• Plan: '.$plan.$tech.$precioTxt;
        } else {
            $lineas[] = '• Plan: no figura en el sistema; un asesor lo confirma.';
        }
        if ($estado !== '') {
            $lineas[] = '• Estado: '.$estado;
        }
        if ($cedula) {
            $lineas[] = '• Cédula: '.$cedula;
        }
        if ($saldo > 0) {
            $lineas[] = '• Saldo pendiente: Gs. '.number_format((int) round($saldo), 0, ',', '.');
            if ($mora > 0) {
                $lineas[] = '• Días de mora: '.$mora;
            }
        } else {
            $lineas[] = '• Saldo pendiente: sin deuda registrada.';
        }
        $lineas[] = 'Si necesita cambiar el plan, pagar o soporte técnico, escribanos por aquí.';

        return implode("\n", $lineas);
    }

    /**
     * Si la frase menciona una medición de red, tono prohibido o fuga de prompt, vuelve al fallback.
     */
    public static function filtrar(?string $reply, string $fallback): string
    {
        $reply = trim((string) $reply);
        if ($reply === '') {
            return $fallback;
        }

        if (self::pareceFugaLineamiento($reply)) {
            return $fallback;
        }

        // dBm siempre es medición técnica; no se muestra al cliente.
        if (preg_match('/\b\d+\s*(dBm|dbm)\b/u', $reply)) {
            return $fallback;
        }
        // Mbps/Gbps solo se bloquean si parecen medición de enlace (no cotización de plan/antena).
        if (
            preg_match('/\b\d+\s*(Mbps|mbps|Gbps|gbps)\b/u', $reply)
            && preg_match('/\b(se[nñ]al|lan|ping|cable|rssi|enlace|puerto|ccq)\b/iu', $reply)
        ) {
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
        if (preg_match('/comunicarte al|atenci[oó]n directa/iu', $reply)) {
            return $fallback;
        }

        $reply = self::recortarSaludoMarca($reply);
        if ($reply === '') {
            return $fallback;
        }
        if (preg_match('/recibimos tu comprobante/iu', $reply)) {
            return $fallback;
        }
        if (preg_match('/agradecemos\s+(tu|su)\s+aviso/iu', $reply)) {
            return $fallback;
        }

        return $reply;
    }

    /** Quita aperturas tipo “Hola, te saluda Interplus / lamentamos…”. */
    public static function recortarSaludoMarca(string $reply): string
    {
        $out = trim($reply);
        for ($i = 0; $i < 3; $i++) {
            $prev = $out;
            $out = preg_replace(
                '/^(?:¡?\s*)?(?:muy\s+)?(?:hola|buen[oa]s?\s+(?:d[ií]as|tardes|noches)|buen\s+d[ií]a|qu[eé]\s+tal)[^.!?\n]*[.!?]+\s*/iu',
                '',
                $out
            ) ?? $out;
            $out = preg_replace(
                '/^(?:te\s+)?salud(?:a|amos)(?:\s+de)?\s+interplus[^.!?\n]*[.!?]+\s*/iu',
                '',
                $out
            ) ?? $out;
            $out = preg_replace(
                '/^soy\s+de\s+interplus[^.!?\n]*[.!?]+\s*/iu',
                '',
                $out
            ) ?? $out;
            $out = preg_replace(
                '/^en\s+interplus[^.!?\n]*[.!?]+\s*/iu',
                '',
                $out
            ) ?? $out;
            $out = preg_replace(
                '/^lamentamos[^.!?\n]*[.!?]+\s*/iu',
                '',
                $out
            ) ?? $out;
            $out = preg_replace(
                '/^agradecemos\s+(?:tu|su)\s+aviso[^.!?\n]*[.!?]+\s*/iu',
                '',
                $out
            ) ?? $out;
            $out = trim($out);
            if ($out === $prev) {
                break;
            }
        }

        // Si aún queda la marca en la primera línea, descartar esa línea.
        $lineas = preg_split('/\R+/', $out) ?: [];
        if ($lineas !== [] && preg_match('/interplus|te saluda|lamentamos/iu', $lineas[0] ?? '')) {
            array_shift($lineas);
            $out = trim(implode("\n", $lineas));
        }

        return $out;
    }
}
