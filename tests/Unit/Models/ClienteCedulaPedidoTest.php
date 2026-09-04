<?php

namespace Tests\Unit\Models;

use App\Models\Cliente;
use Tests\TestCase;

class ClienteCedulaPedidoTest extends TestCase
{
    public function test_quita_separadores_de_cedula(): void
    {
        $this->assertSame('5072899', Cliente::cedulaSinSeparadores('5.072.899'));
        $this->assertSame('5072899', Cliente::cedulaSinSeparadores('5072899'));
        $this->assertSame('800392400', Cliente::cedulaSinSeparadores('80039240-0'));
    }
}
