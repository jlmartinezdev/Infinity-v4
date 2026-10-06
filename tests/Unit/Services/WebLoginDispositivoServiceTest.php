<?php

namespace Tests\Unit\Services;

use App\Services\Auth\WebLoginDispositivoService;
use Tests\TestCase;

class WebLoginDispositivoServiceTest extends TestCase
{
    public function test_hash_es_sha256_estable(): void
    {
        $this->assertSame(
            hash('sha256', 'abc'),
            WebLoginDispositivoService::hashToken('abc')
        );
        $this->assertSame(64, strlen(WebLoginDispositivoService::hashToken('abc')));
    }
}
