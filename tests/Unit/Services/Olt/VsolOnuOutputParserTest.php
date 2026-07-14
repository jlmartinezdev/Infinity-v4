<?php

namespace Tests\Unit\Services\Olt;

use App\Services\Olt\VsolOnuOutputParser;
use PHPUnit\Framework\TestCase;

class VsolOnuOutputParserTest extends TestCase
{
    /**
     * @param  array<int, array<string, mixed>>  ...$grupos
     * @return array<int, array<string, mixed>>
     */
    private function mergeFilasOnu(array ...$grupos): array
    {
        $merged = [];

        foreach ($grupos as $grupo) {
            foreach ($grupo as $row) {
                $key = ($row['pon_key'] ?? '').':'.($row['onu_index'] ?? '');
                if ($key === ':') {
                    continue;
                }

                $existente = $merged[$key] ?? [];
                foreach ($row as $campo => $valor) {
                    if ($valor === null || $valor === '') {
                        continue;
                    }
                    if ($campo === 'estado' && $valor === 'unknown' && ($existente['estado'] ?? 'unknown') !== 'unknown') {
                        continue;
                    }
                    $existente[$campo] = $valor;
                }
                $merged[$key] = $existente;
            }
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    private function filtrarRegistradas(VsolOnuOutputParser $parser, array $filas): array
    {
        return array_values(array_filter($filas, fn (array $row) => $parser->filaEsOnuRegistrada($row)));
    }

    public function test_parse_tabla_show_onu_state_por_pon(): void
    {
        $output = <<<'TXT'
PON port 0/1
OnuId    State
1        working
2        offline
3        los

PON port 0/2
OnuId    State
1        working
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertCount(4, $onus);
        $this->assertCount(0, $this->filtrarRegistradas($parser, $onus));
    }

    public function test_parse_tabla_show_onu_state_con_info(): void
    {
        $info = <<<'TXT'
PON port 0/1
GPON0/1:1 AN5506-01-A default sn FHTT10A02568
GPON0/1:2 AN5506-01-A default sn FHTT20B02569
TXT;
        $state = <<<'TXT'
PON port 0/1
1/1/1:1 enable enable working 1(GPON)
1/1/1:2 enable disable offline 1(GPON)
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $this->filtrarRegistradas($parser, $this->mergeFilasOnu(
            $parser->parse($info, ''),
            $parser->parse('', $state),
        ));

        $this->assertCount(2, $onus);
        $this->assertSame('working', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1)['estado']);
        $this->assertSame('offline', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 2)['estado']);
    }

    public function test_parse_lineas_gpon_con_serial(): void
    {
        $output = <<<'TXT'
GPON0/1:1   GPON0045321A   enable   enable   working
GPON0/1:2   HWTC12345678   enable   disable  offline
GPON0/2:5   VSOLABCDEF01   enable   enable   working
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertCount(3, $onus);
        $this->assertSame('GPON0045321A', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1)['serial']);
        $this->assertSame('working', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1)['estado']);
        $this->assertSame('0/2', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 5)['pon_key']);
    }

    public function test_parse_estado_online_en_tabla(): void
    {
        $output = <<<'TXT'
PON port 0/1
OnuId    PhaseState
1        online
2        offline
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse('', $output);

        $this->assertCount(2, $onus);
        $this->assertCount(0, $this->filtrarRegistradas($parser, $onus));
    }

    public function test_parse_estado_online_en_tabla_con_info(): void
    {
        $info = <<<'TXT'
PON port 0/1
GPON0/1:1 EG8145V5 default sn HWTC11111111
GPON0/1:2 EG8145V5 default sn HWTC22222222
TXT;
        $output = <<<'TXT'
PON port 0/1
OnuId    PhaseState
1        online
2        offline
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $this->filtrarRegistradas($parser, $this->mergeFilasOnu(
            $parser->parse($info, ''),
            $parser->parse('', $output),
        ));

        $this->assertSame('working', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1)['estado']);
        $this->assertSame('offline', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 2)['estado']);
    }

    public function test_parse_formato_vsol_manual_solo_estado_no_crea_onus(): void
    {
        $output = <<<'TXT'
PON 0/1
OnuIndex Admin State OMCC State Phase State Config State Channel
-----------------------------------------------------------------
1/1/1:1 enable enable working succeeded 1(GPON)
1/1/1:2 enable disable offline succeeded 1(GPON)
1/1/1:3 enable enable los succeeded 1(GPON)
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse('', $output);

        $this->assertCount(3, $onus);
        $this->assertCount(0, $this->filtrarRegistradas($parser, $onus));
    }

    public function test_parse_formato_vsol_manual_con_info(): void
    {
        $info = <<<'TXT'
PON 0/1
GPON0/1:1 AN5506-01-A default sn FHTT10A02568
GPON0/1:2 AN5506-01-A default sn FHTT20B02569
GPON0/1:3 AN5506-01-A default sn FHTT30B02570
TXT;

        $output = <<<'TXT'
PON 0/1
OnuIndex Admin State OMCC State Phase State Config State Channel
-----------------------------------------------------------------
1/1/1:1 enable enable working succeeded 1(GPON)
1/1/1:2 enable disable offline succeeded 1(GPON)
1/1/1:3 enable enable los succeeded 1(GPON)
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $this->filtrarRegistradas($parser, $this->mergeFilasOnu(
            $parser->parse($info, ''),
            $parser->parse('', $output),
        ));

        $this->assertCount(3, $onus);

        $onu1 = collect($onus)->firstWhere(fn ($o) => $o['pon_key'] === '0/1' && $o['onu_index'] === 1);
        $this->assertSame('working', $onu1['estado']);

        $onu2 = collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 2);
        $this->assertSame('offline', $onu2['estado']);

        $onu3 = collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 3);
        $this->assertSame('los', $onu3['estado']);
    }

    public function test_parse_show_onu_info_huawei_una_sola_linea(): void
    {
        $output = 'show onu info Onuindex Model Profile Mode AuthInfo --------------------------------------------------------------------------------------------------------------------------- GPON0/1:1 EG8145V5 default sn HWTCa2a64da3 GPON0/1:2 EG8145V5 default sn HWTC2699679f GPON0/1:3 HS8346V5 default sn HWTCa9307ca3 GPON0/1:4 EG8145V5 default sn HWTCa20f19a3 GPON0/1:5 EG8145V5 default';

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertGreaterThanOrEqual(4, count($onus));
        $this->assertSame('HWTCA2A64DA3', collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1)['serial']);
    }

    public function test_parse_show_onu_info_huawei_concatenado(): void
    {
        $output = <<<'TXT'
show onu info Onuindex Model Profile Mode AuthInfo
---------------------------------------------------------------------------------------------------------------------------
GPON0/1:1 EG8145V5 default sn HWTCa2a64da3 GPON0/1:2 EG8145V5 default sn HWTC2699679f GPON0/1:3 HS8346V5 default sn HWTCa9307ca3
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertCount(3, $onus);

        $onu1 = collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1);
        $this->assertSame('0/1', $onu1['pon_key']);
        $this->assertSame('HWTCA2A64DA3', $onu1['serial']);
        $this->assertSame('EG8145V5', $onu1['modelo']);

        $onu3 = collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 3);
        $this->assertSame('HS8346V5', $onu3['modelo']);
        $this->assertSame('HWTCA9307CA3', $onu3['serial']);
    }

    public function test_parse_show_onu_state_vsol_una_linea_con_pon(): void
    {
        $info = 'configure terminal PON 0/1 show onu info GPON0/1:1 EG8145V5 default sn HWTCa2a64da3 GPON0/1:2 EG8145V5 default sn HWTC2699679f GPON0/1:3 HS8346V5 default sn HWTCa9307ca3 GPON0/1:4 EG8145V5 default sn HWTCa20f19a3 GPON0/1:5 EG8145V5 default sn HWTCa2a64da4 GPON0/1:6 EG8145V5 default sn HWTCa2a64da5';
        $output = 'configure terminal PON 0/1 show onu state OnuIndex Admin State OMCC State Phase State Channel --------------------------------------------------------------- 1/1/1:1 enable enable working 1(GPON) 1/1/1:2 enable enable working 1(GPON) 1/1/1:3 enable enable working 1(GPON) 1/1/1:4 enable enable working 1(GPON) 1/1/1:5 enable enable working 1(GPON) 1/1/1:6 enable enable working 1(GPON)';

        $parser = new VsolOnuOutputParser();
        $onus = $this->filtrarRegistradas($parser, $this->mergeFilasOnu(
            $parser->parse($info, ''),
            $parser->parse('', $output),
        ));

        $this->assertGreaterThanOrEqual(6, count($onus));

        $onu1 = collect($onus)->firstWhere(fn ($o) => $o['pon_key'] === '0/1' && $o['onu_index'] === 1);
        $this->assertNotNull($onu1);
        $this->assertSame('working', $onu1['estado']);

        $onu6 = collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 6);
        $this->assertSame('working', $onu6['estado']);
    }

    public function test_parse_opm_diag_con_rx(): void
    {
        $output = <<<'TXT'
PON 0/1
GPON0/1:1  -23.45  2.31
GPON0/1:2  -19.80  2.10
1/1/1:3 Rx:-21.5 Tx:2.0
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parseOptical($output);

        $this->assertCount(3, $onus);
        $this->assertSame(-23.45, collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 1)['rx_power_dbm']);
        $this->assertSame(-19.80, collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 2)['rx_power_dbm']);
        $this->assertSame(-21.5, collect($onus)->firstWhere(fn ($o) => $o['onu_index'] === 3)['rx_power_dbm']);
    }

    public function test_parse_onu_desc_y_optical_info(): void
    {
        $parser = new VsolOnuOutputParser();

        $desc = $parser->parseOnuDescOutput("show onu 2 desc\nDescription: Cliente Juan Pérez\n");
        $this->assertSame('Cliente Juan Pérez', $desc);

        $optical = $parser->parseOnuOpticalInfoOutput(<<<'TXT'
Rx optical power(dBm)  : -22.35
Tx optical power(dBm)  : 2.18
TXT);
        $this->assertSame(-22.35, $optical['rx_power_dbm']);
        $this->assertSame(2.18, $optical['tx_power_dbm']);

        $this->assertNull($parser->parseOnuOpticalInfoOutput("show onu 1 optical_info\nONU index: 1\n")['rx_power_dbm'] ?? null);
        $this->assertNull($parser->parseOnuOpticalInfoOutput("show onu 10 optical_info\n")['rx_power_dbm'] ?? null);
    }

    public function test_parse_descripcion_desde_show_onu_info(): void
    {
        $output = 'GPON0/1:1 PEDRO_CIBILS AN5506-01-A default sn FHTT10A02568';

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertCount(1, $onus);
        $this->assertSame('PEDRO_CIBILS', $onus[0]['descripcion']);
        $this->assertSame('AN5506-01-A', $onus[0]['modelo']);
        $this->assertSame('FHTT10A02568', $onus[0]['serial']);
    }

    public function test_no_crea_onus_sin_pon_ni_estado(): void
    {
        $output = <<<'TXT'
1 enable
2 enable
3 enable
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertCount(0, $onus);
    }
}
