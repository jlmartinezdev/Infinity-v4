<?php

namespace Tests\Unit\Services;

use App\Services\Auth\LoginIntentoLimiter;
use App\Services\ClientePortalUserService;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginIntentoLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login-cuenta:'.hash('sha256', 'a@b.c'));
        RateLimiter::clear('login-cuenta:'.hash('sha256', 'otra@b.c'));
    }

    public function test_bloquea_la_cuenta_y_no_a_otra(): void
    {
        $limiter = new LoginIntentoLimiter;

        for ($i = 0; $i < LoginIntentoLimiter::MAX_FALLOS; $i++) {
            $this->assertFalse($limiter->bloqueado('a@b.c'));
            $limiter->registrarFallo('A@B.C');
        }

        $this->assertTrue($limiter->bloqueado('a@b.c'));
        $this->assertFalse($limiter->bloqueado('otra@b.c'));

        $limiter->limpiar('a@b.c');
        $this->assertFalse($limiter->bloqueado('a@b.c'));
    }

    public function test_clave_portal_nueva_es_mas_larga_que_plus_y_cuatro_digitos(): void
    {
        $clave = (new ClientePortalUserService)->generarClavePlus();

        $this->assertMatchesRegularExpression('/^PLUS[A-HJ-NP-Z2-9]{8}$/', $clave);
        $this->assertSame(12, strlen($clave));
    }
}
