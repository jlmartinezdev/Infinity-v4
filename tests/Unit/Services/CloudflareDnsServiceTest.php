<?php

namespace Tests\Unit\Services;

use App\Services\Cloudflare\CloudflareDnsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDnsServiceTest extends TestCase
{
    public function test_sin_token_no_llama_a_cloudflare(): void
    {
        config([
            'services.cloudflare.token' => '',
            'services.cloudflare.zone' => 'infinityispro.net',
            'services.cloudflare.origin_ufinet' => '186.33.34.14',
        ]);
        Http::fake();

        $r = app(CloudflareDnsService::class)->apuntar('186.33.34.14');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('CLOUDFLARE_API_TOKEN', $r['message']);
        Http::assertNothingSent();
    }

    public function test_actualiza_registros_a_del_origen(): void
    {
        config([
            'services.cloudflare.token' => 'test-token',
            'services.cloudflare.zone' => 'infinityispro.net',
            'services.cloudflare.record_names' => '',
            'services.cloudflare.origin_tigo' => '200.26.179.94',
            'services.cloudflare.origin_ufinet' => '186.33.34.14',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/dns_records/') && $request->method() === 'PATCH') {
                return Http::response(['success' => true, 'result' => []], 200);
            }
            if (str_contains($url, '/dns_records')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        ['id' => 'rec1', 'name' => 'infinityispro.net', 'content' => '200.26.179.94', 'proxied' => true, 'ttl' => 1],
                        ['id' => 'rec2', 'name' => 'mail.infinityispro.net', 'content' => '1.2.3.4', 'proxied' => false, 'ttl' => 300],
                    ],
                ], 200);
            }

            return Http::response([
                'success' => true,
                'result' => [['id' => 'zone1']],
            ], 200);
        });

        $r = app(CloudflareDnsService::class)->apuntar('186.33.34.14');

        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['updated']);
        Http::assertSent(function ($request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/dns_records/rec1')
                && $request['content'] === '186.33.34.14';
        });
    }
}
