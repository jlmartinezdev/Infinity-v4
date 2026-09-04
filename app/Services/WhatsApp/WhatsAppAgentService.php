<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcesarWhatsappAgentJob;
use App\Models\Plan;
use App\Models\WhatsappMensaje;
use App\Services\PedidoNodoOpcionesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Plugin N8N: reenvía texto entrante, envía reply y escala a ticket si corresponde.
 */
class WhatsAppAgentService
{
    public const CONTEXTO = 'wa_agent';

    public const CONTEXTO_TEST = WhatsappMensaje::CONTEXTO_TEST_N8N;

    public const PREFIJO_TEST = WhatsappMensaje::TELEFONO_SANDBOX_PREFIX;

    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppInboundTicketService $inboundTickets,
        private readonly WhatsAppImagenClasificadorService $imagenes,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('whatsapp.agent.enabled')
            && filled(config('whatsapp.agent.url'));
    }

    public function debeProcesar(WhatsappMensaje $mensaje): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $mensaje->esEntrada()) {
            return false;
        }

        $esTexto = $mensaje->tipo === 'text' && trim((string) $mensaje->cuerpo) !== '';
        $esImagen = $mensaje->tipo === 'image';
        if (! $esTexto && ! $esImagen) {
            return false;
        }

        if ($this->yaProcesado($mensaje)) {
            return false;
        }

        if ((bool) config('whatsapp.agent.auto_send') && $this->humanoAtendiendo($mensaje)) {
            return false;
        }

        return true;
    }

    public function dispatchAfterResponse(WhatsappMensaje $mensaje): void
    {
        ProcesarWhatsappAgentJob::dispatch($mensaje->id)->afterResponse();
    }

    /**
     * @return array{
     *   ok: bool,
     *   reply: string|null,
     *   escalate: bool,
     *   cliente_id: int|null,
     *   motivo_escalado: string|null,
     *   n8n_latency_ms: int,
     *   error: string|null,
     *   enviado: bool,
     *   ticket_id: int|null,
     *   modo: string|null
     * }
     */
    public function procesar(WhatsappMensaje $mensaje, ?bool $enviar = null, bool $sandbox = false): array
    {
        $enviar = $sandbox ? false : ($enviar ?? (bool) config('whatsapp.agent.auto_send', false));

        if ($this->yaProcesado($mensaje)) {
            return $this->resultadoVacio('duplicado');
        }

        $consulta = $this->consultarN8n($mensaje);
        $reply = $consulta['reply'];
        $escalate = $consulta['escalate'];
        $error = $consulta['error'];

        if ($error !== null || ($reply === null && ! $escalate)) {
            $error = $error ?: 'n8n_sin_reply';
            if ($enviar) {
                $reply = (string) config('whatsapp.agent.fallback_message');
            }
        }

        if ($escalate && ($reply === null || $reply === '')) {
            $reply = (string) config('whatsapp.agent.escalate_message');
        }

        $ticketId = null;
        if (! $sandbox && $escalate && ($enviar || (bool) config('whatsapp.agent.auto_ticket', false))) {
            $ticket = $this->inboundTickets->crearOAdjuntar($mensaje, false);
            $ticketId = $ticket['ticket']?->id;
        }

        $enviado = false;
        $debeEnviar = $enviar && filled($reply) && (
            ! $escalate || (bool) config('whatsapp.agent.enviar_reply_en_escalado', true)
        );

        if ($debeEnviar) {
            $salida = $this->whatsapp->sendText($mensaje->telefono, $reply, [
                'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
                'ticket_id' => $ticketId ?? $mensaje->ticket_id,
                'contexto_tipo' => self::CONTEXTO,
                'contexto_id' => $mensaje->id,
            ]);
            $enviado = ! $salida->esFallido();
            if ($salida->esFallido()) {
                $error = trim(($error ? $error.' | ' : '').'meta: '.($salida->error_message ?: 'envio_fallido'));
            }
        }

        $this->auditarEntrada($mensaje, $consulta, $reply, $escalate, $error, $ticketId);

        Log::info('[WA agent] Procesado', [
            'message_id' => $mensaje->wamid,
            'wa_id' => $mensaje->telefono,
            'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
            'escalate' => $escalate,
            'n8n_latency_ms' => $consulta['n8n_latency_ms'],
            'error' => $error,
            'enviado' => $enviado,
            'ticket_id' => $ticketId,
        ]);

        return [
            'ok' => $error === null,
            'reply' => $reply,
            'escalate' => $escalate,
            'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
            'motivo_escalado' => $consulta['motivo_escalado'],
            'n8n_latency_ms' => $consulta['n8n_latency_ms'],
            'error' => $error,
            'enviado' => $enviado,
            'ticket_id' => $ticketId,
            'modo' => $consulta['modo'] ?? null,
        ];
    }

    /**
     * @return array{
     *   reply: string|null,
     *   escalate: bool,
     *   cliente_id: int|null,
     *   motivo_escalado: string|null,
     *   n8n_latency_ms: int,
     *   error: string|null
     * }
     */
    public function consultarN8n(WhatsappMensaje $mensaje): array
    {
        $url = (string) config('whatsapp.agent.url');
        $secret = (string) config('whatsapp.agent.secret');
        $timeoutSec = max(1, (int) ceil(((int) config('whatsapp.agent.timeout_ms', 25000)) / 1000));
        $started = microtime(true);

        $vacio = [
            'reply' => null,
            'escalate' => false,
            'cliente_id' => null,
            'motivo_escalado' => null,
            'n8n_latency_ms' => 0,
            'error' => null,
            'modo' => null,
        ];

        if ($url === '') {
            $vacio['error'] = 'n8n_url_vacia';

            return $vacio;
        }

        $timestamp = data_get($mensaje->payload, 'timestamp');
        $timestamp = is_numeric($timestamp) ? (int) $timestamp : (int) ($mensaje->created_at?->timestamp ?? time());

        $hilo = $this->hiloParaTelefono(
            (string) $mensaje->telefono,
            $mensaje->id ? (int) $mensaje->id : null,
        );
        $catalogo = $this->catalogoPlanes();
        $clasificacion = $mensaje->tipo === 'image'
            ? $this->imagenes->clasificar($mensaje)
            : ['tipo' => WhatsAppImagenClasificadorService::TIPO_DESCONOCIDO, 'ocr' => '', 'fuente' => null, 'descripcion' => ''];
        $tipoImagen = (string) ($clasificacion['tipo'] ?? '');
        $cuerpo = self::captionUtil((string) $mensaje->cuerpo);
        if ($cuerpo === '' && $mensaje->tipo === 'image') {
            $cuerpo = match ($tipoImagen) {
                WhatsAppImagenClasificadorService::TIPO_MAPA => 'Te envié una captura de mapa',
                WhatsAppImagenClasificadorService::TIPO_COMPROBANTE => 'Te envié un comprobante de transferencia',
                default => 'Te envié una foto',
            };
        }
        $pareceComprobante = self::detectarComprobante(
            (string) $mensaje->tipo,
            (string) $mensaje->cuerpo,
            $hilo['historial'],
            $tipoImagen,
        );
        $pareceMapa = self::detectarUbicacion(
            (string) $mensaje->tipo,
            (string) $mensaje->cuerpo,
            $hilo['historial'],
            $tipoImagen,
        );
        $horarioLaboral = self::horarioLaboral();
        $pareceDatosTransferencia = self::textoPareceDatosTransferencia($cuerpo);
        $parecePagoTexto = ! $pareceComprobante && ! $pareceMapa && ! $pareceDatosTransferencia && self::textoParecePago($cuerpo);
        $pareceBaja = self::textoPareceBajaServicio($cuerpo);
        $primeraVez = $this->esPrimeraConversacion((string) $mensaje->telefono, $mensaje->id ? (int) $mensaje->id : null);
        $pareceCedula = self::textoPareceCedula($cuerpo);
        $pareceBeneficios = self::textoPareceBeneficiosPlanes($cuerpo);
        $pareceCondiciones = self::textoPareceCondicionesServicio($cuerpo);
        $canned = self::textosCanned();
        $pareceInstalacion = ! $pareceBaja && (
            self::textoPareceInstalacion($cuerpo)
            || ($primeraVez && self::textoPareceWifi($cuerpo))
        );
        $nombreCorto = '';
        try {
            $cli = $this->whatsapp->findClienteByPhone($mensaje->telefono);
            if ($cli) {
                $nombreCorto = self::nombreCortoSaludo((string) ($cli->nombre ?? ''));
            }
        } catch (\Throwable) {
            // lookup opcional
        }

        try {
            $response = Http::timeout($timeoutSec)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(array_filter([
                    'X-Interplus-Secret' => $secret !== '' ? $secret : null,
                ]))
                ->post($url, [
                    'wa_id' => $mensaje->telefono,
                    'nombre_perfil' => null,
                    'mensaje' => $cuerpo,
                    'message_id' => (string) ($mensaje->wamid ?: 'local-'.$mensaje->id),
                    'timestamp' => $timestamp,
                    'tipo' => (string) ($mensaje->tipo ?: 'text'),
                    'historial' => $hilo['historial'],
                    'historial_texto' => $hilo['historial_texto'],
                    'planes_fibra' => $catalogo['fibra'],
                    'planes_antena' => $catalogo['antena'],
                    'planes_texto' => $catalogo['planes_texto'],
                    'texto_beneficios' => $canned['beneficios'],
                    'texto_transferencia' => $canned['transferencia'],
                    'texto_condiciones' => $canned['condiciones'],
                    'horario_laboral' => $horarioLaboral,
                    'parece_comprobante' => $pareceComprobante,
                    'parece_pago_texto' => $parecePagoTexto,
                    'parece_datos_transferencia' => $pareceDatosTransferencia,
                    'parece_beneficios' => $pareceBeneficios,
                    'parece_condiciones' => $pareceCondiciones,
                    'parece_mapa' => $pareceMapa,
                    'parece_cedula' => $pareceCedula,
                    'cobertura_aprobada' => $this->telefonoTieneCoberturaAprobada(
                        (string) $mensaje->telefono,
                        $mensaje->id ? (int) $mensaje->id : null,
                        $cuerpo,
                        is_array($mensaje->payload) ? $mensaje->payload : [],
                    ),
                    'parece_baja' => $pareceBaja,
                    'parece_instalacion' => $pareceInstalacion,
                    'primera_vez' => $primeraVez,
                    'nombre_corto' => $nombreCorto !== '' ? $nombreCorto : null,
                    'tipo_imagen' => $tipoImagen !== '' ? $tipoImagen : null,
                    'ocr_texto' => (string) ($clasificacion['ocr'] ?? ''),
                ]);
        } catch (\Throwable $e) {
            $vacio['n8n_latency_ms'] = (int) round((microtime(true) - $started) * 1000);
            $vacio['error'] = 'n8n_excepcion: '.$e->getMessage();

            return $vacio;
        }

        $vacio['n8n_latency_ms'] = (int) round((microtime(true) - $started) * 1000);

        if ($response->status() === 401) {
            $vacio['error'] = 'n8n_unauthorized';

            return $vacio;
        }

        if (! $response->successful()) {
            $vacio['error'] = 'n8n_http_'.$response->status();

            return $vacio;
        }

        $json = $response->json();
        if (! is_array($json)) {
            $vacio['error'] = 'n8n_json_invalido';

            return $vacio;
        }

        $reply = isset($json['reply']) ? trim((string) $json['reply']) : '';
        $vacio['reply'] = $reply !== '' ? mb_substr($reply, 0, 4096) : null;
        $vacio['escalate'] = filter_var($json['escalate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $vacio['cliente_id'] = isset($json['cliente_id']) && is_numeric($json['cliente_id'])
            ? (int) $json['cliente_id']
            : null;
        $motivo = isset($json['motivo_escalado']) ? trim((string) $json['motivo_escalado']) : '';
        $vacio['motivo_escalado'] = $motivo !== '' ? $motivo : null;
        $modo = isset($json['modo']) ? trim((string) $json['modo']) : '';
        $vacio['modo'] = $modo !== '' ? $modo : null;

        return $vacio;
    }

    /**
     * Planes activos agrupados para el prompt de N8N (fibra / antena).
     *
     * @return array{fibra: list<array<string, mixed>>, antena: list<array<string, mixed>>, otros: list<array<string, mixed>>, planes_texto: string}
     */
    public function catalogoPlanes(): array
    {
        $vacio = [
            'fibra' => [],
            'antena' => [],
            'otros' => [],
            'planes_texto' => '',
        ];

        try {
            $cached = Cache::remember('wa_agent.catalogo_planes', 300, fn () => $this->catalogoPlanesFresh());

            return is_array($cached) ? $cached : $vacio;
        } catch (\Throwable) {
            return $vacio;
        }
    }

    /**
     * @return array{fibra: list<array<string, mixed>>, antena: list<array<string, mixed>>, otros: list<array<string, mixed>>, planes_texto: string}
     */
    public function catalogoPlanesFresh(): array
    {
        $planes = Plan::query()
            ->with('tipoTecnologia')
            ->where('estado', 'activo')
            ->orderBy('precio')
            ->orderBy('nombre')
            ->get();

        $fibra = [];
        $antena = [];
        $otros = [];
        foreach ($planes as $plan) {
            $item = self::serializarPlanWa($plan);
            match ($item['grupo']) {
                'fibra' => $fibra[] = $item,
                'antena' => $antena[] = $item,
                default => $otros[] = $item,
            };
        }

        return [
            'fibra' => $fibra,
            'antena' => $antena,
            'otros' => $otros,
            'planes_texto' => self::planesComoTexto($fibra, $antena, $otros),
        ];
    }

    /**
     * @return array{plan_id: int, nombre: string, velocidad: string, precio: int, precio_texto: string, tecnologia: string|null, grupo: string}
     */
    public static function serializarPlanWa(Plan $plan): array
    {
        $tech = trim((string) ($plan->tipoTecnologia?->descripcion ?? ''));
        $nombre = (string) ($plan->nombre ?? '');
        $grupo = 'otros';
        if (PedidoNodoOpcionesService::descripcionEsGpon($tech) || PedidoNodoOpcionesService::descripcionEsGpon($nombre)) {
            $grupo = 'fibra';
        } elseif (PedidoNodoOpcionesService::descripcionEsWireless($tech) || PedidoNodoOpcionesService::descripcionEsWireless($nombre)) {
            $grupo = 'antena';
        }

        $precio = (int) round((float) $plan->precio);

        return [
            'plan_id' => (int) $plan->plan_id,
            'nombre' => $nombre,
            'velocidad' => (string) ($plan->velocidad ?? ''),
            'precio' => $precio,
            'precio_texto' => 'Gs. '.number_format($precio, 0, ',', '.'),
            'tecnologia' => $tech !== '' ? $tech : null,
            'grupo' => $grupo,
        ];
    }

    /**
     * @param  list<array{nombre?: string, velocidad?: string, precio_texto?: string}>  $fibra
     * @param  list<array{nombre?: string, velocidad?: string, precio_texto?: string}>  $antena
     * @param  list<array{nombre?: string, velocidad?: string, precio_texto?: string}>  $otros
     */
    public static function planesComoTexto(array $fibra, array $antena, array $otros = []): string
    {
        $bloques = [
            'Planes vigentes (mensual, IVA incluido, megas ilimitados, sin permanencia, equipos en comodato). Cotizá SOLO estos; no inventes precios.',
            'Enrutado: pregunta fibra/FTTH/100/200/300 → SOLO fibra. Pregunta antena/10/20/30 megas → SOLO antena. "Qué planes hay" → las dos listas.',
            'Salida WhatsApp: una línea por plan, oración completa, con Mbps. Ej: «Fibra Básico 100 Mbps — Gs. 100.000». Nunca digas que fibra es 10 o 20 Mbps. Cotizar no es derivar a asesor.',
        ];

        $seccion = static function (string $titulo, array $items): ?string {
            if ($items === []) {
                return null;
            }
            $lineas = [$titulo];
            foreach ($items as $item) {
                $nombre = trim((string) ($item['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $vel = trim((string) ($item['velocidad'] ?? ''));
                $precio = trim((string) ($item['precio_texto'] ?? ''));
                $extra = $vel !== '' && ! str_contains(mb_strtolower($nombre), mb_strtolower($vel))
                    ? ' ('.$vel.')'
                    : '';
                $lineas[] = '- '.$nombre.$extra.($precio !== '' ? ' — '.$precio : '');
            }

            return count($lineas) > 1 ? implode("\n", $lineas) : null;
        };

        foreach ([
            $seccion('Fibra (GPON):', $fibra),
            $seccion('Antena (WIRELESS):', $antena),
            $seccion('Otros:', $otros),
        ] as $bloque) {
            if ($bloque) {
                $bloques[] = $bloque;
            }
        }

        return implode("\n\n", $bloques);
    }

    /**
     * Últimos mensajes del hilo (sin el actual) para el agente N8N.
     *
     * @return array{wa_id: string, historial: list<array<string, mixed>>, historial_texto: string}
     */
    public function hiloParaTelefono(?string $telefono, ?int $excludeId = null, ?int $limite = null): array
    {
        $waId = $this->whatsapp->normalizePhone($telefono)
            ?? (preg_replace('/\D+/', '', (string) $telefono) ?: '');

        $vacio = [
            'wa_id' => $waId,
            'historial' => [],
            'historial_texto' => '',
        ];

        if ($waId === '') {
            return $vacio;
        }

        $limite = $limite ?? (int) config('whatsapp.agent.historial_limite', 12);
        $limite = max(0, min(40, $limite));
        if ($limite === 0) {
            return $vacio;
        }

        try {
            $query = WhatsappMensaje::query()->where('telefono', $waId);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if ((bool) config('whatsapp.agent.historial_solo_hoy', true)) {
                $query->where('created_at', '>=', now()->startOfDay());
            } else {
                $dias = max(0, (int) config('whatsapp.agent.historial_dias', 1));
                if ($dias > 0) {
                    $query->where('created_at', '>=', now()->subDays($dias));
                }
            }

            $mensajes = $query
                ->orderByDesc('id')
                ->limit($limite)
                ->get()
                ->reverse()
                ->values();
        } catch (\Throwable) {
            return $vacio;
        }

        $historial = [];
        foreach ($mensajes as $m) {
            $item = self::serializarItemHistorial($m);
            if ($item !== null) {
                $historial[] = $item;
            }
        }

        return [
            'wa_id' => $waId,
            'historial' => $historial,
            'historial_texto' => self::historialComoTexto($historial),
        ];
    }

    /**
     * @return array{rol: string, texto: string, tipo: string, at: string|null, origen: string|null}|null
     */
    public static function serializarItemHistorial(WhatsappMensaje $mensaje): ?array
    {
        if (data_get($mensaje->payload, 'oculto_hilo')) {
            return null;
        }

        $texto = self::textoHistorial($mensaje);
        if ($texto === '') {
            return null;
        }

        return [
            'rol' => $mensaje->esSalida() ? 'asesor' : 'cliente',
            'texto' => $texto,
            'tipo' => (string) ($mensaje->tipo ?: 'text'),
            'at' => $mensaje->created_at?->toIso8601String(),
            'origen' => $mensaje->contexto_tipo ? (string) $mensaje->contexto_tipo : null,
        ];
    }

    /**
     * @param  list<array{rol?: string, texto?: string, at?: string|null}>  $historial
     */
    public static function historialComoTexto(array $historial): string
    {
        $lineas = [];
        foreach ($historial as $item) {
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $rol = ($item['rol'] ?? '') === 'asesor' ? 'Asesor' : 'Cliente';
            $at = isset($item['at']) ? trim((string) $item['at']) : '';
            $hora = '';
            if ($at !== '') {
                try {
                    $hora = \Carbon\Carbon::parse($at)->format('d/m H:i').' ';
                } catch (\Throwable) {
                    $hora = '';
                }
            }
            $lineas[] = $hora.$rol.': '.$texto;
        }

        $out = implode("\n", $lineas);

        return mb_strlen($out) > 1800 ? mb_substr($out, -1800) : $out;
    }

    public static function textoHistorial(WhatsappMensaje $mensaje): string
    {
        $cuerpo = trim((string) ($mensaje->cuerpo ?? ''));
        if ($cuerpo !== '') {
            return mb_substr($cuerpo, 0, 280);
        }

        $tipo = (string) ($mensaje->tipo ?: '');

        return match ($tipo) {
            'image' => '[captura]',
            'audio' => '[audio]',
            'video' => '[video]',
            'document' => '[documento]',
            'sticker' => '[sticker]',
            'location' => '[ubicacion]',
            'template' => $mensaje->template_name
                ? '[plantilla '.$mensaje->template_name.']'
                : '[plantilla]',
            '', 'text' => '',
            default => '['.$tipo.']',
        };
    }

    /**
     * Lunes a viernes, horario_desde–horario_hasta (APP_TIMEZONE).
     */
    public static function horarioLaboral(?\DateTimeInterface $ahora = null): bool
    {
        $tz = (string) config('app.timezone', 'America/Argentina/Buenos_Aires');
        $ahora = $ahora
            ? \Carbon\Carbon::parse($ahora)->timezone($tz)
            : now($tz);

        if ($ahora->isWeekend()) {
            return false;
        }

        $desde = self::normalizarHoraConfig((string) config('whatsapp.agent.horario_desde', '09:00'), '09:00');
        $hasta = self::normalizarHoraConfig((string) config('whatsapp.agent.horario_hasta', '18:00'), '18:00');
        $hm = $ahora->format('H:i');

        return $hm >= $desde && $hm <= $hasta;
    }

    /**
     * Foto de comprobante: imagen ahora, o imagen hoy + texto de pago.
     *
     * @param  list<array{tipo?: string, texto?: string}>  $historial
     */
    public static function detectarComprobante(string $tipoActual, ?string $cuerpoActual, array $historial = [], ?string $tipoImagen = null): bool
    {
        if ($tipoImagen === WhatsAppImagenClasificadorService::TIPO_MAPA) {
            return false;
        }
        if ($tipoImagen === WhatsAppImagenClasificadorService::TIPO_COMPROBANTE) {
            return true;
        }

        $caption = self::captionUtil((string) $cuerpoActual);
        $textoPago = self::textoParecePago($caption);
        $esImagenActual = $tipoActual === 'image';
        $hayImagen = $esImagenActual;

        foreach ($historial as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            $texto = (string) ($item['texto'] ?? '');
            if ($tipo === 'image' || str_contains($texto, '[captura') || str_contains($texto, '[imagen')) {
                $hayImagen = true;
            }
            if (self::textoParecePago(self::captionUtil($texto))) {
                $textoPago = true;
            }
        }

        if ($esImagenActual) {
            return $textoPago;
        }

        return $hayImagen && $textoPago;
    }

    /**
     * @param  list<array{tipo?: string, texto?: string}>  $historial
     */
    public static function detectarUbicacion(string $tipoActual, ?string $cuerpoActual, array $historial = [], ?string $tipoImagen = null): bool
    {
        if ($tipoImagen === WhatsAppImagenClasificadorService::TIPO_MAPA) {
            return true;
        }
        if ($tipoImagen === WhatsAppImagenClasificadorService::TIPO_COMPROBANTE) {
            return false;
        }

        $cuerpo = self::captionUtil((string) $cuerpoActual);
        if (self::textoPareceLinkMapa($cuerpo) || self::textoPareceUbicacion($cuerpo)) {
            return true;
        }

        $hayImagen = $tipoActual === 'image';
        $textos = [$cuerpo];
        foreach ($historial as $item) {
            $tipo = (string) ($item['tipo'] ?? '');
            $texto = (string) ($item['texto'] ?? '');
            $textos[] = $texto;
            if ($tipo === 'image' || str_contains($texto, '[captura') || str_contains(mb_strtolower($texto), 'captura de mapa')) {
                $hayImagen = true;
            }
            if (self::textoPareceLinkMapa($texto)) {
                return true;
            }
        }

        return $hayImagen && self::textoPareceUbicacion(implode("\n", $textos));
    }

    public static function textoPareceLinkMapa(?string $texto): bool
    {
        $t = trim((string) $texto);
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/share\.google|maps\.app\.goo\.gl|maps\.google|google\.[a-z.]+\/maps|goo\.gl\/maps|waze\.com/i',
            $t
        );
    }

    /** Cédula paraguaya: solo dígitos, 5 a 8 (no teléfono). */
    public static function textoPareceCedula(?string $texto): bool
    {
        $t = trim((string) $texto);

        return (bool) preg_match('/^\d{5,8}$/', $t);
    }

    public static function textoPareceUbicacion(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }
        if (self::textoPareceLinkMapa($t)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(camino|calle|avda\.?|avenida|ruta|km\s*\d|barrio|compa[nñ][ií]a|ubicaci[oó]n|maps|gps|esquina|malvinas|enviar ubic|coordenad|tres de mayo)/u',
            $t
        );
    }

    public static function captionUtil(?string $cuerpo): string
    {
        $t = trim((string) $cuerpo);
        if ($t === '') {
            return '';
        }
        if (in_array(mb_strtolower($t), ['imagen', 'image', '[captura]', '[imagen]', 'te envié una captura', 'te envie una captura', 'te envié una foto', 'te envie una foto'], true)) {
            return '';
        }

        return mb_substr($t, 0, 280);
    }

    public static function textoParecePago(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(pago|pague|pagué|pagamos|comprobante|transfer\w*|deposit\w*|recibo|ueno|tigo\s*money|boleta|abone|aboné|extracto|giros?)\b/u',
            $t
        );
    }

    public static function textoPareceDatosTransferencia(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }
        if (preg_match('/ya pag|pagu[eé]|te mand[eé].*comprobante|ac[aá] (est[aá] )?el comprobante/u', $t)) {
            return false;
        }

        return (bool) preg_match(
            '/datos (de |para )?transf|a qu[eé] cuenta|n[uú]mero de cuenta|\balias\b|ueno bank|c[oó]mo (hago |te )?(la )?transf|d[oó]nde (te )?pago|cuenta (para |de )?(pago|transf|ueno)|c[oó]mo (puedo )?pagar/u',
            $t
        );
    }

    public static function textoPareceBeneficiosPlanes(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/\bbeneficios?\b|est[aá]ndar y premi|aparte de la velocidad|tr[aá]fico prioritario|por qu[eé] (sale |cuesta )?m[aá]s|diferencia entre.*plan|qu[eé] incluye el (est[aá]ndar|premium)|planes m[aá]s elevados/u',
            $t
        );
    }

    public static function textoPareceCondicionesServicio(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/\bcondiciones?( del servicio)?\b|comodato|sin (contrato de )?permanencia|fecha de pago|del 1 al 5|cu[aá]ndo (se )?paga(n)? el (servicio|plan)|retiro de (los )?equipos/u',
            $t
        );
    }

    /**
     * @return array{beneficios: string, transferencia: string, condiciones: string}
     */
    public static function textosCanned(): array
    {
        $c = config('whatsapp.agent.canned', []);

        return [
            'beneficios' => trim((string) ($c['beneficios'] ?? '')),
            'transferencia' => trim((string) ($c['transferencia'] ?? '')),
            'condiciones' => trim((string) ($c['condiciones'] ?? '')),
        ];
    }

    /** Cancelar el servicio. No confundir con "bajar wifi" (instalar). */
    public static function textoPareceBajaServicio(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/dar(me|se|nos|te)?\s+de\s+baja|darme\s+de\s+baja|cancelar\s+(el\s+)?(servicio|contrato|plan|internet)|quiero\s+dar(me)?\s+de\s+baja|descontrat/u',
            $t
        );
    }

    /** Pedido de instalación. En PY "mandar a bajar" / "bajar wifi" = instalar. */
    public static function textoPareceInstalacion(?string $texto): bool
    {
        if (self::textoPareceBajaServicio($texto)) {
            return false;
        }

        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/mandar\s+(a\s+)?(bajar|instalar)|bajar\s+(wifi|wi-?fi|internet|fibra|antena)|instal(ar|en|an)|poner\s+(wifi|internet|fibra|antena)|quiero\s+(wifi|internet)|necesito\s+(wifi|internet)|contratar|nuevo\s+servicio|pasen\s+(a\s+)?instalar|me\s+instal|cablear|tirar\s+(el\s+)?cable/u',
            $t
        );
    }

    /** Menciona wifi/internet sin ser un reclamo de corte. */
    public static function textoPareceWifi(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }
        if (preg_match('/cort|ca[ií]d|lento|no anda|se me/u', $t)) {
            return false;
        }

        return (bool) preg_match('/\b(wifi|wi-?fi)\b/u', $t);
    }

    /** Un técnico / el panel ya confirmó cobertura en esta zona. */
    public static function textoPareceCoberturaAprobada(?string $texto): bool
    {
        $t = mb_strtolower(trim((string) $texto));
        if ($t === '') {
            return false;
        }

        return (bool) preg_match(
            '/ubicaci[oó]n\s+aprobada|cobertura\s+aprobada|\bya hay cobertura\b|^hay cobertura\b/u',
            $t
        );
    }

    /**
     * @param  list<array{texto?: string}>  $historial
     */
    public static function hiloTieneCoberturaAprobada(array $historial, ?string $cuerpoActual = null): bool
    {
        if (self::textoPareceCoberturaAprobada($cuerpoActual)) {
            return true;
        }
        foreach ($historial as $item) {
            if (self::textoPareceCoberturaAprobada($item['texto'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payloadActual
     */
    public function telefonoTieneCoberturaAprobada(
        string $telefono,
        ?int $excludeId = null,
        ?string $cuerpoActual = null,
        array $payloadActual = [],
    ): bool {
        if (data_get($payloadActual, 'cobertura_aprobada') || self::textoPareceCoberturaAprobada($cuerpoActual)) {
            return true;
        }

        $waId = $this->whatsapp->normalizePhone($telefono)
            ?? (preg_replace('/\D+/', '', $telefono) ?: '');
        if ($waId === '') {
            return false;
        }

        try {
            $query = WhatsappMensaje::query()->where('telefono', $waId);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            foreach ($query->orderByDesc('id')->limit(40)->get(['cuerpo', 'payload']) as $m) {
                if (data_get($m->payload, 'cobertura_aprobada') || self::textoPareceCoberturaAprobada($m->cuerpo)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    public static function nombreCortoSaludo(?string $nombre): string
    {
        $n = trim((string) $nombre);
        if ($n === '') {
            return '';
        }

        return explode(' ', preg_replace('/\s+/', ' ', $n) ?: $n)[0];
    }

    /**
     * Nadie de Infinity respondió aún a este número.
     */
    public function esPrimeraConversacion(?string $telefono, ?int $excludeId = null): bool
    {
        $waId = $this->whatsapp->normalizePhone($telefono)
            ?? (preg_replace('/\D+/', '', (string) $telefono) ?: '');
        if ($waId === '') {
            return true;
        }

        try {
            $query = WhatsappMensaje::query()
                ->where('telefono', $waId)
                ->where('direccion', WhatsappMensaje::DIRECCION_SALIDA)
                ->where(function ($q) {
                    $q->whereNull('contexto_tipo')
                        ->orWhere('contexto_tipo', '!=', self::CONTEXTO_TEST);
                });
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            return ! $query->exists();
        } catch (\Throwable) {
            return true;
        }
    }

    private static function normalizarHoraConfig(string $valor, string $fallback): string
    {
        $valor = trim($valor);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $valor, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $fallback;
    }

    public function yaProcesado(WhatsappMensaje $mensaje): bool
    {
        if (data_get($mensaje->payload, 'wa_agent.reply') || data_get($mensaje->payload, 'wa_agent.usada')) {
            return true;
        }

        return WhatsappMensaje::query()
            ->where('contexto_tipo', self::CONTEXTO)
            ->where('contexto_id', $mensaje->id)
            ->exists();
    }

    /**
     * @return array{mensaje_id:int,reply:string,escalate:bool,motivo_escalado:?string,error:?string}|null
     */
    public function sugerenciaPendientePara(string $telefono): ?array
    {
        $entrada = WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->orderByDesc('id')
            ->first();

        if (! $entrada) {
            return null;
        }

        $agent = data_get($entrada->payload, 'wa_agent');
        if (! is_array($agent) || ! empty($agent['usada']) || ! empty($agent['descartada'])) {
            return null;
        }

        $reply = trim((string) ($agent['reply'] ?? ''));
        if ($reply === '') {
            return null;
        }

        return [
            'mensaje_id' => (int) $entrada->id,
            'reply' => $reply,
            'escalate' => (bool) ($agent['escalate'] ?? false),
            'motivo_escalado' => isset($agent['motivo_escalado']) ? (string) $agent['motivo_escalado'] : null,
            'error' => isset($agent['error']) ? (string) $agent['error'] : null,
        ];
    }

    public function marcarSugerencia(string $telefono, string $estado): void
    {
        if (! in_array($estado, ['usada', 'descartada'], true)) {
            return;
        }

        $entrada = WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->where('direccion', WhatsappMensaje::DIRECCION_ENTRADA)
            ->orderByDesc('id')
            ->first();

        if (! $entrada || ! is_array(data_get($entrada->payload, 'wa_agent'))) {
            return;
        }

        $payload = is_array($entrada->payload) ? $entrada->payload : [];
        $payload['wa_agent'][$estado] = true;
        $entrada->payload = $payload;
        $entrada->save();
    }

    public function humanoAtendiendo(WhatsappMensaje $mensaje): bool
    {
        $horas = max(0, (int) config('whatsapp.agent.humano_silencio_horas', 2));
        if ($horas === 0) {
            return false;
        }

        return WhatsappMensaje::query()
            ->where('telefono', $mensaje->telefono)
            ->where('direccion', WhatsappMensaje::DIRECCION_SALIDA)
            ->whereIn('contexto_tipo', ['manual_panel', 'app_whatsapp'])
            ->where('created_at', '>=', now()->subHours($horas))
            ->exists();
    }

    /**
     * @return array{
     *   ok: bool,
     *   reply: null,
     *   escalate: bool,
     *   cliente_id: null,
     *   motivo_escalado: string,
     *   n8n_latency_ms: int,
     *   error: string,
     *   enviado: bool,
     *   ticket_id: null
     * }
     */
    private function resultadoVacio(string $error): array
    {
        return [
            'ok' => false,
            'reply' => null,
            'escalate' => false,
            'cliente_id' => null,
            'motivo_escalado' => null,
            'n8n_latency_ms' => 0,
            'error' => $error,
            'enviado' => false,
            'ticket_id' => null,
            'modo' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $consulta
     */
    private function auditarEntrada(
        WhatsappMensaje $mensaje,
        array $consulta,
        ?string $reply,
        bool $escalate,
        ?string $error,
        ?int $ticketId,
    ): void {
        $payload = is_array($mensaje->payload) ? $mensaje->payload : [];
        $payload['wa_agent'] = [
            'reply' => $reply,
            'escalate' => $escalate,
            'cliente_id' => $consulta['cliente_id'] ?? $mensaje->cliente_id,
            'motivo_escalado' => $consulta['motivo_escalado'] ?? null,
            'n8n_latency_ms' => $consulta['n8n_latency_ms'] ?? 0,
            'error' => $error,
            'ticket_id' => $ticketId,
            'usada' => false,
            'descartada' => false,
            'modo' => $consulta['modo'] ?? null,
        ];
        $mensaje->payload = $payload;
        $mensaje->save();
    }

    public static function esTelefonoTest(?string $telefono): bool
    {
        $d = preg_replace('/\D+/', '', (string) $telefono) ?: '';

        return str_starts_with($d, self::PREFIJO_TEST);
    }

    /** Número inventado del playground: siempre 595000 + 6 dígitos. */
    public static function telefonoSandbox(?string $telefono): string
    {
        $d = preg_replace('/\D+/', '', (string) $telefono) ?: '';
        if (str_starts_with($d, self::PREFIJO_TEST) && strlen($d) >= 12) {
            return substr($d, 0, 16);
        }
        $cola = $d === '' ? '000001' : substr($d, -6);

        return self::PREFIJO_TEST.str_pad($cola, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Simula un mensaje de cliente hacia n8n sin enviar a Meta ni crear tickets.
     *
     * @return array{
     *   ok: bool,
     *   telefono: string,
     *   error: string|null,
     *   agent: array<string, mixed>,
     *   flags: array<string, mixed>
     * }
     */
    public function simularMensajeCliente(string $telefono, string $cuerpo): array
    {
        $tel = self::telefonoSandbox($telefono);
        $cuerpo = trim($cuerpo);
        $vacioAgent = $this->resultadoVacio('mensaje_vacio');

        if ($cuerpo === '') {
            return [
                'ok' => false,
                'telefono' => $tel,
                'error' => 'mensaje_vacio',
                'agent' => $vacioAgent,
                'flags' => [],
            ];
        }

        $mensaje = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'telefono' => $tel,
            'contacto_nombre' => 'Test n8n',
            'tipo' => 'text',
            'cuerpo' => mb_substr($cuerpo, 0, 4096),
            'wamid' => 'test-n8n-in-'.uniqid('', true),
            'estado' => WhatsappMensaje::ESTADO_RECIBIDO,
            'contexto_tipo' => self::CONTEXTO_TEST,
            'payload' => [
                'timestamp' => time(),
                'origen' => 'test_n8n',
            ],
        ]);
        $mensaje->save();

        $hilo = $this->hiloParaTelefono($tel, (int) $mensaje->id);
        $flags = [
            'primera_vez' => $this->esPrimeraConversacion($tel, (int) $mensaje->id),
            'parece_cedula' => self::textoPareceCedula($cuerpo),
            'parece_mapa' => self::detectarUbicacion('text', $cuerpo, $hilo['historial']),
            'parece_comprobante' => self::detectarComprobante('text', $cuerpo, $hilo['historial']),
            'parece_instalacion' => self::textoPareceInstalacion($cuerpo) || (
                $this->esPrimeraConversacion($tel, (int) $mensaje->id) && self::textoPareceWifi($cuerpo)
            ),
            'parece_baja' => self::textoPareceBajaServicio($cuerpo),
            'parece_pago_texto' => self::textoParecePago($cuerpo) && ! self::textoPareceDatosTransferencia($cuerpo),
            'parece_datos_transferencia' => self::textoPareceDatosTransferencia($cuerpo),
            'parece_beneficios' => self::textoPareceBeneficiosPlanes($cuerpo),
            'parece_condiciones' => self::textoPareceCondicionesServicio($cuerpo),
            'cobertura_aprobada' => $this->telefonoTieneCoberturaAprobada($tel, (int) $mensaje->id, $cuerpo),
        ];

        $resultado = $this->procesar($mensaje, false, true);

        if (filled($resultado['reply'])) {
            WhatsappMensaje::query()->create([
                'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
                'telefono' => $tel,
                'contacto_nombre' => 'Test n8n',
                'tipo' => 'text',
                'cuerpo' => $resultado['reply'],
                'wamid' => 'test-n8n-out-'.uniqid('', true),
                'estado' => WhatsappMensaje::ESTADO_ENVIADO,
                'contexto_tipo' => self::CONTEXTO_TEST,
                'contexto_id' => $mensaje->id,
                'payload' => [
                    'origen' => 'test_n8n',
                    'no_meta' => true,
                    'modo' => $resultado['modo'] ?? null,
                    'escalate' => $resultado['escalate'] ?? false,
                ],
            ]);
        }

        return [
            'ok' => (bool) $resultado['ok'],
            'telefono' => $tel,
            'error' => $resultado['error'] ?? null,
            'agent' => $resultado,
            'flags' => $flags,
        ];
    }

    /**
     * Staff confirma cobertura: el agente sigue con planes (sin mensaje de cliente).
     *
     * @return array{
     *   ok: bool,
     *   telefono: string,
     *   error: string|null,
     *   agent: array<string, mixed>,
     *   flags: array<string, mixed>
     * }
     */
    public function simularUbicacionAprobada(string $telefono): array
    {
        $tel = self::telefonoSandbox($telefono);
        $mensaje = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'telefono' => $tel,
            'contacto_nombre' => 'Test n8n',
            'tipo' => 'text',
            'cuerpo' => 'cobertura aprobada',
            'wamid' => 'test-n8n-in-'.uniqid('', true),
            'estado' => WhatsappMensaje::ESTADO_RECIBIDO,
            'contexto_tipo' => self::CONTEXTO_TEST,
            'payload' => [
                'timestamp' => time(),
                'origen' => 'test_n8n',
                'cobertura_aprobada' => true,
                'oculto_hilo' => true,
            ],
        ]);
        $mensaje->save();

        $flags = [
            'primera_vez' => $this->esPrimeraConversacion($tel, (int) $mensaje->id),
            'cobertura_aprobada' => true,
        ];
        $resultado = $this->procesar($mensaje, false, true);

        if (filled($resultado['reply'])) {
            WhatsappMensaje::query()->create([
                'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
                'telefono' => $tel,
                'contacto_nombre' => 'Test n8n',
                'tipo' => 'text',
                'cuerpo' => $resultado['reply'],
                'wamid' => 'test-n8n-out-'.uniqid('', true),
                'estado' => WhatsappMensaje::ESTADO_ENVIADO,
                'contexto_tipo' => self::CONTEXTO_TEST,
                'contexto_id' => $mensaje->id,
                'payload' => [
                    'origen' => 'test_n8n',
                    'no_meta' => true,
                    'cobertura_aprobada' => true,
                    'modo' => $resultado['modo'] ?? null,
                    'escalate' => $resultado['escalate'] ?? false,
                ],
            ]);
        }

        return [
            'ok' => (bool) $resultado['ok'],
            'telefono' => $tel,
            'error' => $resultado['error'] ?? null,
            'agent' => $resultado,
            'flags' => $flags,
        ];
    }

    public function borrarHiloTest(string $telefono): int
    {
        $tel = self::telefonoSandbox($telefono);
        if (! self::esTelefonoTest($tel)) {
            return 0;
        }

        return WhatsappMensaje::query()->where('telefono', $tel)->delete();
    }

    /**
     * @return list<array{telefono: string, ultimo_at: string|null}>
     */
    public function telefonosTestRecientes(int $limite = 12): array
    {
        $filas = WhatsappMensaje::query()
            ->where('telefono', 'like', self::PREFIJO_TEST.'%')
            ->selectRaw('telefono, MAX(created_at) as ultimo_at, MAX(id) as ultimo_id')
            ->groupBy('telefono')
            ->orderByDesc('ultimo_id')
            ->limit(max(1, min(30, $limite)))
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $out[] = [
                'telefono' => (string) $fila->telefono,
                'ultimo_at' => $fila->ultimo_at ? (string) $fila->ultimo_at : null,
            ];
        }

        return $out;
    }
}
