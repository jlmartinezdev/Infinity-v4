<?php

namespace Tests\Unit\Support;

use App\Support\ApWirelessCaidaVentana;
use Carbon\Carbon;
use Tests\TestCase;

class ApWirelessCaidaVentanaTest extends TestCase
{
    public function test_recorta_a_24_horas_y_cierra_la_caida_abierta_en_ahora(): void
    {
        $ahora = Carbon::parse('2026-11-20 16:00:00');
        $filas = [
            ['inicio' => Carbon::parse('2026-11-18 10:00:00'), 'fin' => Carbon::parse('2026-11-18 11:00:00')],
            ['inicio' => Carbon::parse('2026-11-19 20:00:00'), 'fin' => Carbon::parse('2026-11-19 20:30:00')],
            ['inicio' => Carbon::parse('2026-11-20 15:00:00'), 'fin' => null],
        ];

        $salida = ApWirelessCaidaVentana::recortar($filas, $ahora, null, false);

        $this->assertCount(2, $salida);
        $this->assertSame(Carbon::parse('2026-11-19 20:00:00')->toIso8601String(), $salida[0]['inicio']);
        $this->assertSame(Carbon::parse('2026-11-19 20:30:00')->toIso8601String(), $salida[0]['fin']);
        $this->assertSame(Carbon::parse('2026-11-20 15:00:00')->toIso8601String(), $salida[1]['inicio']);
        $this->assertSame($ahora->toIso8601String(), $salida[1]['fin']);
    }

    public function test_si_no_hay_filas_usa_la_caida_actual(): void
    {
        $ahora = Carbon::parse('2026-11-20 16:00:00');
        $desde = Carbon::parse('2026-11-20 14:10:00');

        $salida = ApWirelessCaidaVentana::recortar([], $ahora, $desde, true);

        $this->assertSame([
            ['inicio' => $desde->toIso8601String(), 'fin' => $ahora->toIso8601String(), 'abierta' => true],
        ], $salida);
    }
}
