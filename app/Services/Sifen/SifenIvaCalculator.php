<?php

namespace App\Services\Sifen;

/**
 * Cálculos de IVA por ítem y totales según MT SIFEN v150 (precio IVA incluido).
 */
class SifenIvaCalculator
{
    public const AFEC_GRAVADO = 1;

    public const AFEC_EXONERADO = 2;

    public const AFEC_EXENTO = 3;

    /**
     * @return array{
     *   iAfecIVA: int,
     *   dDesAfecIVA: string,
     *   dPropIVA: float,
     *   dTasaIVA: int,
     *   dBasGravIVA: float,
     *   dLiqIVAItem: float,
     *   dBasExe: float,
     *   dPUniProSer: float,
     *   dTotBruOpeItem: float,
     *   dTotOpeItem: float,
     *   subtotalGrav5: float,
     *   subtotalGrav10: float,
     *   subtotalExento: float,
     *   subtotalExonerado: float,
     * }
     */
    public function calcularItem(
        float $cantidad,
        float $totalLinea,
        float $porcentajeImpuesto,
    ): array {
        $cantidad = max($cantidad, 0.0001);
        $totalLinea = round($totalLinea, 8);
        $precioUnitario = round($totalLinea / $cantidad, 8);

        $tasa = $this->normalizarTasa($porcentajeImpuesto);
        $afectacion = $this->afectacionDesdeTasa($tasa);

        $baseGravada = 0.0;
        $liquidacion = 0.0;
        $baseExenta = 0.0;

        if ($afectacion === self::AFEC_GRAVADO && $tasa > 0) {
            $divisor = 1 + ($tasa / 100);
            $baseGravada = round($totalLinea / $divisor, 8);
            $liquidacion = round($totalLinea - $baseGravada, 8);
        } elseif ($afectacion === self::AFEC_EXENTO || $afectacion === self::AFEC_EXONERADO) {
            $baseExenta = $totalLinea;
        }

        return [
            'iAfecIVA' => $afectacion,
            'dDesAfecIVA' => $this->descripcionAfectacion($afectacion),
            'dPropIVA' => $afectacion === self::AFEC_GRAVADO ? 100 : 0,
            'dTasaIVA' => $tasa,
            'dBasGravIVA' => $baseGravada,
            'dLiqIVAItem' => $liquidacion,
            'dBasExe' => $baseExenta,
            'dPUniProSer' => $precioUnitario,
            'dTotBruOpeItem' => $totalLinea,
            'dTotOpeItem' => $totalLinea,
            'subtotalGrav5' => $tasa === 5 ? $totalLinea : 0,
            'subtotalGrav10' => $tasa === 10 ? $totalLinea : 0,
            'subtotalExento' => $afectacion === self::AFEC_EXENTO ? $totalLinea : 0,
            'subtotalExonerado' => $afectacion === self::AFEC_EXONERADO ? $totalLinea : 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsCalculados
     * @return array<string, float>
     */
    public function calcularTotales(array $itemsCalculados): array
    {
        $dSub5 = 0.0;
        $dSub10 = 0.0;
        $dSubExe = 0.0;
        $dSubExo = 0.0;
        $dIVA5 = 0.0;
        $dIVA10 = 0.0;
        $dTotOpe = 0.0;

        foreach ($itemsCalculados as $item) {
            $dSub5 += $item['subtotalGrav5'];
            $dSub10 += $item['subtotalGrav10'];
            $dSubExe += $item['subtotalExento'];
            $dSubExo += $item['subtotalExonerado'];
            $dTotOpe += $item['dTotOpeItem'];

            if ((int) $item['dTasaIVA'] === 5) {
                $dIVA5 += $item['dLiqIVAItem'];
            }
            if ((int) $item['dTasaIVA'] === 10) {
                $dIVA10 += $item['dLiqIVAItem'];
            }
        }

        $dTotGralOpe = round($dTotOpe, 8);
        $dTotIVA = round($dIVA5 + $dIVA10, 8);

        return [
            'dSubExe' => round($dSubExe, 8),
            'dSubExo' => round($dSubExo, 8),
            'dSub5' => round($dSub5, 8),
            'dSub10' => round($dSub10, 8),
            'dTotOpe' => round($dTotOpe, 8),
            'dTotDesc' => 0,
            'dTotDescGlotem' => 0,
            'dTotAntItem' => 0,
            'dTotAnt' => 0,
            'dPorcDescTotal' => 0,
            'dDescTotal' => 0,
            'dAnticipo' => 0,
            'dRedon' => 0,
            'dTotGralOpe' => $dTotGralOpe,
            'dIVA5' => round($dIVA5, 8),
            'dIVA10' => round($dIVA10, 8),
            'dTotIVA' => $dTotIVA,
            'dBaseGrav5' => round($dSub5 > 0 ? $dSub5 / 1.05 : 0, 8),
            'dBaseGrav10' => round($dSub10 > 0 ? $dSub10 / 1.10 : 0, 8),
        ];
    }

    private function normalizarTasa(float $porcentaje): int
    {
        $tasa = (int) round($porcentaje);

        return in_array($tasa, [5, 10], true) ? $tasa : 0;
    }

    private function afectacionDesdeTasa(int $tasa): int
    {
        return match ($tasa) {
            5, 10 => self::AFEC_GRAVADO,
            default => self::AFEC_EXENTO,
        };
    }

    private function descripcionAfectacion(int $afectacion): string
    {
        return match ($afectacion) {
            self::AFEC_GRAVADO => 'Gravado IVA',
            self::AFEC_EXONERADO => 'Exonerado (Art. 83- Ley 125/91)',
            self::AFEC_EXENTO => 'Exento',
            default => 'Exento',
        };
    }
}
