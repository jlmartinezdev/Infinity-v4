<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AjustesGenerales extends Model
{
    protected $table = 'ajustes_generales';

    public const RECIBO_MODO_CON_GRAFICO = 'con_grafico';

    public const RECIBO_MODO_SIN_GRAFICO = 'sin_grafico';

    protected $fillable = [
        'nombre_empresa',
        'logo',
        'telefono',
        'email',
        'direccion',
        'sitio_web',
        'recibo_modo',
        'recibo_papel_mm',
    ];

    /**
     * Ancho del papel del recibo en mm (56 o 80). Valor por defecto 80.
     */
    public function reciboPapelMm(): int
    {
        $v = (string) ($this->recibo_papel_mm ?? '80');

        return $v === '56' ? 56 : 80;
    }

    /**
     * Recibo solo texto (impresora matricial / sin logo ni imágenes).
     */
    public function reciboSinGrafico(): bool
    {
        return ($this->recibo_modo ?? self::RECIBO_MODO_CON_GRAFICO) === self::RECIBO_MODO_SIN_GRAFICO;
    }

    /** Obtiene el único registro de ajustes (id = 1). */
    public static function obtener(): ?self
    {
        return static::first();
    }

    /**
     * URL pública del logo (disco public).
     * En peticiones HTTP usa el mismo host/puerto de la solicitud (sirve al abrir por IP o dominio sin depender de APP_URL).
     * En consola/colas usa la URL del disco public (APP_URL).
     */
    public function urlLogo(): ?string
    {
        if (blank($this->logo)) {
            return null;
        }

        $relative = '/storage/'.ltrim(str_replace('\\', '/', $this->logo), '/');

        if (! app()->runningInConsole() && request()->getSchemeAndHttpHost()) {
            return rtrim(request()->root(), '/').$relative;
        }

        return Storage::disk('public')->url($this->logo);
    }
}
