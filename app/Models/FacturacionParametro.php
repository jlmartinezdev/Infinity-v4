<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FacturacionParametro extends Model
{
    protected $table = 'facturacion_parametros';

    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    /**
     * Obtener valor de parámetro (cacheado).
     */
    public static function obtener(string $clave, $default = null)
    {
        $cacheKey = 'facturacion_param_' . $clave;
        return Cache::remember($cacheKey, 300, function () use ($clave, $default) {
            $p = static::find($clave);
            if (!$p || $p->valor === null || $p->valor === '') {
                return $default;
            }
            $v = $p->valor;
            if (is_numeric($v)) {
                return strpos($v, '.') !== false ? (float) $v : (int) $v;
            }
            return $v;
        });
    }

    /**
     * Establecer valor y limpiar caché.
     */
    public static function establecer(string $clave, $valor, ?string $descripcion = null): void
    {
        static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => (string) $valor, 'descripcion' => $descripcion]
        );
        Cache::forget('facturacion_param_' . $clave);
    }

    public static function diasVencimientoFactura(): int
    {
        return (int) static::obtener('dias_vencimiento_factura', 10);
    }

    public static function diasParaSuspender(): int
    {
        return (int) static::obtener('dias_para_suspender', 5);
    }

    public static function diaCreacionFacturaAutomatica(): int
    {
        return (int) static::obtener('dia_creacion_factura_automatica', 1);
    }

    public static function notificacionTipoPlataforma(): string
    {
        return (string) static::obtener('notificacion_tipo_plataforma', 'web');
    }

    public static function notificacionDiasAntes(): int
    {
        return (int) static::obtener('notificacion_dias_antes', 3);
    }

    public static function horaCorteAutomatico(): string
    {
        return (string) static::obtener('hora_corte_automatico', '00:01');
    }

    public static function diaFechaCobro(): int
    {
        return (int) static::obtener('dia_fecha_cobro', 1);
    }

    public static function diaVencimiento(): int
    {
        return (int) static::obtener('dia_vencimiento', 5);
    }

    public static function diaCorte(): int
    {
        return (int) static::obtener('dia_corte', 6);
    }
}
