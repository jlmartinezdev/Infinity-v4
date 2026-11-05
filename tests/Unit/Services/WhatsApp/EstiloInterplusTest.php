<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\Soporte\EstiloInterplus;
use PHPUnit\Framework\TestCase;

class EstiloInterplusTest extends TestCase
{
    public function test_rechaza_ueno_cuenta_y_voseo(): void
    {
        $this->assertTrue(EstiloInterplus::textoProhibido('Entidad: ueno bank 619556130'));
        $this->assertTrue(EstiloInterplus::textoProhibido('N° de cuenta: 18451024'));
        $this->assertTrue(EstiloInterplus::textoProhibido('me pases una captura'));
        $this->assertTrue(EstiloInterplus::textoProhibido('Puedes facilitarnos el número de C.I'));
    }

    public function test_el_estilo_limpio_no_esta_prohibido(): void
    {
        $estilo = new EstiloInterplus;
        $this->assertFalse(EstiloInterplus::textoProhibido($estilo->default()));
        $this->assertFalse(EstiloInterplus::textoProhibido('Muy buenos días, Sr. Leiva. Quedamos a las órdenes.'));
        $this->assertFalse(EstiloInterplus::textoProhibido('Puede transferir a estas cuentas.'));
    }

    public function test_sanear_descarta_solo_las_lineas_malas(): void
    {
        $limpio = EstiloInterplus::sanear("Muy buenos días.\nEntidad: ueno bank\nQuedamos a las órdenes.");

        $this->assertStringContainsString('Muy buenos días.', $limpio);
        $this->assertStringContainsString('Quedamos a las órdenes.', $limpio);
        $this->assertStringNotContainsString('Entidad', $limpio);
    }
}
