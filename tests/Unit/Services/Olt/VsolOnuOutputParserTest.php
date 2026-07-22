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

    public function test_parse_descripcion_con_status_online_delante(): void
    {
        $output = <<<'TXT'
OnuIndex Status Descriptions Model Profile Mode AuthInfo
GPON0/1:1 Online PEDRO_CIBILS AN5506-01-A default Sn FHTT10A02568
GPON0/1:2 Online NELSON_CIBILS AN5506-01-A default Sn FHTT10A025B8
GPON0/1:9 Online SABINA_LOPEZ_DE_ARAUJO EG8145V5 onu_profile_2 Sn HWTCD4A0A29E
TXT;

        $parser = new VsolOnuOutputParser();
        $onus = $parser->parse($output, '');

        $this->assertCount(3, $onus);
        $this->assertSame('PEDRO_CIBILS', $onus[0]['descripcion']);
        $this->assertSame('working', $onus[0]['estado']);
        $this->assertSame('NELSON_CIBILS', $onus[1]['descripcion']);
        $this->assertSame('SABINA_LOPEZ_DE_ARAUJO', $onus[2]['descripcion']);
        $this->assertSame('working', $onus[2]['estado']);
        $this->assertSame('EG8145V5', $onus[2]['modelo']);
    }

    public function test_parse_onu_desc_formato_vsol_desc_nombre(): void
    {
        $parser = new VsolOnuOutputParser();

        $this->assertSame('PEDRO_CIBILS', $parser->parseOnuDescOutput('desc PEDRO_CIBILS'));
        $this->assertSame('PEDRO_CIBILS', $parser->parseOnuDescOutput("show onu 1 desc\ndesc PEDRO_CIBILS"));
        $this->assertSame('PEDRO_CIBILS', $parser->parseOnuDescOutput('  desc PEDRO_CIBILS'));
        $this->assertSame('PEDRO_CIBILS', $parser->parseOnuDescOutput("show onu 1 desc\nPEDRO_CIBILS"));
    }

    public function test_parse_onu_desc_comando_primero_firmware(): void
    {
        $parser = new VsolOnuOutputParser();

        // Firmware: show onu desc 1 → "onu 1 Description: NOMBRE"
        $this->assertSame(
            'NELSON_RAMON_GAUTO_MARTINEZ',
            $parser->parseOnuDescOutput("show onu desc 1\nonu 1 Description: NELSON_RAMON_GAUTO_MARTINEZ")
        );
    }

    public function test_info_online_prevalece_sobre_phase_los(): void
    {
        $info = 'GPON0/1:9 Online SABINA_LOPEZ_DE_ARAUJO EG8145V5 onu_profile_2 Sn HWTCD4A0A29E';
        $state = "OnuIndex Admin OMCC OMT Phase state\nGPON0/1:9 enable enable enable LOS";

        $parser = new VsolOnuOutputParser();
        // Simula merge del sync: state primero, info después
        $fromState = $parser->parse('', $state);
        $fromInfo = $parser->parse($info, '');
        $merged = array_replace_recursive(
            [$fromState[0]['pon_key'].':'.$fromState[0]['onu_index'] => $fromState[0]],
            [$fromInfo[0]['pon_key'].':'.$fromInfo[0]['onu_index'] => array_merge($fromState[0], $fromInfo[0])]
        );
        $row = array_values($merged)[0];

        $this->assertSame('working', $row['estado']);
        $this->assertSame('SABINA_LOPEZ_DE_ARAUJO', $row['descripcion']);
    }

    public function test_parse_mac_address_lookup_formato_vsol(): void
    {
        $output = <<<'TXT'
VLAN: 199
MAC Address: fc1b:d1c2:8c15
Type: Dynamic
Port: GPON0/07
ONU ID: 7
TXT;

        $parser = new VsolOnuOutputParser;
        $hit = $parser->parseMacAddressLookup($output, 'FC:1B:D1:C2:8C:15');

        $this->assertNotNull($hit);
        $this->assertSame(199, $hit['vlan']);
        $this->assertSame('FC:1B:D1:C2:8C:15', $hit['mac']);
        $this->assertSame(7, $hit['pon_port']);
        $this->assertSame(7, $hit['onu_index']);
        $this->assertSame('Dynamic', $hit['type']);
    }

    public function test_parse_mac_address_lookup_con_puntos_en_etiquetas(): void
    {
        $output = <<<'TXT'
VLAN............: 199
MAC Address.....: fc1b:d1c2:8c15
Type............: Dynamic
Port............: GPON0/07
ONU ID..........: 7
TXT;

        $parser = new VsolOnuOutputParser;
        $hit = $parser->parseMacAddressLookup($output, 'FC:1B:D1:C2:8C:15');

        $this->assertNotNull($hit);
        $this->assertSame(7, $hit['pon_port']);
        $this->assertSame(7, $hit['onu_index']);
        $this->assertSame('FC:1B:D1:C2:8C:15', $hit['mac']);
    }

    public function test_parse_mac_address_lookup_espacios_alineados_vsol(): void
    {
        $output = "VLAN           : 199\nMAC Address    : fc1b:d1c2:8c15\nType           : Dynamic\nPort           : GPON0/07\nONU ID         : 7\n";

        $parser = new VsolOnuOutputParser;
        $hit = $parser->parseMacAddressLookup($output, 'FC:1B:D1:C2:8C:15');

        $this->assertNotNull($hit);
        $this->assertSame(199, $hit['vlan']);
        $this->assertSame(7, $hit['pon_port']);
        $this->assertSame(7, $hit['onu_index']);
    }

    public function test_parse_mac_address_lookup_dos_puntos_fullwidth(): void
    {
        $colon = "\u{FF1A}";
        $output = "VLAN           {$colon} 199\nMAC Address    {$colon} fc1b:d1c2:8c15\nType           {$colon} Dynamic\nPort           {$colon} GPON0/07\nONU ID         {$colon} 7\n";

        $parser = new VsolOnuOutputParser;
        $hit = $parser->parseMacAddressLookup($output, 'FC:1B:D1:C2:8C:15');

        $this->assertNotNull($hit);
        $this->assertSame(7, $hit['pon_port']);
        $this->assertSame(7, $hit['onu_index']);
    }

    public function test_parse_mac_address_lookup_sin_onu_id_solo_pon(): void
    {
        $output = <<<'TXT'
VLAN           : 199
MAC Address    : 48f8:dbd2:c791
Type           : Dynamic
Port           : GPON0/1
TXT;

        $parser = new VsolOnuOutputParser;
        $hit = $parser->parseMacAddressLookup($output, '48:F8:DB:D2:C7:91');

        $this->assertNotNull($hit);
        $this->assertSame(1, $hit['pon_port']);
        $this->assertNull($hit['onu_index']);
    }

    public function test_parse_mac_address_table_formato_gpon_slot_pon_onu(): void
    {
        $output = <<<'TXT'
Mac Address      Vlan        Type         GPON Slot/Pon:Onu       Gem_index         Gem_id
---------------------------------------------------------------------------------------------
28de.e52d.17de     199         Dynamic      GPON0/1:29            2                  202
084f.0a8a.1e1c     199         Dynamic      GPON0/1:16            2                  176
48f8.dbd2.c791     199         Dynamic      GPON0/1:31            2                  206
TXT;

        $parser = new VsolOnuOutputParser;
        $filas = $parser->parseMacAddressTable($output);
        $hit = $parser->buscarMacEnTabla($filas, '48:F8:DB:D2:C7:91');

        $this->assertNotNull($hit);
        $this->assertSame(1, $hit['pon_port']);
        $this->assertSame(31, $hit['onu_index']);
        $this->assertSame(199, $hit['vlan']);
    }

    public function test_parse_mac_address_table_formato_con_info_serial(): void
    {
        $output = <<<'TXT'
Mac Address      Vlan        Type         GPON0/Pon:Onu       Gem_index           Gem_id         Info
-----------------------------------------------------------------------------------------------------------
Addresses of all pons Found: 348
The pon Found: 36

fc1b.d1cc.1632     199         Dynamic      GPON0/1:39            2                  222        HWTCa29ef6a3
fc1b.d1cc.9b13     199         Dynamic      GPON0/1:18            2                  180        HWTCa2a6c7a3
5825.7536.41a0     199         Dynamic      GPON0/1:3             2                  150        HWTCa9307ca3
TXT;

        $parser = new VsolOnuOutputParser;
        $filas = $parser->parseMacAddressTable($output);
        $hit = $parser->buscarMacEnTabla($filas, 'FC:1B:D1:CC:16:32');

        $this->assertCount(3, $filas);
        $this->assertNotNull($hit);
        $this->assertSame(1, $hit['pon_port']);
        $this->assertSame(39, $hit['onu_index']);
        $this->assertSame(199, $hit['vlan']);
    }

    public function test_parse_mac_address_table_telnet_multilinea(): void
    {
        $output = <<<'TXT'
Mac Address Table
Mac Address      Vlan        Type         GPON Slot/Pon:Onu       Gem_index         Gem_id
---------------------------------------------------------------------------------------------
28de.e52d.17de
199
Dynamic
GPON0/1:29
2
202
fc1b.d1cc.35f0
199
Dynamic
GPON0/1:2
2
148
fc1b.d1c2.6c46
199
Dynamic
GPON0/1:4
2
150
TXT;

        $parser = new VsolOnuOutputParser;
        $filas = $parser->parseMacAddressTable($output);
        $hit = $parser->buscarMacEnTabla($filas, 'FC:1B:D1:CC:35:F0');

        $this->assertCount(3, $filas);
        $this->assertNotNull($hit);
        $this->assertSame(1, $hit['pon_port']);
        $this->assertSame(2, $hit['onu_index']);
        $this->assertSame(199, $hit['vlan']);
    }

    public function test_parse_mac_address_table_no_usa_solo_linea_mac_address(): void
    {
        $output = <<<'TXT'
VLAN: 199
MAC Address: fc1b:d1c2:8c15
Type: Dynamic
TXT;

        $parser = new VsolOnuOutputParser;
        $filas = $parser->parseMacAddressTable($output);
        $hit = $parser->buscarMacEnTabla($filas, 'FC:1B:D1:C2:8C:15');

        $this->assertNull($hit);
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
