<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Http\Middleware\VerifyInterplusAgentSecret;
use Illuminate\Http\Request;
use Tests\TestCase;

class WaAgentToolsAuthTest extends TestCase
{
    public function test_rechaza_sin_secreto(): void
    {
        config(['whatsapp.agent.secret' => 'secreto-test']);
        $mw = new VerifyInterplusAgentSecret;
        $req = Request::create('/api/v1/wa-agent/tools/catalogo-planes', 'GET');
        $res = $mw->handle($req, fn () => response()->json(['ok' => true]));
        $this->assertSame(401, $res->getStatusCode());
    }

    public function test_acepta_secreto_correcto(): void
    {
        config(['whatsapp.agent.secret' => 'secreto-test']);
        $mw = new VerifyInterplusAgentSecret;
        $req = Request::create('/api/v1/wa-agent/tools/catalogo-planes', 'GET');
        $req->headers->set('X-Interplus-Secret', 'secreto-test');
        $res = $mw->handle($req, fn () => response()->json(['ok' => true], 200));
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_backend_langgraph_en_sandbox(): void
    {
        config([
            'whatsapp.agent.backend' => 'n8n',
            'whatsapp.agent.langgraph_sandbox' => true,
            'whatsapp.agent.langgraph_url' => 'http://127.0.0.1:8091/v1/turn',
            'whatsapp.agent.url' => 'http://127.0.0.1:5678/webhook/x',
        ]);
        $agent = app(\App\Services\WhatsApp\WhatsAppAgentService::class);
        $this->assertSame('langgraph', $agent->backendActivo(true));
        $this->assertSame('n8n', $agent->backendActivo(false));
    }

    public function test_backend_langgraph_global(): void
    {
        config([
            'whatsapp.agent.backend' => 'langgraph',
            'whatsapp.agent.langgraph_url' => 'http://127.0.0.1:8091/v1/turn',
        ]);
        $agent = app(\App\Services\WhatsApp\WhatsAppAgentService::class);
        $this->assertSame('langgraph', $agent->backendActivo(false));
    }
}
