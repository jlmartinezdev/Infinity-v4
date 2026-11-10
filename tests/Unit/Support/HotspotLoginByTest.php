<?php

namespace Tests\Unit\Support;

use App\Support\HotspotLoginBy;
use Tests\TestCase;

class HotspotLoginByTest extends TestCase
{
    public function test_pone_mac_primero_y_quita_la_cookie(): void
    {
        $this->assertSame(
            'mac,http-chap,http-pap',
            HotspotLoginBy::paraRoaming('http-chap,cookie')
        );
    }

    public function test_conserva_el_trial(): void
    {
        $this->assertSame(
            'mac,http-pap,trial,http-chap',
            HotspotLoginBy::paraRoaming('http-pap,trial,cookie')
        );
    }
}
