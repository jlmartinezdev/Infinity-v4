<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth para tools del agente (LangGraph / n8n): header X-Interplus-Secret.
 */
class VerifyInterplusAgentSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('whatsapp.agent.secret', '');
        $dado = (string) $request->header('X-Interplus-Secret', '');
        if ($secret === '' || ! hash_equals($secret, $dado)) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
