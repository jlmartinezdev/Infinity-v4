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
        $this->assertTrue(EstiloInterplus::textoProhibido('Tienes algún horario de preferencia'));
        $this->assertTrue(EstiloInterplus::textoProhibido('Estás de acuerdo perder estos beneficios'));
        $this->assertTrue(EstiloInterplus::textoProhibido('También podés hacerlo por transferencia'));
        $this->assertTrue(EstiloInterplus::textoProhibido('Avísanos si le funciona'));
    }

    public function test_reescribe_el_voseo_util_y_apaga_el_saludo(): void
    {
        $this->assertSame(
            '¿Nos facilita el número de cédula del titular?',
            EstiloInterplus::reescribirCaso('Puedes facilitarnos el número de C.I del titular del servicio!')
        );
        $this->assertNull(EstiloInterplus::reescribirCaso('Muy buenos días!'));
        $this->assertNull(EstiloInterplus::reescribirCaso('Entidad: ueno bank N° de cuenta: 18451024'));
        $this->assertSame(
            'En cuanto el técnico verifique su ubicación nos volvemos a comunicar con usted.',
            EstiloInterplus::reescribirCaso('En cuando el técnico verifique su ubicación nos volvemos a comunicar con usted.')
        );
        $titulo = EstiloInterplus::tituloCorto('El inconveniente es en general en la zona, no sólo en su domicilio, pero los técnicos ya se dirigen al lugar');
        $this->assertLessThanOrEqual(42, mb_strlen($titulo));
        $this->assertStringEndsNotWith('dom', $titulo);
        $this->assertSame('En breve le confirmamos', EstiloInterplus::tituloCorto('En breve le confirmamos!'));
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
