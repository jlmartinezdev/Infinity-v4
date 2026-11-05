<?php

namespace Tests\Unit\Services;

use App\Services\Tpago\TpagoPagoVerificador;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TpagoPagoVerificadorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tpago.public_key' => 'apps/public',
            'tpago.private_key' => 'private',
            'tpago.commerce_code' => '1',
            'tpago.branch_code' => '1',
            'tpago.base_url' => 'https://tpago.test',
        ]);
    }

    public function test_no_confirma_si_la_api_no_tiene_pago(): void
    {
        Http::fake([
            'https://tpago.test/*' => Http::response([
                'status' => 'success',
                'amount' => 5000,
                'payments' => [],
            ], 200),
        ]);

        $resultado = app(TpagoPagoVerificador::class)->consultar('PSHLA13790', 5000);

        $this->assertSame('no_pago', $resultado['estado']);
        $this->assertSame(0, $resultado['monto']);
    }

    public function test_confirma_solo_si_el_monto_coincide(): void
    {
        Http::fake([
            'https://tpago.test/*' => Http::response([
                'status' => 'success',
                'payments' => [[
                    'amount' => '5000.0',
                    'response_code' => '00',
                    'status' => 'confirmed',
                    'ticket_number' => '5723103463',
                    'authorization_code' => 'ABC',
                ]],
            ], 200),
        ]);

        $verificador = app(TpagoPagoVerificador::class);

        $ok = $verificador->consultar('PSHLA13790', 5000);
        $this->assertSame('confirmado', $ok['estado']);
        $this->assertSame(5000, $ok['monto']);
        $this->assertSame('5723103463', $ok['ticket']);

        $distinto = $verificador->consultar('PSHLA13790', 15000);
        $this->assertSame('no_pago', $distinto['estado']);
    }

    public function test_api_caida_queda_indisponible(): void
    {
        Http::fake([
            'https://tpago.test/*' => Http::response('error', 503),
        ]);

        $resultado = app(TpagoPagoVerificador::class)->consultar('PSHLA13790', 5000);

        $this->assertSame('indisponible', $resultado['estado']);
    }
}
