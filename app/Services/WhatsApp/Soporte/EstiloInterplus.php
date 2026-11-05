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
            '/\bueno\b|n[°º]\.?\s*de cuenta|n[uú]mero de cuenta|\bme pases\b|\bpuedes\b/iu',
            $t
        );
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
