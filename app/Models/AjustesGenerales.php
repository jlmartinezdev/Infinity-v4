<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AjustesGenerales extends Model
{
    protected $table = 'ajustes_generales';

    protected $fillable = [
        'nombre_empresa',
        'logo',
        'telefono',
        'email',
        'direccion',
        'sitio_web',
    ];

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
