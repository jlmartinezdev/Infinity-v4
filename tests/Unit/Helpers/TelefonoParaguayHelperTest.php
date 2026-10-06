<?php

namespace Tests\Unit\Helpers;

use App\Helpers\TelefonoParaguayHelper;
use Tests\TestCase;

class TelefonoParaguayHelperTest extends TestCase
{
    public function test_formatea_movil_como_595_9xx_xxxxxx(): void
    {
        $this->assertSame('595 984 995239', TelefonoParaguayHelper::formatear('595984995239'));
        $this->assertSame('595 984 995239', TelefonoParaguayHelper::formatear('+595984995239'));
        $this->assertSame('595 984 995239', TelefonoParaguayHelper::formatear('0984995239'));
        $this->assertSame('595 984 995239', TelefonoParaguayHelper::formatear('984995239'));
    }

    public function test_vacio_queda_vacio(): void
    {
        $this->assertSame('', TelefonoParaguayHelper::formatear(null));
        $this->assertSame('', TelefonoParaguayHelper::formatear('  '));
    }
}
