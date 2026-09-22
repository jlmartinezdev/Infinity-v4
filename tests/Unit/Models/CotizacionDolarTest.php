<?php

namespace Tests\Unit\Models;

use App\Models\CotizacionDolar;
use Tests\TestCase;

class CotizacionDolarTest extends TestCase
{
    public function test_etiqueta_formatea_guaranies_sin_decimales_de_mas(): void
    {
        $cotizacion = new CotizacionDolar(['valor' => 7300]);

        $this->assertSame('1 USD = 7.300 Gs', $cotizacion->etiqueta());
        $this->assertSame('1 USD = 7.300,5 Gs', CotizacionDolar::etiquetaDe(7300.5));
        $this->assertSame('—', CotizacionDolar::etiquetaDe(null));
    }
}
