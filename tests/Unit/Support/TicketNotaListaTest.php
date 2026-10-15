<?php

namespace Tests\Unit\Support;

use App\Support\TicketNotaLista;
use PHPUnit\Framework\TestCase;

class TicketNotaListaTest extends TestCase
{
    public function test_deja_la_queja_cuando_no_hay_secreto(): void
    {
        $this->assertSame('Sin internet en la NAP', TicketNotaLista::paraLista('Sin internet en la NAP'));
        $this->assertSame('', TicketNotaLista::paraLista('   '));
    }

    public function test_muestra_la_contraseña_en_la_lista(): void
    {
        $this->assertSame('Nueva contraseña: Secreto123', TicketNotaLista::paraLista('Nueva contraseña: Secreto123'));
        $this->assertSame('Password: otro-valor', TicketNotaLista::paraLista('Password: otro-valor'));
        $this->assertSame(
            'Sin señal. Nueva contraseña: Secreto123',
            TicketNotaLista::paraLista("Sin señal.\nNueva contraseña: Secreto123")
        );
    }

    public function test_el_detalle_conserva_la_contraseña(): void
    {
        $detalle = TicketNotaLista::paraDetalle('Nueva contraseña: Secreto123');

        $this->assertSame('Nueva contraseña: Secreto123', $detalle);
    }

    public function test_una_frase_sin_valor_no_se_trata_como_secreto(): void
    {
        $frase = 'Olvidó la contraseña del router';

        $this->assertSame($frase, TicketNotaLista::paraLista($frase));
        $this->assertSame($frase, TicketNotaLista::paraDetalle($frase));
    }
}
