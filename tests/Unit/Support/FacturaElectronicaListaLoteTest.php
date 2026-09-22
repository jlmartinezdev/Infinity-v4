<?php

namespace Tests\Unit\Support;

use App\Support\FacturaElectronicaListaLote;
use Tests\TestCase;

class FacturaElectronicaListaLoteTest extends TestCase
{
    public function test_omite_ya_emitidos_y_respeta_el_tope(): void
    {
        $ids = range(1, 40);
        $r = FacturaElectronicaListaLote::preparar($ids, [1, 2, 3], 30);

        $this->assertSame(37, $r['pendientes']);
        $this->assertCount(30, $r['lote']);
        $this->assertSame(4, $r['lote'][0]);
        $this->assertSame(7, $r['restantes']);
    }

    public function test_sin_pendientes_devuelve_lote_vacio(): void
    {
        $r = FacturaElectronicaListaLote::preparar([10, 11], [10, 11]);

        $this->assertSame([], $r['lote']);
        $this->assertSame(0, $r['pendientes']);
        $this->assertSame(0, $r['restantes']);
    }

    public function test_quita_duplicados_y_ids_invalidos_conservando_orden(): void
    {
        $r = FacturaElectronicaListaLote::preparar([5, 0, 5, 8, -1, '8', 9], [], 30);

        $this->assertSame([5, 8, 9], $r['lote']);
        $this->assertSame(3, $r['pendientes']);
        $this->assertSame(0, $r['restantes']);
    }

    public function test_lote_menor_al_tope_no_deja_restantes(): void
    {
        $r = FacturaElectronicaListaLote::preparar([20, 21], [], 30);

        $this->assertSame([20, 21], $r['lote']);
        $this->assertSame(0, $r['restantes']);
    }
}
