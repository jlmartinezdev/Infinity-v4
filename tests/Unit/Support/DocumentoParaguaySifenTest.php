<?php

namespace Tests\Unit\Support;

use App\Support\DocumentoParaguaySifen;
use Tests\TestCase;

class DocumentoParaguaySifenTest extends TestCase
{
    public function test_acepta_cedula_de_siete_digitos(): void
    {
        $this->assertTrue(DocumentoParaguaySifen::esValido('1234567'));
        $this->assertTrue(DocumentoParaguaySifen::esValido(' 1234567 '));
    }

    public function test_acepta_cedula_o_ruc_con_dv(): void
    {
        $this->assertTrue(DocumentoParaguaySifen::esValido('1234567-8'));
        $this->assertTrue(DocumentoParaguaySifen::esValido('80030552-0'));
    }

    public function test_rechaza_puntos_espacios_y_otros_caracteres(): void
    {
        $this->assertFalse(DocumentoParaguaySifen::esValido('1.234.567'));
        $this->assertFalse(DocumentoParaguaySifen::esValido('1234567 8'));
        $this->assertFalse(DocumentoParaguaySifen::esValido('1234567A'));
        $this->assertFalse(DocumentoParaguaySifen::esValido('12345678'));
        $this->assertFalse(DocumentoParaguaySifen::esValido('123456'));
        $this->assertFalse(DocumentoParaguaySifen::esValido('800305520'));
        $this->assertFalse(DocumentoParaguaySifen::esValido(''));
        $this->assertFalse(DocumentoParaguaySifen::esValido(null));
    }

    public function test_mensaje_pide_formato_y_bloquea_envio(): void
    {
        $r = DocumentoParaguaySifen::evaluar('1.234.567');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('SIFEN', $r['message']);
        $this->assertStringContainsString('XXXXXXX-X', $r['message']);
        $this->assertStringContainsString('1.234.567', $r['message']);
    }
}
