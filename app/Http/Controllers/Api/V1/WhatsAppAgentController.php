<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\WhatsApp\WhatsAppAgentService;
use Illuminate\Http\Request;

class WhatsAppAgentController extends ApiController
{
    public function hilo(Request $request, WhatsAppAgentService $agent)
    {
        $telefono = trim((string) $request->query('telefono', ''));
        if ($telefono === '') {
            return $this->fail('Indicá telefono.', 422);
        }

        $limite = $request->filled('limite') ? (int) $request->query('limite') : null;

        return $this->ok($agent->hiloParaTelefono($telefono, null, $limite));
    }
}
