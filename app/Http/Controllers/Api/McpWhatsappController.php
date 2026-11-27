<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mcp\WhatsappMcpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class McpWhatsappController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();
        $id = $payload['id'] ?? null;
        $method = (string) ($payload['method'] ?? '');

        if (! array_key_exists('jsonrpc', $payload)) {
            return $this->error($id, -32600, 'El cuerpo tiene que ser JSON-RPC 2.0.');
        }

        if ($id === null) {
            return response()->noContent(202);
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize(is_array($payload['params'] ?? null) ? $payload['params'] : []),
                'tools/list' => ['tools' => WhatsappMcpService::definiciones()],
                'ping' => new \stdClass(),
                'tools/call' => app(WhatsappMcpService::class)->llamar(
                    is_array($payload['params'] ?? null) ? $payload['params'] : []
                ),
                default => throw new \InvalidArgumentException('Método no encontrado.'),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->error($id, -32601, $e->getMessage());
        }

        $sesion = (string) ($request->header('Mcp-Session-Id') ?: Str::uuid());

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ])->header('Mcp-Session-Id', $sesion);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $pedida = (string) ($params['protocolVersion'] ?? '2025-03-26');
        $version = in_array($pedida, ['2024-11-05', '2025-03-26'], true) ? $pedida : '2025-03-26';

        return [
            'protocolVersion' => $version,
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'infinity-whatsapp',
                'version' => '1.0.0',
            ],
            'instructions' => 'Asistente de WhatsApp de Interplus. Antes de proponer, leé whatsapp_pendientes o whatsapp_hilo y whatsapp_contexto. Trato de usted, sin voseo. No inventes saldos, precios ni estados. Mostrá el mensaje y no lo envíes hasta que la persona lo pida de forma explícita. Solo entonces llamá whatsapp_enviar con confirmar=true. Si la ventana de 24 horas está cerrada, no envíes texto libre.',
        ];
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ], $code === -32600 ? 400 : 200);
    }
}
