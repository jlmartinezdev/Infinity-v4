<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRegla extends Model
{
    protected $table = 'loyalty_reglas';

    public const EVENTO_MANUAL = 'manual';

    public const EVENTO_PAGO = 'pago_recibido';

    public const EVENTO_BIENVENIDA = 'bienvenida';

    public const FRECUENCIA_UNICA = 'unica_vez';

    public const FRECUENCIA_MENSUAL = 'mensual';

    public const FRECUENCIA_EVENTO = 'por_evento';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'puntos',
        'activa',
        'evento',
        'frecuencia',
        'orden',
        'fase',
        'visible_portal',
        'condiciones',
    ];

    protected function casts(): array
    {
        return [
            'puntos' => 'integer',
            'activa' => 'boolean',
            'orden' => 'integer',
            'fase' => 'integer',
            'visible_portal' => 'boolean',
            'condiciones' => 'array',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopeVisiblesPortal(Builder $query): Builder
    {
        return $query->activas()
            ->where('visible_portal', true)
            ->orderBy('orden')
            ->orderBy('nombre');
    }

    public static function eventos(): array
    {
        return [
            self::EVENTO_MANUAL => 'Manual / ajuste',
            self::EVENTO_PAGO => 'Al recibir un pago',
            self::EVENTO_BIENVENIDA => 'Bienvenida (primer acceso app)',
        ];
    }

    /** @return array<string, string> */
    public static function frecuencias(): array
    {
        return [
            self::FRECUENCIA_UNICA => 'Una sola vez',
            self::FRECUENCIA_MENSUAL => 'Mensual',
            self::FRECUENCIA_EVENTO => 'Por evento',
        ];
    }

    public const DIAS_PAGO_CONFIGURABLES = 5;

    /**
     * Mapa día del mes (1–31) => puntos. Vacío = usar campo puntos fijo.
     *
     * @return array<int, int>
     */
    public function puntosPorDia(): array
    {
        $raw = $this->condiciones['puntos_por_dia'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $dia => $pts) {
            $d = (int) $dia;
            $p = (int) $pts;
            if ($d >= 1 && $d <= 31 && $p > 0) {
                $out[$d] = $p;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @return array<int, int> días 1..N con pts (0 si vacío)
     */
    public function puntosPorDiaHasta(int $maxDia = self::DIAS_PAGO_CONFIGURABLES): array
    {
        $mapa = $this->puntosPorDia();
        $out = [];
        for ($d = 1; $d <= $maxDia; $d++) {
            $out[$d] = (int) ($mapa[$d] ?? 0);
        }

        return $out;
    }

    public function puntosParaDiaMes(int $dia): int
    {
        $mapa = $this->puntosPorDia();
        if ($mapa !== []) {
            return (int) ($mapa[$dia] ?? 0);
        }

        return (int) $this->puntos;
    }

    public function usaPuntosPorDia(): bool
    {
        return $this->puntosPorDia() !== [];
    }

    /**
     * @param  array<int|string, mixed>  $mapa
     * @return array{solo_factura_servicio: bool, puntos_por_dia: array<string, int>}
     */
    public static function condicionesPagoDesdeMapa(array $mapa, int $maxDia = self::DIAS_PAGO_CONFIGURABLES): array
    {
        $limpio = [];
        foreach ($mapa as $dia => $pts) {
            $d = (int) $dia;
            $p = (int) $pts;
            if ($d >= 1 && $d <= $maxDia && $p > 0) {
                $limpio[(string) $d] = $p;
            }
        }
        ksort($limpio, SORT_NUMERIC);

        return [
            'solo_factura_servicio' => true,
            'puntos_por_dia' => $limpio,
        ];
    }

    public function frecuenciaInferida(): string
    {
        if (filled($this->frecuencia)) {
            return (string) $this->frecuencia;
        }

        return match ($this->evento) {
            self::EVENTO_BIENVENIDA => self::FRECUENCIA_UNICA,
            self::EVENTO_PAGO => self::FRECUENCIA_MENSUAL,
            default => self::FRECUENCIA_EVENTO,
        };
    }

    public function toPortalArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'puntos' => (int) $this->puntos,
            'frecuencia' => $this->frecuenciaInferida(),
            'activo' => (bool) $this->activa,
            'orden' => (int) $this->orden,
            'fase' => $this->fase !== null ? (int) $this->fase : null,
        ];
    }
}
