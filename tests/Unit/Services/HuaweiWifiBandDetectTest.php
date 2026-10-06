<?php

namespace Tests\Unit\Services;

use App\Services\Huawei\HuaweiOnuService;
use Tests\TestCase;

class HuaweiWifiBandDetectTest extends TestCase
{
    public function test_parse_wifi_radios_usa_canal_y_estandar_no_nombre(): void
    {
        $raw = <<<'TXT'
SSID Index                    :1
SSID                          :CasaSinEtiqueta
Channel                       :4(auto)
Standard                      :11bgn
Enable                        :Enabled
----------------------------------------------------
SSID Index                    :5
SSID                          :OtraRedSin5G
Channel                       :149(auto)
Standard                      :11ac
Enable                        :Enabled
----------------------------------------------------
TXT;

        $radios = HuaweiOnuService::parseWifiRadios($raw);

        $this->assertCount(2, $radios);
        $this->assertSame('CasaSinEtiqueta', $radios[0]['ssid']);
        $this->assertSame('2.4GHz', $radios[0]['band']);
        $this->assertSame(4, $radios[0]['channel']);
        $this->assertSame('OtraRedSin5G', $radios[1]['ssid']);
        $this->assertSame('5GHz', $radios[1]['band']);
        $this->assertSame(149, $radios[1]['channel']);
    }

    public function test_juliana_sofi_5g_con_guion_queda_5ghz_por_canal(): void
    {
        $raw = <<<'TXT'
SSID Index                    :1
SSID                          :Sofi-2.4G
Channel                       :4(auto)
Standard                      :11bgn
----------------------------------------------------
SSID Index                    :5
SSID                          :Sofi-5G-
Channel                       :149(auto)
Standard                      :11ac
----------------------------------------------------
TXT;

        $radios = HuaweiOnuService::parseWifiRadios($raw);
        $this->assertSame('2.4GHz', $radios[0]['band']);
        $this->assertSame('5GHz', $radios[1]['band']);
    }

    public function test_indice_huawei_sin_canal_como_fallback(): void
    {
        $this->assertSame('2.4GHz', HuaweiOnuService::inferBandFromRadio(1, null, null));
        $this->assertSame('5GHz', HuaweiOnuService::inferBandFromRadio(5, null, null));
        $this->assertSame('5GHz', HuaweiOnuService::inferBandFromRadio(5, null, '11ac'));
        $this->assertSame('2.4GHz', HuaweiOnuService::inferBandFromRadio(1, 6, '11bgn'));
    }
}
