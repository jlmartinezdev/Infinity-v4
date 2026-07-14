<?php

namespace App\Models;

use App\Services\Sifen\SifenXmlManipulator;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    use Auditable;

    protected $table = 'factura_electronicas';

    protected $fillable = [
        'cliente_id',
        'es_ocasional',
        'receptor_documento',
        'receptor_nombre',
        'receptor_apellido',
        'receptor_direccion',
        'receptor_email',
        'receptor_telefono',
        'tipo_documento',
        'estado',
        'numero_timbrado',
        'timbrado_vigencia_desde',
        'timbrado_vigencia_hasta',
        'establecimiento',
        'punto_emision',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'total_impuestos',
        'total',
        'observaciones',
        'datos_complementarios',
        'usuario_id',
        'set_cdc',
        'set_codigo_seguridad',
        'set_serie',
        'set_fecha_emision_de',
        'set_qr_url',
        'set_fecha_autorizacion',
        'set_estado_envio',
        'set_nro_lote',
        'set_xml_respuesta',
        'xml_path',
        'pdf_path',
        'sifen_api_documento_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'timbrado_vigencia_desde' => 'date',
            'timbrado_vigencia_hasta' => 'date',
            'set_fecha_emision_de' => 'datetime',
            'set_fecha_autorizacion' => 'datetime',
            'subtotal' => 'decimal:2',
            'total_impuestos' => 'decimal:2',
            'total' => 'decimal:2',
            'tipo_cambio' => 'decimal:4',
            'datos_complementarios' => 'array',
            'es_ocasional' => 'boolean',
        ];
    }

    public function esOcasional(): bool
    {
        return (bool) $this->es_ocasional;
    }

    public function lotePendienteSifen(): bool
    {
        return $this->estado === 'borrador'
            && filled($this->set_nro_lote)
            && in_array($this->set_estado_envio, ['en_proceso', 'pendiente'], true);
    }

    public function scopeLotePendienteSifen($query)
    {
        return $query->where('estado', 'borrador')
            ->whereNotNull('set_nro_lote')
            ->where('set_nro_lote', '!=', '')
            ->whereIn('set_estado_envio', ['en_proceso', 'pendiente']);
    }

    public function receptorNombreCompleto(): string
    {
        if ($this->esOcasional()) {
            return trim(($this->receptor_nombre ?? '').' '.($this->receptor_apellido ?? ''));
        }

        return trim(($this->cliente?->nombre ?? '').' '.($this->cliente?->apellido ?? ''));
    }

    public function receptorDocumentoEfectivo(): ?string
    {
        if ($this->esOcasional()) {
            return $this->receptor_documento;
        }

        return $this->cliente?->cedula;
    }

    public function receptorDireccionEfectiva(): ?string
    {
        if ($this->esOcasional()) {
            return $this->receptor_direccion;
        }

        return $this->cliente?->direccion;
    }

    public function receptorEmailEfectivo(): ?string
    {
        if ($this->esOcasional()) {
            return $this->receptor_email;
        }

        return $this->cliente?->email;
    }

    public function receptorTelefonoEfectivo(): ?string
    {
        if ($this->esOcasional()) {
            return $this->receptor_telefono;
        }

        return $this->cliente?->telefono;
    }

    public function codigoClienteSifen(): int
    {
        if ($this->esOcasional()) {
            return (int) $this->id;
        }

        return (int) ($this->cliente_id ?? 0);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class, 'factura_electronica_id')->orderBy('id');
    }

    public function scopePruebaSifen(Builder $query): Builder
    {
        return $query->where('observaciones', 'like', 'PRUEBA SIFEN%');
    }

    /**
     * Número de factura formateado (establecimiento-punto-número) Paraguay.
     */
    public function getNumeroCompletoAttribute(): ?string
    {
        if ($this->numero === null) {
            return null;
        }
        return sprintf(
            '%03d-%03d-%07d',
            $this->establecimiento,
            $this->punto_emision,
            $this->numero
        );
    }

    public static function tiposDocumento(): array
    {
        return [
            'factura_contado' => 'Factura al contado',
            'factura_credito' => 'Factura a crédito',
            'nota_credito' => 'Nota de crédito',
            'nota_debito' => 'Nota de débito',
            'autofactura' => 'Autofactura',
            'nota_remision' => 'Nota de remisión',
        ];
    }

    /** Tipos que requieren sifen-api (datos complementarios SIFEN). */
    public static function tiposSoloApi(): array
    {
        return ['autofactura', 'nota_remision'];
    }

    public static function tiposConDocumentoAsociado(): array
    {
        return ['nota_credito', 'nota_debito'];
    }

    public function requiereApi(): bool
    {
        return in_array($this->tipo_documento, array_merge(
            self::tiposSoloApi(),
            self::tiposConDocumentoAsociado()
        ), true);
    }

    public function codigoTipoDocumentoSifen(): int
    {
        return (int) config('sifen.tipos_documento.'.$this->tipo_documento, 1);
    }

    public function descripcionTipoDocumentoSifen(): string
    {
        $codigo = $this->codigoTipoDocumentoSifen();

        return config('sifen.descripciones_tipo_documento.'.$codigo, 'Factura electrónica');
    }

    public function tituloKude(): string
    {
        return 'KuDE de '.$this->descripcionTipoDocumentoSifen();
    }

    public function esFacturaComercial(): bool
    {
        return in_array($this->tipo_documento, ['factura_contado', 'factura_credito'], true);
    }

    public function tipoTransaccionKude(): string
    {
        return match ($this->tipo_documento) {
            'nota_remision' => 'Traslado de mercaderías',
            'nota_credito' => 'Nota de crédito electrónica',
            'nota_debito' => 'Nota de débito electrónica',
            'autofactura' => 'Autofactura electrónica',
            default => 'Prestación de servicios',
        };
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function lineasComplementariasKude(): array
    {
        $datos = $this->datos_complementarios ?? [];
        $lineas = [];

        if (in_array($this->tipo_documento, ['nota_credito', 'nota_debito'], true)) {
            $motivo = (int) ($datos['motivo_emision'] ?? 0);
            if ($motivo > 0) {
                $lineas[] = [
                    'label' => 'Motivo de emisión',
                    'value' => config('sifen.motivos_emision_nc_nd.'.$motivo, (string) $motivo),
                ];
            }
            $cdcRef = $datos['documento_asociado']['cdc'] ?? null;
            if ($cdcRef) {
                $lineas[] = [
                    'label' => 'CDC documento asociado',
                    'value' => $cdcRef,
                ];
            }
        }

        if ($this->tipo_documento === 'autofactura') {
            $vendedor = $datos['vendedor'] ?? [];
            if (! empty($vendedor['nombre'])) {
                $lineas[] = ['label' => 'Vendedor', 'value' => (string) $vendedor['nombre']];
            }
            if (! empty($vendedor['numero_documento'])) {
                $lineas[] = ['label' => 'Documento vendedor', 'value' => (string) $vendedor['numero_documento']];
            }
            $lugar = $datos['lugar_provision']['direccion'] ?? null;
            if ($lugar) {
                $lineas[] = ['label' => 'Lugar de provisión', 'value' => $lugar];
            }
        }

        if ($this->tipo_documento === 'nota_remision') {
            $remision = $datos['remision'] ?? [];
            $motivo = (int) ($remision['motivo_traslado'] ?? 0);
            if ($motivo > 0) {
                $lineas[] = [
                    'label' => 'Motivo de traslado',
                    'value' => config('sifen.motivos_traslado_remision.'.$motivo, (string) $motivo),
                ];
            }
            if (! empty($remision['kilometros'])) {
                $lineas[] = [
                    'label' => 'Kilómetros estimados',
                    'value' => (string) $remision['kilometros'].' km',
                ];
            }
        }

        return $lineas;
    }

    public static function estados(): array
    {
        return [
            'borrador' => 'Borrador',
            'emitida' => 'Emitida',
            'anulada' => 'Anulada',
        ];
    }

    /**
     * Fecha/hora del DE para KuDE y pantallas (prioriza set_fecha_emision_de y XML firmado).
     */
    public function fechaEmisionDeEfectiva(): Carbon
    {
        $tz = config('sifen.timezone', 'America/Asuncion');

        if ($this->set_fecha_emision_de) {
            return $this->set_fecha_emision_de->copy()->timezone($tz);
        }

        if ($this->xml_path) {
            $ruta = storage_path($this->xml_path);
            if (is_file($ruta)) {
                $desdeXml = SifenXmlManipulator::extraerFechaEmisionDe((string) file_get_contents($ruta));
                if ($desdeXml) {
                    return $desdeXml->timezone($tz);
                }
            }
        }

        if ($this->set_fecha_autorizacion) {
            return $this->set_fecha_autorizacion->copy()->timezone($tz);
        }

        return Carbon::parse($this->fecha_emision, $tz)->startOfDay();
    }

    /**
     * Recalcula subtotal, total_impuestos y total desde los detalles.
     */
    public function recalcularTotales(): void
    {
        $subtotal = $this->detalles->sum('subtotal');
        $totalImpuestos = $this->detalles->sum('monto_impuesto');
        $total = $this->detalles->sum('total');
        $this->update([
            'subtotal' => $subtotal,
            'total_impuestos' => $totalImpuestos,
            'total' => $total,
        ]);
    }
}
