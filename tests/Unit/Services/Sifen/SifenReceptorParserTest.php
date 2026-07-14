<?php

namespace Tests\Unit\Services\Sifen;

use App\Services\Sifen\SifenReceptorParser;
use PHPUnit\Framework\TestCase;

class SifenReceptorParserTest extends TestCase
{
    private SifenReceptorParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SifenReceptorParser;
    }

    public function test_ruc_generico_9999999_9_es_b2c(): void
    {
        $r = $this->parser->parseDesdeDocumento('9999999-9', 'Consumidor Final');

        $this->assertSame(2, $r['iNatRec']);
        $this->assertSame(2, $r['iTiOpe']);
        $this->assertNull($r['dRucRec']);
        $this->assertSame('9999999', $r['dNumIDRec']);
        $this->assertSame(1, $r['iTipIDRec']);
    }

    public function test_ruc_real_es_b2b(): void
    {
        $r = $this->parser->parseDesdeDocumento('80030552-0', 'Empresa SA');

        $this->assertSame(1, $r['iNatRec']);
        $this->assertSame(1, $r['iTiOpe']);
        $this->assertSame('80030552', $r['dRucRec']);
        $this->assertSame(0, $r['dDVRec']);
        $this->assertNull($r['dNumIDRec']);
    }

    public function test_cedula_es_b2c(): void
    {
        $r = $this->parser->parseDesdeDocumento('1234567', 'Juan Perez');

        $this->assertSame(2, $r['iNatRec']);
        $this->assertSame(2, $r['iTiOpe']);
        $this->assertSame('1234567', $r['dNumIDRec']);
    }
}
