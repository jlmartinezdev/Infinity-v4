<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Canje extends Model
{
    protected $table = 'canjes';

    public const MOD_RETIRO = 'retiro_oficina';

    public const MOD_DESCUENTO = 'descuento_factura';

    public const MOD_AUTOMATICO = 'automatico';

    public const MOD_APROBACION = 'requiere_aprobacion';

    public const MOD_SORTEO = 'sorteo';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_EN_PREPARACION = 'EN_PREPARACION';

    public const ESTADO_LISTO = 'LISTO_PARA_RETIRAR';

    public const ESTADO_ENTREGADO = 'ENTREGADO';

    public const ESTADO_APLICADO = 'APLICADO';

    public const ESTADO_APROBADO = 'APROBADO';

    public const ESTADO_CANCELADO = 'CANCELADO';

    protected $fillable = [
        'cliente_id',
        'premio_id',
        'puntos_usados',
        'modalidad',
        'estado',
        'notas',
        'staff_user_id',
        'prepared_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'factura_interna_id',
    ];

    protected function casts(): array
    {
        return [
            'puntos_usados' => 'integer',
            'prepared_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function premio(): BelongsTo
    {
        return $this->belongsTo(Premio::class, 'premio_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id', 'usuario_id');
    }

    public function facturaInterna(): BelongsTo
    {
        return $this->belongsTo(FacturaInterna::class, 'factura_interna_id');
    }

    public function scopeDelMesActual(Builder $query, int $clienteId): Builder
    {
        return $query
            ->where('cliente_id', $clienteId)
            ->where('estado', '!=', self::ESTADO_CANCELADO)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
    }

    public function estaAbierto(): bool
    {
        return ! in_array($this->estado, [self::ESTADO_ENTREGADO, self::ESTADO_APLICADO, self::ESTADO_CANCELADO], true);
    }

    public static function modalidades(): array
    {
        return [
            self::MOD_RETIRO => 'Retiro en oficina',
            self::MOD_DESCUENTO => 'Descuento en factura',
            self::MOD_AUTOMATICO => 'Automático',
            self::MOD_APROBACION => 'Requiere aprobación',
            self::MOD_SORTEO => 'Sorteo',
        ];
    }

    public static function estados(): array
    {
        return [
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_EN_PREPARACION => 'En preparación',
            self::ESTADO_LISTO => 'Listo para retirar',
            self::ESTADO_ENTREGADO => 'Entregado',
            self::ESTADO_APLICADO => 'Aplicado en factura',
            self::ESTADO_APROBADO => 'Aprobado (próxima factura)',
            self::ESTADO_CANCELADO => 'Cancelado',
        ];
    }

    /**
     * @return array{estado_portal: string, estado_label: string}
     */
    public function estadoPortal(): array
    {
        if ($this->estado === self::ESTADO_CANCELADO) {
            return ['estado_portal' => 'cancelado', 'estado_label' => 'Cancelado'];
        }

        if (in_array($this->estado, [self::ESTADO_APLICADO, self::ESTADO_ENTREGADO], true)) {
            $enFactura = $this->modalidad === self::MOD_DESCUENTO
                || $this->premio?->esDescuentoFactura();

            $label = 'Aplicado';
            if ($enFactura) {
                $label = $this->factura_interna_id
                    ? 'Aplicado en factura #'.$this->factura_interna_id
                    : 'Aplicado en factura';
            }

            return [
                'estado_portal' => 'aplicado',
                'estado_label' => $label,
            ];
        }

        return ['estado_portal' => 'pendiente', 'estado_label' => 'Pendiente'];
    }

    public function toPortalArray(): array
    {
        return [
            'id' => $this->id,
            'premio_id' => $this->premio_id,
            'premio' => $this->premio?->nombre,
            'premio_tipo' => $this->premio?->tipo,
            'puntos_usados' => (int) $this->puntos_usados,
            'modalidad' => $this->modalidad,
            'descuento_porcentaje' => $this->premio?->descuento_porcentaje !== null
                ? (float) $this->premio->descuento_porcentaje
                : null,
            'descuento_monto' => $this->premio?->descuento_monto !== null
                ? (float) $this->premio->descuento_monto
                : null,
            'estado' => $this->estado,
            'estado_portal' => $this->estadoPortal()['estado_portal'],
            'estado_label' => $this->estadoPortal()['estado_label'],
            'imagen_url' => $this->premio?->imagenUrl(),
            'gratis' => (int) $this->puntos_usados === 0,
            'factura_interna_id' => $this->factura_interna_id ? (int) $this->factura_interna_id : null,
            'factura_numero' => $this->factura_interna_id ? (string) $this->factura_interna_id : null,
            'notas' => $this->notas,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'completed_at' => optional($this->completed_at)?->toIso8601String(),
        ];
    }
}
