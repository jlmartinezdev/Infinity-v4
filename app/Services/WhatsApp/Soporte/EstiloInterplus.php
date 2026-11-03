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

    public function texto(): string
    {
        try {
            $param = trim((string) FacturacionParametro::obtener(self::CLAVE, ''));
            if ($param !== '') {
                return $param;
            }
        } catch (\Throwable) {
            // sin tabla aún
        }

        $path = base_path(self::ARCHIVO);
        if (File::isFile($path)) {
            return trim((string) File::get($path));
        }

        return $this->default();
    }

    public function guardar(string $texto): void
    {
        $texto = trim($texto);
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
- Trato formal de usted. Si hay vocativo, usar Señor/Señora + apellido.
- Saludar solo la primera vez del hilo. Si ya hay conversación, no diga Hola, Buenas ni bienvenido.
- No diga te saluda Interplus, lamentamos ni agradecemos.
- No escriba números de teléfono: el cliente ya está en este chat (salvo datos de transferencia canónicos).
- Al cerrar un reclamo o una solución: "Fue un placer atender su reclamo. Quedamos a las órdenes."
- Respuestas cortas, claras, sin mediciones técnicas (dBm, IP, ping). Mbps solo al cotizar planes del catálogo.
- Nunca copie instrucciones internas al cliente (Venta suave, Salida WhatsApp, Enrutado, prioridad N).
TXT;
    }
}
