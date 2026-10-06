<?php

namespace Tests\Unit\Support;

use App\Support\FacturaElectronicaListaUnicidad;
use Tests\TestCase;

class FacturaElectronicaListaUnicidadTest extends TestCase
{
    public function test_separa_libres_y_ya_listados(): void
    {
        $r = FacturaElectronicaListaUnicidad::separar(
            [10, 11, 10, 12],
            [11 => 'Lista_solocedula_1']
        );

        $this->assertSame([10, 12], $r['libres']);
        $this->assertSame([11 => 'Lista_solocedula_1'], $r['omitidos']);
    }

    public function test_mensaje_agrupa_por_lista(): void
    {
        $msg = FacturaElectronicaListaUnicidad::mensajeOmitidos([
            1 => 'A',
            2 => 'A',
            3 => 'B',
        ]);

        $this->assertStringContainsString('Se omitieron 3 ya listados', $msg);
        $this->assertStringContainsString('2 en «A»', $msg);
        $this->assertStringContainsString('1 en «B»', $msg);
    }
}
