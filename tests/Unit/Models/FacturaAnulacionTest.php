<?php

namespace Tests\Unit\Models;

use App\Models\Factura;
use Carbon\Carbon;
use Tests\TestCase;

class FacturaAnulacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['sifen.cancelacion_horas' => 48]);
    }

    public function test_permite_cancelar_dentro_de_la_ventana(): void
    {
        $factura = $this->facturaAutorizada(now()->subHours(2));

        $this->assertTrue($factura->puedeCancelarPorEvento());
        $this->assertTrue($factura->puedePrepararNotaCredito());
        $this->assertTrue($factura->fechaLimiteCancelacionEvento()->isFuture());
    }

    public function test_no_permite_cancelar_despues_de_48_horas(): void
    {
        $factura = $this->facturaAutorizada(now()->subHours(49));

        $this->assertFalse($factura->puedeCancelarPorEvento());
        $this->assertTrue($factura->puedePrepararNotaCredito());
    }

    public function test_no_permite_anular_si_no_esta_autorizada(): void
    {
        $factura = $this->facturaAutorizada(now()->subHour());
        $factura->set_estado_envio = 'pendiente';

        $this->assertFalse($factura->puedeCancelarPorEvento());
        $this->assertFalse($factura->puedePrepararNotaCredito());
    }

    private function facturaAutorizada(Carbon $autorizadaEn): Factura
    {
        $factura = new Factura([
            'estado' => 'emitida',
            'tipo_documento' => 'factura_contado',
            'set_cdc' => str_repeat('1', 44),
            'set_estado_envio' => 'autorizado',
            'set_fecha_autorizacion' => $autorizadaEn,
        ]);

        return $factura;
    }
}
