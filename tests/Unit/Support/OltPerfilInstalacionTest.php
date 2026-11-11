<?php

namespace Tests\Unit\Support;

use App\Support\OltPerfilInstalacion;
use Tests\TestCase;

class OltPerfilInstalacionTest extends TestCase
{
    public function test_normaliza_la_serie(): void
    {
        $this->assertSame('FHTT10A0F220', OltPerfilInstalacion::normalizarSerial(' fhtt-10a0 f220 '));
    }

    public function test_encuentra_la_serie_en_show_onu_info(): void
    {
        $salida = <<<'TXT'
GPON0/2:24
EG8145V5
onu_profile_2
sn
HWTC644A249E
GPON0/2:25
unknown
onu_profile_2
sn
FHTT10A0F220
GPON0/3:1
EG8145V5
onu_profile_2
sn
HWTCD46D4A9E
TXT;

        $hit = OltPerfilInstalacion::buscarEnSalida($salida, 'fhtt10a0f220');

        $this->assertNotNull($hit);
        $this->assertSame(2, $hit['pon_port']);
        $this->assertSame(25, $hit['onu_index']);
        $this->assertSame('FHTT10A0F220', $hit['serial']);
    }

    public function test_arma_los_comandos_de_cada_instalacion(): void
    {
        $ont = OltPerfilInstalacion::perfil('ont');
        $puente = OltPerfilInstalacion::perfil('onu_router');

        $this->assertNotNull($ont);
        $this->assertNotNull($puente);
        $this->assertSame([
            'interface gpon 0/2',
            'onu 25 profile line name line_PPPOE+HOSPOT',
            'onu 25 profile srv name srv_1',
        ], OltPerfilInstalacion::comandos(2, 25, $ont));
        $this->assertSame([
            'interface gpon 0/2',
            'onu 25 profile line name line_puente',
            'onu 25 profile srv name srv_puente',
        ], OltPerfilInstalacion::comandos(2, 25, $puente));
    }
}
