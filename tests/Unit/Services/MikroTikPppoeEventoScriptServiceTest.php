<?php

namespace Tests\Unit\Services;

use App\Services\MikroTikPppoeEventoScriptService;
use App\Services\MikroTikService;
use Tests\TestCase;

class MikroTikPppoeEventoScriptServiceTest extends TestCase
{
    public function test_genera_source_up_con_token_y_url(): void
    {
        $service = new MikroTikPppoeEventoScriptService($this->createMock(MikroTikService::class));
        $src = $service->source('up', 'abc123token', 'https://infinityisppro.net/api/v1/webhooks/mikrotik/pppoe');

        $this->assertStringContainsString('evento=up', $src);
        $this->assertStringContainsString('Authorization: Bearer abc123token', $src);
        $this->assertStringContainsString('https://infinityisppro.net/api/v1/webhooks/mikrotik/pppoe', $src);
        $this->assertStringContainsString('check-certificate=no', $src);
        $this->assertStringContainsString('keep-result=no', $src);
    }

    public function test_http_no_agrega_check_certificate(): void
    {
        $service = new MikroTikPppoeEventoScriptService($this->createMock(MikroTikService::class));
        $src = $service->source('down', 'tok', 'http://10.0.0.1/api/v1/webhooks/mikrotik/pppoe');

        $this->assertStringContainsString('evento=down', $src);
        $this->assertStringNotContainsString('check-certificate', $src);
    }
}
