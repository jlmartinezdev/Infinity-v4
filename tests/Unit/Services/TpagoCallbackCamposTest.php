<?php

namespace Tests\Unit\Services;

use App\Services\Tpago\TpagoCallbackService;
use Tests\TestCase;

class TpagoCallbackCamposTest extends TestCase
{
    public function test_lee_campos_desde_payment_de_tpago(): void
    {
        $c = TpagoCallbackService::camposDesdePayload([
            'payment' => [
                'hook_alias' => 'PUYAM31792',
                'status' => 'confirmed',
                'response_code' => '00',
                'amount' => 6000,
                'ticket_number' => '5723209417',
                'authorization_code' => 'ABC123',
            ],
        ]);

        $this->assertSame('PUYAM31792', $c['alias']);
        $this->assertSame('00', $c['response_code']);
        $this->assertSame(6000, $c['amount']);
        $this->assertSame('5723209417', $c['ticket_number']);
        $this->assertSame('ABC123', $c['authorization_code']);
        $this->assertSame('confirmed', $c['status']);
    }

    public function test_lee_campos_planos(): void
    {
        $c = TpagoCallbackService::camposDesdePayload([
            'link_alias' => 'XXXX',
            'response_code' => '00',
            'amount' => 1000,
            'ticket_number' => '1',
            'status' => 'Paid',
        ]);

        $this->assertSame('XXXX', $c['alias']);
        $this->assertSame('paid', $c['status']);
    }
}
