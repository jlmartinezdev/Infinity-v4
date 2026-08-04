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

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'puntos',
        'activa',
        'evento',
        'condiciones',
    ];

    protected function casts(): array
    {
        return [
            'puntos' => 'integer',
            'activa' => 'boolean',
            'condiciones' => 'array',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public static function eventos(): array
    {
        return [
            self::EVENTO_MANUAL => 'Manual / ajuste',
            self::EVENTO_PAGO => 'Al recibir un pago',
            self::EVENTO_BIENVENIDA => 'Bienvenida (primer acceso app)',
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
}
