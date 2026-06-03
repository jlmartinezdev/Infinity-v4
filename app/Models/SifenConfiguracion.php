<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SifenConfiguracion extends Model
{
    protected $table = 'sifen_configuracion';

    protected $hidden = [
        'certificado_password',
        'csc_token',
    ];

    protected $fillable = [
        'ruc',
        'dv_ruc',
        'tipo_contribuyente',
        'razon_social',
        'nombre_fantasia',
        'numero_timbrado',
        'establecimiento',
        'punto_expedicion',
        'timbrado_vigencia_desde',
        'timbrado_vigencia_hasta',
        'codigo_actividad_economica',
        'descripcion_actividad_economica',
        'direccion',
        'numero_casa',
        'departamento',
        'departamento_descripcion',
        'distrito',
        'distrito_descripcion',
        'ciudad',
        'ciudad_descripcion',
        'telefono',
        'email',
        'csc_id',
        'csc_token',
        'certificado_password',
        'ultimo_numero_factura',
        'serie_actual',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'timbrado_vigencia_desde' => 'date',
            'timbrado_vigencia_hasta' => 'date',
            'activo' => 'boolean',
        ];
    }

    public static function activa(): ?self
    {
        return static::where('activo', true)->orderBy('id')->first();
    }

    public static function obtenerOInicializar(): self
    {
        $config = static::orderBy('id')->first();

        if ($config) {
            return $config;
        }

        return static::create([
            'ruc' => '0000000',
            'dv_ruc' => 0,
            'tipo_contribuyente' => 2,
            'razon_social' => config('app.name', 'Empresa'),
            'numero_timbrado' => '00000000',
            'establecimiento' => 1,
            'punto_expedicion' => 1,
            'timbrado_vigencia_desde' => now()->toDateString(),
            'direccion' => '—',
            'telefono' => '000000',
            'email' => 'facturacion@example.com',
            'activo' => true,
        ]);
    }

    public function passwordCertificado(): ?string
    {
        if (filled($this->certificado_password)) {
            try {
                return Crypt::decryptString($this->certificado_password);
            } catch (\Throwable) {
                return null;
            }
        }

        $env = config('sifen.certificado.password');

        return filled($env) ? (string) $env : null;
    }

    public function cscTokenEfectivo(): ?string
    {
        return $this->csc_token ?: config('sifen.csc.token');
    }

    public function cscIdEfectivo(): string
    {
        return $this->csc_id ?: config('sifen.csc.id', '0001');
    }

    public function siguienteNumeroDocumento(): int
    {
        return $this->ultimo_numero_factura + 1;
    }

    public function registrarNumeroEmitido(int $numero): void
    {
        if ($numero > $this->ultimo_numero_factura) {
            $this->update(['ultimo_numero_factura' => $numero]);
        }
    }
}
