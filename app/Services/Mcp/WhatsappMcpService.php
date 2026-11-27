<?php

namespace App\Services\Mcp;

use App\Models\WhatsappContacto;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\ClientePorTelefonoService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Str;

/**
 * Herramientas MCP de WhatsApp. Claude propone; Infinity envía solo con confirmar=true.
 */
class WhatsappMcpService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly ClientePorTelefonoService $clientes,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public static function definiciones(): array
    {
        return [
            [
                'name' => 'whatsapp_pendientes',
                'description' => 'Chats de WhatsApp con mensajes del cliente sin leer. No envía nada.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30],
                    ],
                ],
            ],
            [
                'name' => 'whatsapp_hilo',
                'description' => 'Últimos mensajes de un teléfono. Llamala antes de proponer una respuesta.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'telefono' => ['type' => 'string'],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 40],
                    ],
                    'required' => ['telefono'],
                ],
            ],
            [
                'name' => 'whatsapp_contexto',
                'description' => 'Plan, saldo, estado y zona del cliente. Sin cédula ni claves. No inventes cifras que no vengan acá.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'telefono' => ['type' => 'string'],
                    ],
                    'required' => ['telefono'],
                ],
            ],
            [
                'name' => 'whatsapp_enviar',
                'description' => 'Envía un texto de WhatsApp solo si confirmar es true y la persona lo pidió en este chat. Si confirmar no es true, devuelve el borrador y no envía.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'telefono' => ['type' => 'string'],
                        'texto' => ['type' => 'string'],
                        'confirmar' => ['type' => 'boolean'],
                    ],
                    'required' => ['telefono', 'texto', 'confirmar'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    public function llamar(array $params): array
    {
        $nombre = (string) ($params['name'] ?? '');
        $args = $params['arguments'] ?? [];
        if (! is_array($args)) {
            $args = [];
        }

        $datos = match ($nombre) {
            'whatsapp_pendientes' => $this->pendientes((int) ($args['limite'] ?? 15)),
            'whatsapp_hilo' => $this->hilo((string) ($args['telefono'] ?? ''), (int) ($args['limite'] ?? 20)),
            'whatsapp_contexto' => $this->contexto((string) ($args['telefono'] ?? '')),
            'whatsapp_enviar' => $this->enviar(
                (string) ($args['telefono'] ?? ''),
                (string) ($args['texto'] ?? ''),
                $args['confirmar'] ?? false,
            ),
            default => ['error' => 'Herramienta desconocida.'],
        };

        $error = isset($datos['error']) || (($datos['enviado'] ?? null) === false && ($datos['motivo'] ?? '') !== 'sin_confirmacion');

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            ]],
            'isError' => $error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pendientes(int $limite): array
    {
        $limite = max(1, min(30, $limite));
        $agg = WhatsappMensaje::query()
            ->excluirSandboxN8n()
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->selectRaw("telefono, MAX(id) as ultimo_id, SUM(CASE WHEN direccion = 'entrada' AND estado != 'leido' THEN 1 ELSE 0 END) as sin_leer")
            ->groupBy('telefono')
            ->havingRaw("SUM(CASE WHEN direccion = 'entrada' AND estado != 'leido' THEN 1 ELSE 0 END) > 0")
            ->orderByDesc('ultimo_id')
            ->limit($limite)
            ->get();

        $ultimos = WhatsappMensaje::query()
            ->whereIn('id', $agg->pluck('ultimo_id')->filter()->all())
            ->get()
            ->keyBy('id');
        $contactos = WhatsappContacto::query()
            ->with('cliente:cliente_id,nombre,apellido')
            ->whereIn('telefono', $agg->pluck('telefono')->all())
            ->get()
            ->keyBy('telefono');

        $chats = [];
        foreach ($agg as $row) {
            $ultimo = $ultimos->get($row->ultimo_id);
            $contacto = $contactos->get($row->telefono);
            $chats[] = [
                'telefono' => (string) $row->telefono,
                'nombre' => $this->nombreVisible($contacto, $ultimo),
                'sin_leer' => (int) $row->sin_leer,
                'ultimo' => $this->textoCorto($ultimo?->cuerpo),
                'ultimo_de' => $ultimo?->direccion === WhatsappMensaje::DIRECCION_ENTRADA ? 'cliente' : 'interplus',
                'hace' => $ultimo?->created_at?->diffForHumans(),
            ];
        }

        return [
            'chats' => $chats,
            'nota' => 'Leé el hilo y proponé el texto. No llames whatsapp_enviar hasta que pidan enviarlo.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hilo(string $telefono, int $limite): array
    {
        $tel = $this->whatsapp->normalizePhone($telefono);
        if (! $tel) {
            return ['error' => 'Teléfono inválido.'];
        }
        $limite = max(1, min(40, $limite));

        $mensajes = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->reverse()
            ->values();

        $ultimaEntrada = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->latest('id')
            ->first();

        return [
            'telefono' => $tel,
            'ventana_24h' => $this->ventanaAbierta($ultimaEntrada),
            'mensajes' => $mensajes->map(fn (WhatsappMensaje $m) => [
                'de' => $m->direccion === WhatsappMensaje::DIRECCION_ENTRADA ? 'cliente' : 'interplus',
                'texto' => $this->textoCorto($m->cuerpo, 800) ?: '['.$m->tipo.']',
                'fecha' => $m->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contexto(string $telefono): array
    {
        $tel = $this->whatsapp->normalizePhone($telefono);
        if (! $tel) {
            return ['error' => 'Teléfono inválido.'];
        }

        $ficha = $this->clientes->buscar($tel);
        $ultimaEntrada = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->latest('id')
            ->first();

        return [
            'telefono' => $tel,
            'encontrado' => (bool) ($ficha['encontrado'] ?? false),
            'nombre' => $ficha['nombre'] ?? null,
            'plan' => $ficha['plan'] ?? null,
            'plan_tecnologia' => $ficha['plan_tecnologia'] ?? null,
            'estado_servicio' => $ficha['estado_servicio'] ?? null,
            'saldo_pendiente' => $ficha['saldo_pendiente'] ?? null,
            'dias_mora' => $ficha['dias_mora'] ?? null,
            'localidad' => $ficha['localidad'] ?? null,
            'tiene_ticket_abierto' => $ficha['tiene_ticket_abierto'] ?? false,
            'ventana_24h' => $this->ventanaAbierta($ultimaEntrada),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function enviar(string $telefono, string $texto, mixed $confirmar): array
    {
        $texto = trim($texto);
        if ($confirmar !== true) {
            return [
                'enviado' => false,
                'motivo' => 'sin_confirmacion',
                'borrador' => $texto,
                'nota' => 'No se envió. Mostrá el texto y esperá un sí explícito. Después llamá de nuevo con confirmar=true.',
            ];
        }
        if ($texto === '') {
            return ['enviado' => false, 'error' => 'El texto está vacío.'];
        }

        $tel = $this->whatsapp->normalizePhone($telefono);
        if (! $tel) {
            return ['enviado' => false, 'error' => 'Teléfono inválido.'];
        }
        if (! $this->whatsapp->isConfigured()) {
            return ['enviado' => false, 'error' => 'WhatsApp no está habilitado en el servidor.'];
        }

        $ultimaEntrada = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->latest('id')
            ->first();
        if (! $this->ventanaAbierta($ultimaEntrada)) {
            return [
                'enviado' => false,
                'error' => 'La ventana de 24 horas está cerrada. El texto libre no sale. Hay que usar una plantilla desde el panel.',
            ];
        }

        $mensaje = $this->whatsapp->sendText($tel, Str::limit($texto, 4000, ''), [
            'contexto_tipo' => 'mcp',
        ]);

        return [
            'enviado' => $mensaje->estado !== WhatsappMensaje::ESTADO_FALLIDO,
            'estado' => $mensaje->estado,
            'mensaje_id' => (int) $mensaje->id,
            'error' => $mensaje->estado === WhatsappMensaje::ESTADO_FALLIDO
                ? ($mensaje->error_message ?: 'No se pudo enviar.')
                : null,
        ];
    }

    private function ventanaAbierta(?WhatsappMensaje $entrada): bool
    {
        return $entrada?->created_at !== null
            && $entrada->created_at->greaterThanOrEqualTo(now()->subHours(24));
    }

    private function nombreVisible(?WhatsappContacto $contacto, ?WhatsappMensaje $ultimo): string
    {
        $cliente = $contacto?->cliente;
        $crm = trim(((string) ($cliente->nombre ?? '')).' '.((string) ($cliente->apellido ?? '')));
        if ($crm !== '') {
            return $crm;
        }
        $guardado = trim((string) ($contacto?->nombre ?? ''));
        if ($guardado !== '') {
            return $guardado;
        }

        return trim((string) ($ultimo?->contacto_nombre ?? '')) ?: 'Sin nombre';
    }

    private function textoCorto(?string $texto, int $max = 240): string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? '' : Str::limit($texto, $max, '…');
    }
}
