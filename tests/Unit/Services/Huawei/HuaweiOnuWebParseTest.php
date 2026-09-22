<?php

namespace Tests\Unit\Services\Huawei;

use App\Services\Huawei\HuaweiOnuWeb;
use PHPUnit\Framework\TestCase;

class HuaweiOnuWebParseTest extends TestCase
{
    public function test_decode_js_hex(): void
    {
        $this->assertSame(' 2.30', HuaweiOnuWeb::decodeJs('\x202\x2e30'));
        $this->assertSame('-15.56', HuaweiOnuWeb::decodeJs('\x2d15\x2e56'));
        $this->assertSame('3A:8B:33:82:AB:21', HuaweiOnuWeb::decodeJs('3A\x3a8B\x3a33\x3a82\x3aAB\x3a21'));
    }

    public function test_parse_optic_html_eg8145(): void
    {
        $html = 'var opticInfos = new Array(new stOpticInfo("InternetGatewayDevice.X_HW_DEBUG.AMP.Optic","\x202\x2e30","\x2d15\x2e56","3330","40","9","\x2d\x2d","\x2d\x2d"),null);';
        $optic = HuaweiOnuWeb::parseOpticHtml($html);

        $this->assertSame(2.3, $optic['tx_power_dbm']);
        $this->assertSame(-15.56, $optic['rx_power_dbm']);
        $this->assertSame(40.0, $optic['temperatura_c']);
    }

    public function test_parse_ssids_html(): void
    {
        $html = 'var WlanWifiArr = new Array(new stWlanWifi("InternetGatewayDevice\x2eLANDevice\x2e1\x2eWLANConfiguration\x2e1","ath0","1","Fliatorres\x20","11bgn","0","100","CN","1","0"),new stWlanWifi("InternetGatewayDevice\x2eLANDevice\x2e1\x2eWLANConfiguration\x2e5","ath8","1","Fliatorres\x205G","11ac","0","100","CN","1","3"),null);';
        $ssids = HuaweiOnuWeb::parseSsidsHtml($html);

        $this->assertSame(['Fliatorres', 'Fliatorres 5G'], $ssids);
    }

    public function test_parse_asociados_html(): void
    {
        $raw = 'new Array(new stAssociatedDevice("InternetGatewayDevice\x2eLANDevice\x2e1\x2eWLANConfiguration\x2e1\x2eAssociatedDevice\x2e1","3A\x3a8B\x3a33\x3a82\x3aAB\x3a21","19521","24","144"),null);';
        $rows = HuaweiOnuWeb::parseAsociadosHtml($raw, [1 => 'Fliatorres']);

        $this->assertCount(1, $rows);
        $this->assertSame('3A:8B:33:82:AB:21', $rows[0]['mac']);
        $this->assertSame('Fliatorres', $rows[0]['ssid']);
        $this->assertSame('19521', $rows[0]['tiempo']);
    }

    public function test_parse_wan_ppp_ipv6_flags(): void
    {
        $html = 'var PPPWanList = new Array(new WanPPP("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1","AlwaysOn","00\x3a11\x3a41\x3a1F\x3a59\x3a55","Connected","ERROR\x5fNONE","","1\x5fINTERNET\x5fR\x5fVID\x5f199","1","","","Connected","IP\x5fRouted","10\x2e7\x2e8\x2e46","172\x2e255\x2e255\x2e15","1","0","1\x2e1\x2e1\x2e1","user","hash","AlwaysOn","4294967295","199","4294967295","0","0","INTERNET","","0","180","1","1","0","-1","Specified","0","1492","","0","100","0","","1","1","","0","",""),null);';
        $wans = HuaweiOnuWeb::parseWanPppHtml($html);

        $this->assertCount(1, $wans);
        $this->assertSame('INTERNET', $wans[0]['service']);
        $this->assertTrue($wans[0]['ipv4']);
        $this->assertFalse($wans[0]['ipv6']);
        $this->assertSame($wans[0], HuaweiOnuWeb::wanInternet($wans));
    }

    public function test_parse_prefix_acquire_vacio_queda_none(): void
    {
        $html = 'var PrefixAcquirePPP = new Array(null); var IPAddressAcquirePPP = new Array(null);';
        $this->assertSame([], HuaweiOnuWeb::parsePrefixAcquire($html));
        $this->assertSame([], HuaweiOnuWeb::parseAddressAcquire($html));

        $wan = [
            'domain' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1',
            'name' => 'wan1',
            'service' => 'INTERNET',
            'ipv4' => true,
            'ipv6' => true,
        ];
        $cruzado = HuaweiOnuWeb::cruzarWansConAcquire([$wan], $html)[0];
        $this->assertSame('None', $cruzado['prefix_origin']);
        $this->assertSame('None', $cruzado['address_origin']);
        $this->assertNull($cruzado['prefix_domain']);
        $this->assertFalse(HuaweiOnuWeb::ipv6ConPd($cruzado));
    }

    public function test_parse_prefix_acquire_dhcpv6_pd(): void
    {
        $html = 'var PrefixAcquirePPP = new Array(new PrefixAcquireItem("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_HW_IPv6.IPv6Prefix.1","","PrefixDelegation",""),null);'
            .'var IPAddressAcquirePPP = new Array(new IPAddressAcquirePPPItem("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_HW_IPv6.IPv6Address.1","","AutoConfigured","","","0",""),null);';
        $prefixes = HuaweiOnuWeb::parsePrefixAcquire($html);
        $addrs = HuaweiOnuWeb::parseAddressAcquire($html);

        $this->assertSame('PrefixDelegation', $prefixes[0]['origin']);
        $this->assertSame('AutoConfigured', $addrs[0]['origin']);

        $wan = [
            'domain' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1',
            'ipv6' => true,
        ];
        $cruzado = HuaweiOnuWeb::cruzarWansConAcquire([$wan], $html)[0];
        $this->assertTrue(HuaweiOnuWeb::ipv6ConPd($cruzado));
        $this->assertSame('PrefixDelegation', $cruzado['prefix_origin']);
        $this->assertSame('AutoConfigured', $cruzado['address_origin']);
        $this->assertStringContainsString('IPv6Prefix.1', (string) $cruzado['prefix_domain']);
    }

    public function test_payload_ipv6_crea_instancias_con_add(): void
    {
        $wan = [
            'domain' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1',
            'prefix_domain' => null,
            'address_domain' => null,
        ];
        $payload = HuaweiOnuWeb::payloadIpv6($wan, 'tok');

        $this->assertStringContainsString('Add_n=', $payload['path']);
        $this->assertStringContainsString('Add_m=', $payload['path']);
        $this->assertSame('PrefixDelegation', $payload['fields']['Add_n.Origin']);
        $this->assertSame('AutoConfigured', $payload['fields']['Add_m.Origin']);
        $this->assertSame('1', $payload['fields']['y.X_HW_IPv6Enable']);
        $this->assertSame('tok', $payload['fields']['x.X_HW_Token']);
    }

    public function test_payload_ipv6_edita_instancias_existentes(): void
    {
        $wan = [
            'domain' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1',
            'prefix_domain' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_HW_IPv6.IPv6Prefix.1',
            'address_domain' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_HW_IPv6.IPv6Address.1',
        ];
        $payload = HuaweiOnuWeb::payloadIpv6($wan, 'tok');

        $this->assertStringContainsString('n=', $payload['path']);
        $this->assertStringNotContainsString('Add_n=', $payload['path']);
        $this->assertSame('PrefixDelegation', $payload['fields']['n.Origin']);
        $this->assertSame('AutoConfigured', $payload['fields']['m.Origin']);
    }

    public function test_html_es_huawei(): void
    {
        $this->assertTrue(HuaweiOnuWeb::htmlEsHuawei("var ProductName = 'EG8145V5';"));
        $this->assertFalse(HuaweiOnuWeb::htmlEsHuawei('<html><title>Router</title></html>'));
    }
}
