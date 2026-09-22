<?php

namespace Tests\Unit\Services\Huawei;

use App\Services\Huawei\HuaweiOnuService;
use PHPUnit\Framework\TestCase;

class HuaweiOnuServiceParseTest extends TestCase
{
    public function test_parse_wan_dual_stack(): void
    {
        $raw = <<<'TXT'
Index    Name      Enable    Service Type             Protocol Type    IP Acquisition Mode
1        wan2      Enable    INTERNET                 IPv4&IPv6        PPPoE(IPv4)/(IPv6)
Total: 1
TXT;
        $wan = HuaweiOnuService::parseWanPrincipal($raw);

        $this->assertSame('wan2', $wan['name']);
        $this->assertSame('IPv4&IPv6', $wan['protocol']);
        $this->assertTrue($wan['dual']);
    }

    public function test_parse_wan_solo_ipv4(): void
    {
        $raw = <<<'TXT'
1        wan2      Enable    INTERNET                 IPv4             PPPoE
TXT;
        $wan = HuaweiOnuService::parseWanPrincipal($raw);

        $this->assertSame('wan2', $wan['name']);
        $this->assertFalse($wan['dual']);
    }

    public function test_parse_wifi_associate_formato_eg8145_5ghz(): void
    {
        $raw = <<<'TXT'
Mac               SSID                             Time   TxRate  RxRate  Mode  Antenas  PowerSave DualBand  BF  11K 11V 
############################################################## 2.4GHz ##############################################################
Number of associated STAs on 2.4GHz band: 0

##############################################################  5GHz  ##############################################################
Number of associated STAs on 5GHz band: 1

8A:58:F7:C9:92:17 Test_5G                          421    234M    6M      11ac  2*2      on        1         1   1   1   
success!
TXT;
        $rows = HuaweiOnuService::parseWifiAssociate($raw);

        $this->assertCount(1, $rows);
        $this->assertSame('8A:58:F7:C9:92:17', $rows[0]['mac']);
        $this->assertSame('Test_5G', $rows[0]['ssid']);
        $this->assertSame('421', $rows[0]['tiempo']);
        $this->assertSame('234M', $rows[0]['tx']);
        $this->assertSame('6M', $rows[0]['rx']);
    }

    public function test_parse_wifi_associate_con_ssid_con_espacios(): void
    {
        $raw = <<<'TXT'
Mac               SSID                    Time  TxRate  RxRate  Mode  PowerSave
00:e0:fc:D6:73:45 Vodafone Fibra - XXXXXX 16    54M     2M      11g   on
TXT;
        $rows = HuaweiOnuService::parseWifiAssociate($raw);

        $this->assertCount(1, $rows);
        $this->assertSame('00:E0:FC:D6:73:45', $rows[0]['mac']);
        $this->assertSame('Vodafone Fibra - XXXXXX', $rows[0]['ssid']);
        $this->assertSame('16', $rows[0]['tiempo']);
        $this->assertSame('54M', $rows[0]['tx']);
        $this->assertSame('2M', $rows[0]['rx']);
    }

    public function test_parse_dhcp_users_formato_eg8145(): void
    {
        $raw = <<<'TXT'
Index User   IP              Host   HW Addr           Expire-time       
      Port                   Name   
----------------------------------------------------------------------
1     SSID5  192.168.100.4   S24-Ul 8a:58:f7:c9:92:17 0 days, 23:52:06
----------------------------------------------------------------------
Total: 1
TXT;
        $rows = HuaweiOnuService::parseDhcpUsers($raw);

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['index']);
        $this->assertSame('SSID5', $rows[0]['puerto']);
        $this->assertSame('192.168.100.4', $rows[0]['ip']);
        $this->assertSame('S24-Ul', $rows[0]['host']);
        $this->assertSame('8A:58:F7:C9:92:17', $rows[0]['mac']);
        $this->assertStringContainsString('0 days', $rows[0]['expira']);
    }

    public function test_parse_dhcp_users(): void
    {
        $raw = <<<'TXT'
Index User IP          Host   HW Addr           Expire-time          Port Name
1     LAN1 192.168.1.2 phone  00:11:22:33:44:55 2 days, 23:59:24     LAN1
TXT;
        $rows = HuaweiOnuService::parseDhcpUsers($raw);

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['index']);
        $this->assertSame('LAN1', $rows[0]['puerto']);
        $this->assertSame('192.168.1.2', $rows[0]['ip']);
        $this->assertSame('phone', $rows[0]['host']);
        $this->assertSame('00:11:22:33:44:55', $rows[0]['mac']);
        $this->assertStringContainsString('2 days', $rows[0]['expira']);
    }

    public function test_parse_wifi_indices_y_ssids(): void
    {
        $raw = <<<'TXT'
SSID Index          : 1
SSID                : INTERPLUS
SSID Index          : 5
SSID                : INTERPLUS_5G
TXT;

        $this->assertSame([1, 5], HuaweiOnuService::parseWifiIndices($raw));
        $this->assertSame(['INTERPLUS', 'INTERPLUS_5G'], HuaweiOnuService::parseWifiSsids($raw));
        $this->assertSame('INTERPLUS', HuaweiOnuService::ssidPrincipal(['INTERPLUS', 'INTERPLUS_5G']));
        $this->assertSame('Casa', HuaweiOnuService::ssidPrincipal(['Casa_5G']));
        $this->assertSame('', HuaweiOnuService::ssidPrincipal([]));
    }

    public function test_cruzar_wifi_y_dhcp_por_mac(): void
    {
        $cruzado = HuaweiOnuService::cruzarDispositivos(
            [[
                'mac' => '8A:58:F7:C9:92:17',
                'ssid' => 'Test_5G',
                'tiempo' => '807',
                'tx' => '234M',
                'rx' => '6M',
            ]],
            [[
                'puerto' => 'SSID5',
                'ip' => '192.168.100.4',
                'host' => 'S24-Ul',
                'mac' => '8a:58:f7:c9:92:17',
                'expira' => '0 days, 23:52:06',
            ]]
        );

        $this->assertCount(1, $cruzado);
        $this->assertSame([
            'host' => 'S24-Ul',
            'ip' => '192.168.100.4',
            'mac' => '8A:58:F7:C9:92:17',
            'ssid' => 'Test_5G',
            'tiempo' => '807',
        ], $cruzado[0]);
    }

    public function test_cruzar_incluye_solo_wifi_o_solo_dhcp(): void
    {
        $cruzado = HuaweiOnuService::cruzarDispositivos(
            [[
                'mac' => 'AA:BB:CC:DD:EE:01',
                'ssid' => 'Casa',
                'tiempo' => '10',
            ]],
            [[
                'ip' => '192.168.100.9',
                'host' => 'TV',
                'mac' => 'aa-bb-cc-dd-ee-02',
            ]]
        );

        $this->assertCount(2, $cruzado);
        $this->assertSame('Casa', $cruzado[0]['ssid']);
        $this->assertSame('', $cruzado[0]['ip']);
        $this->assertSame('TV', $cruzado[1]['host']);
        $this->assertSame('', $cruzado[1]['ssid']);
    }

    public function test_parse_dhcp_host_name_completo(): void
    {
        $raw = <<<'TXT'
 Index               :1
 User Port           :SSID5
 IP                  :192.168.100.4
 HW Addr             :8a:58:f7:c9:92:17
 Host Name           :S24-Ultra-de-Jose
 Lease Expire        :0 days, 23:37:28
 Device Type         :android-dhcp-16
 Status              :Online
TXT;
        $this->assertSame('S24-Ultra-de-Jose', HuaweiOnuService::parseDhcpHostName($raw));

        $leases = HuaweiOnuService::enriquecerHostsDhcp(
            [['index' => '1', 'host' => 'S24-Ul', 'ip' => '192.168.100.4', 'mac' => '8A:58:F7:C9:92:17']],
            [$raw]
        );
        $this->assertSame('S24-Ultra-de-Jose', $leases[0]['host']);
        $this->assertSame(['display dhcp server user index 1'], HuaweiOnuService::comandosDetalleDhcp($leases));
    }

    public function test_parse_optic_formato_eg8145(): void
    {
        $raw = <<<'TXT'
WAP>display optic
---------------------------------------
Temperature(C)                    :50
Supply votage(V)                  :3.26
TX bias current(mA)               :15
TX power(dBm)                     :2.36
RX power(dBm)                     :-20.18
success!
TXT;
        $optic = HuaweiOnuService::parseOptic($raw);

        $this->assertSame(-20.18, $optic['rx_power_dbm']);
        $this->assertSame(2.36, $optic['tx_power_dbm']);
        $this->assertSame(50.0, $optic['temperatura_c']);
    }

    public function test_parse_optic_etiquetas_largas(): void
    {
        $raw = <<<'TXT'
Rx optical power                  : -18,50 dBm
Tx optical power                  : 1.90 dBm
Temperature                       : 47
TXT;
        $optic = HuaweiOnuService::parseOptic($raw);

        $this->assertSame(-18.5, $optic['rx_power_dbm']);
        $this->assertSame(1.9, $optic['tx_power_dbm']);
        $this->assertSame(47.0, $optic['temperatura_c']);
    }

    public function test_parse_optic_vacio(): void
    {
        $optic = HuaweiOnuService::parseOptic('success!');

        $this->assertNull($optic['rx_power_dbm']);
        $this->assertNull($optic['tx_power_dbm']);
        $this->assertNull($optic['temperatura_c']);
    }
}
