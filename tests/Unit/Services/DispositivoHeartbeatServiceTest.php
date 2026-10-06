<?php

namespace Tests\Unit\Services;

use App\Services\Portal\DispositivoHeartbeatService;
use Illuminate\Http\Request;
use Tests\TestCase;

class DispositivoHeartbeatServiceTest extends TestCase
{
    public function test_normalizar_version(): void
    {
        $this->assertNull(DispositivoHeartbeatService::normalizarVersion(null));
        $this->assertNull(DispositivoHeartbeatService::normalizarVersion('  '));
        $this->assertSame('3.2.12', DispositivoHeartbeatService::normalizarVersion(' 3.2.12 '));
        $this->assertSame(40, strlen(DispositivoHeartbeatService::normalizarVersion(str_repeat('1', 50))));
    }

    public function test_version_desde_request_prioriza_header(): void
    {
        $req = Request::create('/api/v1/me', 'GET', ['app_version' => '1.0.0']);
        $req->headers->set('X-App-Version', '3.2.12');

        $this->assertSame('3.2.12', DispositivoHeartbeatService::versionDesdeRequest($req));
    }
}
