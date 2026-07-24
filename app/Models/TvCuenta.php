<?php

namespace App\Models;

use App\Support\TvAvisoConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TvCuenta extends Model
{
    public const APP_NEBULA = 'nebula';

    public const APP_LUMIX = 'lumix';

    public const MAX_NEBULA = 3;

    public const MAX_LUMIX = 4;

    /** @deprecated Usar maxAsignaciones() según aplicación */
    public const MAX_ASIGNACIONES = self::MAX_NEBULA;

    /** Fallback si no hay config de avisos TV. */
    public const DIAS_AVISO_POR_VENCER = 7;

    public static function diasAvisoPorVencer(): int
    {
        try {
            return TvAvisoConfig::diasAntes();
        } catch (\Throwable) {
            return self::DIAS_AVISO_POR_VENCER;
        }
    }

    protected $table = 'tv_cuentas';

    protected $fillable = [
        'nombre',
        'aplicacion',
        'usuario_app',
        'password',
        'vencimiento_pago',
        'dia_aviso_vencimiento',
        'perfil_1',
        'precio_perfil_1',
        'perfil_2',
        'precio_perfil_2',
        'perfil_3',
        'precio_perfil_3',
        'precio_pantalla_1',
        'precio_pantalla_2',
        'precio_pantalla_3',
        'precio_pantalla_4',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'vencimiento_pago' => 'date',
            'password' => 'encrypted',
            'dia_aviso_vencimiento' => 'integer',
            'precio_perfil_1' => 'decimal:2',
            'precio_perfil_2' => 'decimal:2',
            'precio_perfil_3' => 'decimal:2',
            'precio_pantalla_1' => 'decimal:2',
            'precio_pantalla_2' => 'decimal:2',
            'precio_pantalla_3' => 'decimal:2',
            'precio_pantalla_4' => 'decimal:2',
        ];
    }

    /** @return array<string, string> */
    public static function aplicaciones(): array
    {
        return [
            self::APP_NEBULA => 'Nebula',
            self::APP_LUMIX => 'Lumix',
        ];
    }

    public function esNebula(): bool
    {
        return $this->aplicacion !== self::APP_LUMIX;
    }

    public function esLumix(): bool
    {
        return $this->aplicacion === self::APP_LUMIX;
    }

    public function maxAsignaciones(): int
    {
        return $this->esLumix() ? self::MAX_LUMIX : self::MAX_NEBULA;
    }

    public function etiquetaTipoSlot(): string
    {
        return $this->esLumix() ? 'Pantalla' : 'Perfil';
    }

    public function nombreSlot(int $numero): string
    {
        if ($this->esLumix()) {
            return 'Pantalla '.$numero;
        }

        return match ($numero) {
            1 => $this->perfil_1 ?: 'Perfil 1',
            2 => $this->perfil_2 ?: 'Perfil 2',
            3 => $this->perfil_3 ?: 'Perfil 3',
            default => 'Perfil '.$numero,
        };
    }

    public function precioSlot(int $numero): ?float
    {
        if ($this->esLumix()) {
            $raw = match ($numero) {
                1 => $this->precio_pantalla_1,
                2 => $this->precio_pantalla_2,
                3 => $this->precio_pantalla_3,
                4 => $this->precio_pantalla_4,
                default => null,
            };

            return $raw !== null ? (float) $raw : null;
        }

        $raw = match ($numero) {
            1 => $this->precio_perfil_1,
            2 => $this->precio_perfil_2,
            3 => $this->precio_perfil_3,
            default => null,
        };

        return $raw !== null ? (float) $raw : null;
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(TvCuentaAsignacion::class, 'tv_cuenta_id');
    }

    public function cuposLibres(): int
    {
        return max(0, $this->maxAsignaciones() - $this->asignaciones()->count());
    }

    public function diaVencimientoMensual(): int
    {
        $dia = $this->dia_aviso_vencimiento ?? $this->vencimiento_pago?->day ?? 1;

        return max(1, min(31, (int) $dia));
    }

    /** Fecha de vencimiento del ciclo mensual actual (día configurado en el mes en curso). */
    public function vencimientoDelMesActual(?Carbon $referencia = null): Carbon
    {
        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();
        $dia = min($this->diaVencimientoMensual(), $hoy->copy()->endOfMonth()->day);

        return $hoy->copy()->startOfMonth()->day($dia);
    }

    /** Próxima fecha de vencimiento/pago (campo BD o día del mes en curso). */
    public function fechaVencimientoReferencia(?Carbon $referencia = null): Carbon
    {
        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();

        if ($this->vencimiento_pago) {
            return $this->vencimiento_pago->copy()->startOfDay();
        }

        return $this->vencimientoDelMesActual($hoy);
    }

    /** Renueva la cuenta: próximo vencimiento + 1 mes (mismo día mensual). */
    public function renovarUnMesAdelante(): Carbon
    {
        $hoy = Carbon::today();
        $dia = $this->diaVencimientoMensual();
        $base = $this->fechaVencimientoReferencia($hoy);

        if ($base->lt($hoy)) {
            $mes = $hoy->copy()->addMonthNoOverflow();
            $diaAjustado = min($dia, $mes->copy()->endOfMonth()->day);
            $nueva = $mes->copy()->startOfMonth()->day($diaAjustado);
            if ($nueva->lte($hoy)) {
                $mes = $hoy->copy()->addMonthsNoOverflow(2)->startOfMonth();
                $diaAjustado = min($dia, $mes->copy()->endOfMonth()->day);
                $nueva = $mes->copy()->day($diaAjustado);
            }
        } else {
            $mes = $base->copy()->addMonthNoOverflow();
            $diaAjustado = min($dia, $mes->copy()->endOfMonth()->day);
            $nueva = $mes->copy()->startOfMonth()->day($diaAjustado);
        }

        $this->update(['vencimiento_pago' => $nueva->toDateString()]);

        return $nueva;
    }

    /**
     * @return 'vencido'|'por_vencer'|'ok'
     */
    public function estadoVencimiento(?int $diasPorVencer = null): string
    {
        $diasPorVencer = $diasPorVencer ?? self::diasAvisoPorVencer();
        $hoy = Carbon::today();
        $vencimiento = $this->fechaVencimientoReferencia($hoy);

        if ($hoy->gt($vencimiento)) {
            return 'vencido';
        }

        if ($hoy->diffInDays($vencimiento) <= $diasPorVencer) {
            return 'por_vencer';
        }

        return 'ok';
    }

    /** Positivo = faltan días; negativo = días de atraso. */
    public function diasParaVencimiento(?Carbon $referencia = null): int
    {
        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();
        $vencimiento = $this->fechaVencimientoReferencia($hoy);

        if ($hoy->gt($vencimiento)) {
            return - (int) $vencimiento->diffInDays($hoy);
        }

        return (int) $hoy->diffInDays($vencimiento);
    }

    public function etiquetaEstadoVencimiento(): string
    {
        $dias = $this->diasParaVencimiento();

        return match ($this->estadoVencimiento()) {
            'vencido' => 'Vencido hace '.abs($dias).' día'.(abs($dias) === 1 ? '' : 's'),
            'por_vencer' => $dias === 0 ? 'Vence hoy' : ('Vence en '.$dias.' día'.($dias === 1 ? '' : 's')),
            default => 'Al día',
        };
    }
}
