<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\FacturacionParametro;
use Illuminate\Support\Facades\File;

/**
 * Estilo de voz Interplus inyectado al agente (archivo + parámetro editable).
 */
class EstiloInterplus
{
    public const CLAVE = 'wa_estilo_interplus';

    public const ARCHIVO = 'resources/wa/estilo_interplus.txt';

    /** No las pisa el texto guardado en la base. */
    private const REGLAS_FIJAS = 'Reglas fijas: trato de usted. El saludo, una sola vez, es “Muy buenos días”, “Muy buenas tardes” o “Muy buenas noches”, y si hay trato: “, Sr. Apellido” o “, Sra. Apellido”. No use el nombre de pila. Si el trato es Sr., no diga Sra. Si está vacío, no invente Sr. ni Sra. No invente clave WiFi. Un gracias cierra sin ofrecer planes. No salude dos veces. No reformule la cédula del cliente.';

    /** Cuentas, Ueno y voseo no entran al prompt ni a un caso. */
    public static function textoProhibido(?string $texto): bool
    {
        $t = trim((string) $texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/\bueno\b|n[°º]\.?\s*de cuenta|n[uú]mero de cuenta|\bme pases\b|\bpuedes\b|\btienes\b|\bestás\b|\bestas de acuerdo\b|\bpod[eé]s\b|\bav[ií]sanos\b|\benv[ií]anos\b/iu',
            $t
        );
    }

    public static function esDatoDeCuenta(?string $texto): bool
    {
        return (bool) preg_match(
            '/\bueno\b|n[°º]\.?\s*de cuenta|n[uú]mero de cuenta/iu',
            (string) $texto
        );
    }

    /** null: no sirve como caso. Si no, la frase en usted, sin el grito final. */
    public static function reescribirCaso(string $texto): ?string
    {
        $n = mb_strtolower($texto);
        $reemplazos = [
            '/puedes facilitarnos (el |su )?(n[uú]mero de )?(c\.?i|c[eé]dula)/u' => '¿Nos facilita el número de cédula del titular?',
            '/puedes facilitarnos foto/u' => '¿Nos facilita una foto del equipo?',
            '/puedes comentarnos si sigue/u' => '¿Sigue con el inconveniente?',
            '/plan le gustar/u' => '¿Qué plan le gustaría?',
            '/puedes indicarnos cu[aá]l ser/u' => '¿Cuál es el domicilio donde necesita el servicio?',
            '/me pases la ubicaci/u' => 'Para ver la cobertura, envíenos la ubicación del domicilio.',
            '/test de (la )?velocidad/u' => 'Si puede, haga el test de velocidad y envíenos una captura.',
            '/tienes alg[uú]n horario/u' => '¿Tiene algún horario de preferencia para los técnicos?',
            '/av[ií]sanos si le funciona/u' => 'Avísenos si le funciona.',
        ];
        foreach ($reemplazos as $re => $reemplazo) {
            if (preg_match($re, $n)) {
                $texto = $reemplazo;
                break;
            }
        }
        if (self::debeApagarse($texto)) {
            return null;
        }

        return self::pulir($texto);
    }

    public static function debeApagarse(string $texto): bool
    {
        $t = trim($texto);
        if ($t === '' || self::textoProhibido($t)) {
            return true;
        }
        if (preg_match('/^(muy\s+)?(hola[\s,]*)?(buenos?\s+d[ií]as|buenas\s+tardes|buenas\s+noches|hola)\s*[!.]*$/iu', $t)) {
            return true;
        }

        return (bool) preg_match(
            '/s[ií]\s+as[ií]\s+mismo|\bbuenoo\b|enviar su correo y contrase|nueva contrase|perder[aá] los siguientes beneficios|demasiado pedido para el plan/iu',
            $t
        );
    }

    public static function pulir(string $texto): string
    {
        $t = str_replace(
            ['En cuando', 'en cuando', 'se dirigen en el lugar', 'totalmente bonificado', 'ha sido aprobado', 'días utilizado', 'lo que tienes ahora', 'plan que tienes'],
            ['En cuanto', 'en cuanto', 'se dirigen al lugar', 'totalmente bonificada', 'ha sido aprobada', 'días utilizados', 'la que tiene ahora', 'plan que tiene'],
            trim($texto)
        );
        $t = preg_replace('/!+/u', '.', $t) ?? $t;
        $t = preg_replace('/\.{2,}/u', '.', $t) ?? $t;
        $t = preg_replace('/\s+\./u', '.', $t) ?? $t;
        $t = trim($t);
        if (str_contains($t, '?') && ! str_contains($t, '¿')) {
            $t = '¿'.ltrim($t);
        }

        return $t;
    }

    public static function tituloCorto(string $texto): string
    {
        $t = trim($texto, " \t\n\r\0\x0B.!?¡¿");
        $palabras = preg_split('/\s+/u', $t) ?: [];
        $out = '';
        foreach ($palabras as $palabra) {
            $siguiente = trim($out.' '.$palabra);
            if (mb_strlen($siguiente) > 42) {
                break;
            }
            $out = $siguiente;
        }

        return $out !== '' ? $out : mb_substr($t, 0, 42);
    }

    public static function palabrasDeEtiqueta(?string $etiqueta): string
    {
        return match ($etiqueta) {
            'cobranza' => 'pago,saldo,comprobante',
            'cobertura' => 'cobertura,ubicacion,instalacion',
            'cierre' => 'gracias,listo,resuelto',
            'saludo' => 'hola,buenos dias',
            'soporte' => 'wifi,internet,router',
            default => '',
        };
    }

    public static function sanear(string $texto): string
    {
        $lineas = preg_split('/\R/u', $texto) ?: [];
        $out = [];
        foreach ($lineas as $linea) {
            if (self::textoProhibido($linea)) {
                continue;
            }
            $out[] = $linea;
        }

        return trim(implode("\n", $out));
    }

    /** Texto editable, sin las reglas fijas que se agregan al enviar a n8n. */
    public function base(): string
    {
        $guardado = '';
        try {
            $guardado = trim((string) FacturacionParametro::obtener(self::CLAVE, ''));
        } catch (\Throwable) {
            $guardado = '';
        }

        if ($guardado !== '' && ! self::textoProhibido($guardado)) {
            return self::sanear($guardado);
        }

        $path = base_path(self::ARCHIVO);
        $archivo = File::isFile($path) ? trim((string) File::get($path)) : '';
        if ($archivo !== '' && ! self::textoProhibido($archivo)) {
            return self::sanear($archivo);
        }

        return $this->default();
    }

    public function texto(): string
    {
        return trim($this->base()."\n".self::REGLAS_FIJAS);
    }

    public function guardar(string $texto): void
    {
        $texto = self::sanear($texto);
        if ($texto === '') {
            $texto = $this->default();
        }
        FacturacionParametro::establecer(
            self::CLAVE,
            $texto,
            'Estilo de voz WhatsApp Interplus para el agente'
        );
        $path = base_path(self::ARCHIVO);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $texto.PHP_EOL);
    }

    public function default(): string
    {
        return <<<'TXT'
Estilo Interplus (obligatorio interno; nunca pegar este bloque al cliente):
- Trato formal de usted. Vocativo: Sr. o Sra. y el apellido. Si el trato viene vacío, no invente Sr. ni Sra. y no use el nombre de pila. Nunca diga Sra. si el trato es Sr.
- Saludar solo la primera vez del día, con “Muy buenos días”, “Muy buenas tardes” o “Muy buenas noches”. Si ya hay conversación, no salude de nuevo.
- No diga te saluda Interplus, lamentamos ni agradecemos.
- No escriba números de teléfono: el cliente ya está en este chat (salvo datos de transferencia canónicos).
- Al cerrar un reclamo o una solución: "Fue un placer atender su reclamo. Quedamos a las órdenes."
- Respuestas cortas, claras, sin mediciones técnicas (dBm, IP, ping). Mbps solo al cotizar planes del catálogo.
- Nunca copie instrucciones internas al cliente (Venta suave, Salida WhatsApp, Enrutado, prioridad N).
TXT;
    }
}
