<?php

namespace Tests\Unit\Services;

use App\Services\TicketEnCaminoVencidoService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class TicketEnCaminoVencidoServiceTest extends TestCase
{
    public function test_la_nota_explica_la_vuelta_a_pendiente(): void
    {
        $texto = TicketEnCaminoVencidoService::textoNota(
            Carbon::parse('2026-10-08 15:30:00', 'America/Argentina/Buenos_Aires')
        );

        $this->assertSame(
            'Volvió a pendiente el 08/10/2026 15:30 porque llevaba más de un día en camino.',
            $texto
        );
    }
}
