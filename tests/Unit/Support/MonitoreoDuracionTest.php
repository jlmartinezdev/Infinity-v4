<?php

namespace Tests\Unit\Support;

use App\Support\MonitoreoDuracion;
use Carbon\Carbon;
use Tests\TestCase;

class MonitoreoDuracionTest extends TestCase
{
    public function test_formatea_minutos_y_horas(): void
    {
        $desde = Carbon::parse('2026-08-28 12:00:00');
        $ahora = Carbon::parse('2026-08-28 14:17:00');

        $this->assertSame('2h 17m', MonitoreoDuracion::formatear($desde, $ahora));
    }

    public function test_menos_de_un_minuto(): void
    {
        $desde = Carbon::parse('2026-08-28 12:00:00');
        $ahora = Carbon::parse('2026-08-28 12:00:40');

        $this->assertSame('< 1 min', MonitoreoDuracion::formatear($desde, $ahora));
    }

    public function test_null_si_no_hay_fecha(): void
    {
        $this->assertNull(MonitoreoDuracion::formatear(null));
    }
}
