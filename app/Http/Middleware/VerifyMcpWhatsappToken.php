<?php

namespace App\Http\Middleware;

use App\Services\Mcp\McpOAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer del conector MCP de WhatsApp. No usa la sesión del panel.
 */
class VerifyMcpWhatsappToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('mcp.whatsapp.token', '');
        $dado = (string) $request->bearerToken();
        $fijo = $token !== '' && $dado !== '' && strlen($token) === strlen($dado) && hash_equals($token, $dado);

        if (! $fijo && ! $this->tokenOAuthValido($dado)) {
            $meta = app(McpOAuthService::class)->cabeceraNoAutorizado();

            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'No autorizado.'],
            ], 401)->header('WWW-Authenticate', $meta);
        }

        return $next($request);
    }

    private function tokenOAuthValido(string $dado): bool
    {
        if (! str_starts_with($dado, 'inf_mcp_')) {
            return false;
        }

        try {
            return app(McpOAuthService::class)->usuarioIdDeAccessToken($dado) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
