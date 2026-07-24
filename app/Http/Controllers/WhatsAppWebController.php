<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\User;
use App\Models\WhatsappAsunto;
use App\Models\WhatsappContacto;
use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WhatsAppWebController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function index(): View
    {
        $ultimos = WhatsappMensaje::query()
            ->latest('id')
            ->limit(8)
            ->get();

        $conteos = [
            'hoy' => WhatsappMensaje::query()->whereDate('created_at', today())->count(),
            'salida' => WhatsappMensaje::query()->where('direccion', 'salida')->whereDate('created_at', today())->count(),
            'entrada' => WhatsappMensaje::query()->where('direccion', 'entrada')->whereDate('created_at', today())->count(),
            'fallidos' => WhatsappMensaje::query()->where('estado', 'fallido')->whereDate('created_at', '>=', now()->subDays(7))->count(),
        ];

        return view('whatsapp.index', [
            'configured' => $this->whatsapp->isConfigured(),
            'enabled' => (bool) config('whatsapp.enabled'),
            'phoneNumberId' => (string) config('whatsapp.phone_number_id'),
            'businessAccountId' => (string) config('whatsapp.business_account_id'),
            'apiVersion' => (string) config('whatsapp.api_version'),
            'events' => config('whatsapp.events', []),
            'templatesConfig' => config('whatsapp.templates', []),
            'plantillasMeta' => $this->whatsapp->listTemplates(),
            'ultimos' => $ultimos,
            'conteos' => $conteos,
            'puedeEditar' => auth()->user()?->tienePermiso('whatsapp.editar') ?? false,
        ]);
    }

    public function mensajes(Request $request): View
    {
        $tel = $this->normalizeTel($request->get('tel'));

        return view('whatsapp.mensajes', [
            'telInicial' => $tel,
            'buscarInicial' => trim((string) $request->get('buscar', '')),
            'configured' => $this->whatsapp->isConfigured(),
            'puedeEditar' => auth()->user()?->tienePermiso('whatsapp.editar') ?? false,
            'urls' => [
                'conversaciones' => route('whatsapp.conversaciones'),
                'hilo' => route('whatsapp.hilo'),
                'marcarLeidos' => route('whatsapp.marcar-leidos'),
                'asignarAsunto' => route('whatsapp.asignar-asunto'),
                'asuntos' => route('whatsapp.asuntos.json'),
                'enviar' => route('whatsapp.enviar.store'),
                'reintentarTpl' => url('/whatsapp/mensajes/__ID__/reintentar'),
                'enviarPlantilla' => route('whatsapp.enviar'),
                'mediaTpl' => url('/whatsapp/mensajes/__ID__/media'),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function conversacionesJson(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->get('buscar', ''));
        $asuntoId = $request->filled('asunto_id') ? (int) $request->get('asunto_id') : null;

        return response()->json([
            'conversaciones' => $this->buildConversaciones($buscar, $asuntoId)->values(),
            'ahora' => now()->toIso8601String(),
        ]);
    }

    public function asuntosJson(): JsonResponse
    {
        $asuntos = WhatsappAsunto::query()
            ->activos()
            ->get(['id', 'nombre', 'color', 'orden'])
            ->map(fn (WhatsappAsunto $a) => [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'color' => $a->color,
            ]);

        return response()->json(['asuntos' => $asuntos]);
    }

    public function asignarAsunto(Request $request): JsonResponse
    {
        $tel = $this->normalizeTel($request->input('telefono'));
        if (! $tel) {
            return response()->json(['ok' => false, 'error' => 'Teléfono requerido'], 422);
        }

        $asuntoId = $request->input('whatsapp_asunto_id');
        if ($asuntoId === '' || $asuntoId === null) {
            $asuntoId = null;
        } else {
            $asuntoId = (int) $asuntoId;
            if (! WhatsappAsunto::query()->whereKey($asuntoId)->where('activo', true)->exists()) {
                return response()->json(['ok' => false, 'error' => 'Asunto inválido'], 422);
            }
        }

        $cliente = $this->whatsapp->findClienteByPhone($tel);
        $contacto = WhatsappContacto::query()->firstOrNew(['telefono' => $tel]);
        $contacto->whatsapp_asunto_id = $asuntoId;
        if ($cliente) {
            $contacto->cliente_id = $cliente->cliente_id;
        }
        $contacto->ultimo_visto_at = $contacto->ultimo_visto_at ?: now();
        $contacto->save();
        $contacto->load('asunto:id,nombre,color');

        return response()->json([
            'ok' => true,
            'asunto' => $contacto->asunto
                ? [
                    'id' => $contacto->asunto->id,
                    'nombre' => $contacto->asunto->nombre,
                    'color' => $contacto->asunto->color,
                ]
                : null,
        ]);
    }

    public function hiloJson(Request $request): JsonResponse
    {
        $tel = $this->normalizeTel($request->get('tel'));
        if (! $tel) {
            return response()->json(['error' => 'Teléfono requerido'], 422);
        }

        $afterId = (int) $request->get('after_id', 0);
        $updatedAfterRaw = trim((string) $request->get('updated_after', ''));
        $updatedAfter = null;
        if ($updatedAfterRaw !== '') {
            try {
                $updatedAfter = \Carbon\Carbon::parse($updatedAfterRaw);
            } catch (\Throwable) {
                $updatedAfter = null;
            }
        }

        $incremental = $afterId > 0 || $updatedAfter !== null;

        $query = WhatsappMensaje::query()->where('telefono', $tel);

        if ($incremental) {
            $query->where(function ($q) use ($afterId, $updatedAfter) {
                if ($afterId > 0) {
                    $q->where('id', '>', $afterId);
                }
                if ($updatedAfter) {
                    $method = $afterId > 0 ? 'orWhere' : 'where';
                    $q->{$method}(function ($inner) use ($updatedAfter, $afterId) {
                        $inner->where('updated_at', '>', $updatedAfter);
                        if ($afterId > 0) {
                            // Evitar duplicar los recién creados (ya vienen por after_id).
                            $inner->where('id', '<=', $afterId);
                        }
                    });
                }
            });
        }

        $hilo = $query
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(fn (WhatsappMensaje $m) => $this->serializeMensaje($m));

        $contacto = WhatsappContacto::query()
            ->with(['cliente:cliente_id,nombre,apellido', 'asunto:id,nombre,color'])
            ->where('telefono', $tel)
            ->first();

        $ultimaEntrada = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', 'entrada')
            ->latest('id')
            ->first();

        $fueraVentana = ! $ultimaEntrada
            || ($ultimaEntrada->created_at && $ultimaEntrada->created_at->lt(now()->subHours(24)));

        $fallidos = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', 'salida')
            ->where('estado', 'fallido')
            ->count();

        $sinLeer = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', 'entrada')
            ->where('estado', '!=', WhatsappMensaje::ESTADO_LEIDO)
            ->count();

        $total = WhatsappMensaje::query()->where('telefono', $tel)->count();

        $maxId = (int) (WhatsappMensaje::query()->where('telefono', $tel)->max('id') ?: 0);
        $serverNow = now()->toIso8601String();
        $clasif = $this->clasificarTelefonos([$tel])->get($tel, [
            'tipo' => null,
            'label' => null,
            'color' => null,
        ]);

        return response()->json([
            'telefono' => $tel,
            'nombre' => $contacto?->nombre,
            'cliente_id' => $contacto?->cliente_id,
            'cliente_nombre' => $contacto?->cliente
                ? trim(($contacto->cliente->nombre ?? '').' '.($contacto->cliente->apellido ?? ''))
                : null,
            'asunto' => $contacto?->asunto
                ? [
                    'id' => $contacto->asunto->id,
                    'nombre' => $contacto->asunto->nombre,
                    'color' => $contacto->asunto->color,
                ]
                : null,
            'clasificacion' => $clasif['tipo'],
            'clasificacion_label' => $clasif['label'],
            'clasificacion_color' => $clasif['color'],
            'fuera_ventana' => $fueraVentana,
            'total' => $total,
            'fallidos' => $fallidos,
            'sin_leer' => $sinLeer,
            'mensajes' => $hilo->values(),
            'ultimo_id' => max($afterId, $maxId),
            'server_now' => $serverNow,
            'incremental' => $incremental,
        ]);
    }

    public function marcarLeidos(Request $request): JsonResponse
    {
        $tel = $this->normalizeTel($request->input('telefono') ?: $request->input('tel'));
        if (! $tel) {
            return response()->json(['ok' => false, 'error' => 'Teléfono requerido'], 422);
        }

        $result = $this->whatsapp->marcarConversacionLeida($tel);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function media(WhatsappMensaje $mensaje)
    {
        $mensaje = $this->whatsapp->adjuntarMediaLocal($mensaje);
        $path = $this->whatsapp->rutaMediaLocal($mensaje);
        if (! $path) {
            abort(404, 'Media no disponible (Meta lo borra a los ~7 días si no se descargó a tiempo).');
        }

        $mime = (string) (data_get($mensaje->payload, '_local.mime') ?: 'application/octet-stream');
        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="wa-'.$mensaje->id.'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function contactos(Request $request): View
    {
        $q = WhatsappContacto::query()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderByDesc('ultimo_visto_at');

        if ($buscar = trim((string) $request->get('buscar', ''))) {
            $q->where(function ($inner) use ($buscar) {
                $inner->where('telefono', 'like', '%'.$buscar.'%')
                    ->orWhere('nombre', 'like', '%'.$buscar.'%');
                if (ctype_digit($buscar)) {
                    $inner->orWhere('cliente_id', (int) $buscar);
                }
            });
        }

        return view('whatsapp.contactos', [
            'contactos' => $q->paginate(30)->withQueryString(),
        ]);
    }

    public function enviarForm(Request $request): View
    {
        $plantillas = $this->whatsapp->listTemplates();
        $aprobadas = array_values(array_filter(
            $plantillas,
            static fn (array $t) => strtoupper($t['status'] ?? '') === 'APPROVED'
        ));
        $pendientes = array_values(array_filter(
            $plantillas,
            static fn (array $t) => strtoupper($t['status'] ?? '') !== 'APPROVED'
        ));

        return view('whatsapp.enviar', [
            'plantillasMeta' => $plantillas,
            'plantillasAprobadas' => $aprobadas,
            'plantillasPendientes' => $pendientes,
            'defaultLang' => (string) config('whatsapp.default_template_language', 'es'),
            'configured' => $this->whatsapp->isConfigured(),
            'telefonoPrefill' => (string) $request->get('telefono', ''),
        ]);
    }

    public function enviar(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if (! $this->whatsapp->isConfigured()) {
            return $wantsJson
                ? response()->json(['ok' => false, 'error' => 'WhatsApp no está configurado.'], 422)
                : back()->withInput()->with('error', 'WhatsApp no está configurado.');
        }

        $validated = $request->validate([
            'telefono' => ['required', 'string', 'max:40'],
            'modo' => ['required', 'in:texto,plantilla'],
            'texto' => ['nullable', 'string', 'max:4000'],
            'plantilla' => ['nullable', 'string', 'max:120'],
            'lang' => ['nullable', 'string', 'max:10'],
            'params' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $mensaje = $this->dispatchEnvio($validated);
        } catch (\InvalidArgumentException $e) {
            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $e->getMessage()], 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        if ($mensaje->estado === WhatsappMensaje::ESTADO_FALLIDO) {
            $msg = 'Falló el envío: '.($mensaje->error_message ?: 'error Meta');

            return $wantsJson
                ? response()->json([
                    'ok' => false,
                    'error' => $msg,
                    'mensaje' => $this->serializeMensaje($mensaje),
                ], 422)
                : redirect()->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])->with('error', $msg);
        }

        return $wantsJson
            ? response()->json(['ok' => true, 'mensaje' => $this->serializeMensaje($mensaje)])
            : redirect()
                ->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])
                ->with('success', 'Mensaje #'.$mensaje->id.' enviado (estado: '.$mensaje->estado.').');
    }

    public function reintentar(Request $request, WhatsappMensaje $mensaje)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $nuevo = $this->whatsapp->reintentar($mensaje);
        } catch (\InvalidArgumentException $e) {
            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $e->getMessage()], 422)
                : redirect()->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $msg = 'No se pudo reintentar: '.$e->getMessage();

            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $msg], 500)
                : redirect()->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])->with('error', $msg);
        }

        if ($nuevo->estado === WhatsappMensaje::ESTADO_FALLIDO) {
            $msg = 'Reintento #'.$nuevo->id.' falló: '.($nuevo->error_message ?: 'error Meta');

            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $msg, 'mensaje' => $this->serializeMensaje($nuevo)], 422)
                : redirect()->route('whatsapp.mensajes', ['tel' => $nuevo->telefono])->with('error', $msg);
        }

        return $wantsJson
            ? response()->json(['ok' => true, 'mensaje' => $this->serializeMensaje($nuevo)])
            : redirect()
                ->route('whatsapp.mensajes', ['tel' => $nuevo->telefono])
                ->with('success', 'Reenviado (#'.$mensaje->id.' → #'.$nuevo->id.'), estado: '.$nuevo->estado.'.');
    }

    /**
     * @param  array{telefono:string,modo:string,texto?:string|null,plantilla?:string|null,lang?:string|null,params?:string|null}  $validated
     */
    private function dispatchEnvio(array $validated): WhatsappMensaje
    {
        if ($validated['modo'] === 'texto') {
            if (! filled($validated['texto'] ?? null)) {
                throw new \InvalidArgumentException('Escribí el mensaje de texto.');
            }

            return $this->whatsapp->sendText(
                $validated['telefono'],
                (string) $validated['texto'],
                ['contexto_tipo' => 'manual_panel']
            );
        }

        if (! filled($validated['plantilla'] ?? null)) {
            throw new \InvalidArgumentException('Indicá el nombre de la plantilla.');
        }

        $tplName = (string) $validated['plantilla'];
        $tplLang = $validated['lang'] ?: (string) config('whatsapp.default_template_language', 'es');
        $aprobada = collect($this->whatsapp->listTemplates())->first(function (array $t) use ($tplName, $tplLang) {
            return ($t['name'] ?? '') === $tplName
                && strtoupper($t['status'] ?? '') === 'APPROVED'
                && (
                    empty($t['language'])
                    || strtolower((string) $t['language']) === strtolower($tplLang)
                    || str_starts_with(strtolower((string) $t['language']), strtolower($tplLang))
                );
        });

        if (! $aprobada) {
            throw new \InvalidArgumentException(
                "La plantilla «{$tplName}» ({$tplLang}) no está APPROVED en Meta. Mientras esté PENDING no se puede enviar fuera de ventana 24h."
            );
        }

        $paramsRaw = trim((string) ($validated['params'] ?? ''));
        $params = $paramsRaw === ''
            ? []
            : array_map(
                static fn (string $p) => ['type' => 'text', 'text' => trim($p)],
                preg_split('/\r\n|\r|\n/', $paramsRaw) ?: []
            );
        $params = array_values(array_filter($params, static fn ($p) => ($p['text'] ?? '') !== ''));

        return $this->whatsapp->sendTemplate(
            $validated['telefono'],
            $tplName,
            $tplLang,
            $params,
            ['contexto_tipo' => 'manual_panel']
        );
    }

    private function normalizeTel(mixed $tel): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $tel) ?: null;
        if (! $digits) {
            return null;
        }

        return $this->whatsapp->normalizePhone($digits) ?? $digits;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildConversaciones(string $buscar = '', ?int $asuntoId = null): Collection
    {
        $conversacionesQuery = WhatsappMensaje::query()
            ->selectRaw("telefono, MAX(id) as ultimo_id, COUNT(*) as total, SUM(CASE WHEN direccion = 'salida' AND estado = 'fallido' THEN 1 ELSE 0 END) as fallidos, SUM(CASE WHEN direccion = 'entrada' AND estado != 'leido' THEN 1 ELSE 0 END) as sin_leer")
            ->groupBy('telefono');

        if ($buscar !== '') {
            $conversacionesQuery->where(function ($inner) use ($buscar) {
                $inner->where('telefono', 'like', '%'.$buscar.'%')
                    ->orWhere('cuerpo', 'like', '%'.$buscar.'%')
                    ->orWhere('contacto_nombre', 'like', '%'.$buscar.'%');
                if (ctype_digit($buscar)) {
                    $inner->orWhere('cliente_id', (int) $buscar);
                }
            });
        }

        if ($asuntoId !== null) {
            if ($asuntoId === 0) {
                // Sin asunto
                $telsSin = WhatsappContacto::query()
                    ->whereNull('whatsapp_asunto_id')
                    ->pluck('telefono')
                    ->all();
                $telsConMensajeSinContacto = WhatsappMensaje::query()
                    ->whereNotIn('telefono', WhatsappContacto::query()->select('telefono'))
                    ->distinct()
                    ->pluck('telefono')
                    ->all();
                $conversacionesQuery->whereIn('telefono', array_values(array_unique(array_merge($telsSin, $telsConMensajeSinContacto))));
            } else {
                $tels = WhatsappContacto::query()
                    ->where('whatsapp_asunto_id', $asuntoId)
                    ->pluck('telefono')
                    ->all();
                $conversacionesQuery->whereIn('telefono', $tels === [] ? ['__none__'] : $tels);
            }
        }

        $agg = $conversacionesQuery->orderByDesc('ultimo_id')->limit(80)->get();
        $ultimosIds = $agg->pluck('ultimo_id')->all();
        $ultimosMsgs = $ultimosIds === []
            ? collect()
            : WhatsappMensaje::query()->whereIn('id', $ultimosIds)->get()->keyBy('id');

        $telefonos = $agg->pluck('telefono')->all();
        $contactos = $telefonos === []
            ? collect()
            : WhatsappContacto::query()
                ->with(['cliente:cliente_id,nombre,apellido', 'asunto:id,nombre,color'])
                ->whereIn('telefono', $telefonos)
                ->get()
                ->keyBy('telefono');

        $clasificaciones = $this->clasificarTelefonos($telefonos);

        return $agg->map(function ($row) use ($ultimosMsgs, $contactos, $clasificaciones) {
            $ultimo = $ultimosMsgs->get($row->ultimo_id);
            $contacto = $contactos->get($row->telefono);
            $nombre = $contacto?->nombre ?: $ultimo?->contacto_nombre;
            $clasif = $clasificaciones->get($row->telefono, [
                'tipo' => null,
                'label' => null,
                'color' => null,
            ]);

            return [
                'telefono' => $row->telefono,
                'total' => (int) $row->total,
                'fallidos' => (int) ($row->fallidos ?? 0),
                'sin_leer' => (int) ($row->sin_leer ?? 0),
                'nombre' => $nombre,
                'cliente_id' => $contacto?->cliente_id ?: $ultimo?->cliente_id,
                'cliente_nombre' => $contacto?->cliente
                    ? trim(($contacto->cliente->nombre ?? '').' '.($contacto->cliente->apellido ?? ''))
                    : null,
                'asunto' => $contacto?->asunto
                    ? [
                        'id' => $contacto->asunto->id,
                        'nombre' => $contacto->asunto->nombre,
                        'color' => $contacto->asunto->color,
                    ]
                    : null,
                'clasificacion' => $clasif['tipo'],
                'clasificacion_label' => $clasif['label'],
                'clasificacion_color' => $clasif['color'],
                'ultimo_id' => $ultimo?->id,
                'ultimo_cuerpo' => $ultimo?->cuerpo ?: $ultimo?->template_name,
                'ultimo_direccion' => $ultimo?->direccion,
                'ultimo_estado' => $ultimo?->estado,
                'ultimo_at' => $ultimo?->created_at?->toIso8601String(),
                'ultimo_at_label' => $ultimo?->created_at?->format('d/m H:i'),
            ];
        });
    }

    /**
     * Clasifica teléfonos: staff > pedido (pendiente) > cliente.
     *
     * @param  list<string|null>  $telefonos
     * @return Collection<string, array{tipo:?string,label:?string,color:?string}>
     */
    private function clasificarTelefonos(array $telefonos): Collection
    {
        $telefonos = array_values(array_unique(array_filter(array_map(
            fn ($t) => $this->normalizeTel($t),
            $telefonos
        ))));

        $out = collect();
        foreach ($telefonos as $tel) {
            $out[$tel] = ['tipo' => null, 'label' => null, 'color' => null];
        }
        if ($telefonos === []) {
            return $out;
        }

        $staffSet = $this->telefonosStaffNormalizados();
        $clientePorTel = [];
        foreach ($telefonos as $tel) {
            $cliente = $this->whatsapp->findClienteByPhone($tel);
            if ($cliente) {
                $clientePorTel[$tel] = (int) $cliente->cliente_id;
            }
        }

        // Contactos ya vinculados
        $contactos = WhatsappContacto::query()
            ->whereIn('telefono', $telefonos)
            ->whereNotNull('cliente_id')
            ->pluck('cliente_id', 'telefono');
        foreach ($contactos as $tel => $clienteId) {
            $clientePorTel[$tel] = (int) $clienteId;
        }

        $clienteIds = array_values(array_unique(array_filter($clientePorTel)));
        $conPedidoPendiente = $clienteIds === []
            ? []
            : Pedido::query()
                ->whereIn('cliente_id', $clienteIds)
                ->where('estado_instalado', false)
                ->pluck('cliente_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
        $pedidoSet = array_fill_keys($conPedidoPendiente, true);

        foreach ($telefonos as $tel) {
            if (isset($staffSet[$tel])) {
                $out[$tel] = [
                    'tipo' => 'staff',
                    'label' => 'Staff',
                    'color' => '#3b82f6',
                ];

                continue;
            }

            $clienteId = $clientePorTel[$tel] ?? null;
            if ($clienteId && isset($pedidoSet[$clienteId])) {
                $out[$tel] = [
                    'tipo' => 'pedido',
                    'label' => 'Pedido pendiente',
                    'color' => '#f59e0b',
                ];

                continue;
            }

            if ($clienteId) {
                $out[$tel] = [
                    'tipo' => 'cliente',
                    'label' => 'Cliente',
                    'color' => '#10b981',
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, true> telefono normalizado => true
     */
    private function telefonosStaffNormalizados(): array
    {
        $set = [];

        $users = User::query()
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->get(['usuario_id', 'telefono']);

        foreach ($users as $user) {
            $n = $this->normalizeTel($user->telefono);
            if ($n) {
                $set[$n] = true;
            }
        }

        $map = (string) config('whatsapp.staff_phones', '');
        if ($map !== '') {
            foreach (explode(',', $map) as $pair) {
                $parts = array_map('trim', explode(':', $pair, 2));
                $phone = count($parts) === 2 ? $parts[1] : $parts[0];
                $n = $this->normalizeTel($phone);
                if ($n) {
                    $set[$n] = true;
                }
            }
        }

        return $set;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMensaje(WhatsappMensaje $m): array
    {
        $fallo = $m->esFallido() ? $m->detalleFallo() : null;
        $tieneMedia = filled(data_get($m->payload, '_local.path'))
            || in_array($m->tipo, ['audio', 'image', 'video', 'document', 'sticker'], true);
        $ubicacion = $this->datosUbicacion($m);

        return [
            'id' => $m->id,
            'direccion' => $m->direccion,
            'telefono' => $m->telefono,
            'contacto_nombre' => $m->contacto_nombre,
            'tipo' => $m->tipo,
            'cuerpo' => $m->cuerpo,
            'template_name' => $m->template_name,
            'template_language' => $m->template_language,
            'estado' => $m->estado,
            'error_code' => $m->error_code,
            'error_message' => $m->error_message,
            'contexto_tipo' => $m->contexto_tipo,
            'wamid' => $m->wamid,
            'created_at' => $m->created_at?->toIso8601String(),
            'updated_at' => $m->updated_at?->toIso8601String(),
            'hora' => $m->created_at?->format('H:i'),
            'dia' => $m->created_at?->format('Y-m-d'),
            'dia_label' => $m->created_at?->translatedFormat('d M Y'),
            'fallo' => $fallo,
            'media_url' => $tieneMedia ? url('/whatsapp/mensajes/'.$m->id.'/media') : null,
            'media_mime' => data_get($m->payload, '_local.mime'),
            'media_voice' => (bool) data_get($m->payload, '_local.voice', data_get($m->payload, 'audio.voice', false)),
            'media_ready' => filled(data_get($m->payload, '_local.path')),
            'maps_url' => $ubicacion['url'] ?? null,
            'maps_lat' => $ubicacion['lat'] ?? null,
            'maps_lng' => $ubicacion['lng'] ?? null,
            'maps_nombre' => $ubicacion['nombre'] ?? null,
            'maps_direccion' => $ubicacion['direccion'] ?? null,
        ];
    }

    /**
     * @return array{lat:?float,lng:?float,nombre:?string,direccion:?string,url:?string}
     */
    private function datosUbicacion(WhatsappMensaje $m): array
    {
        $empty = ['lat' => null, 'lng' => null, 'nombre' => null, 'direccion' => null, 'url' => null];

        $lat = data_get($m->payload, 'location.latitude');
        $lng = data_get($m->payload, 'location.longitude');
        $nombre = trim((string) data_get($m->payload, 'location.name', '')) ?: null;
        $direccion = trim((string) data_get($m->payload, 'location.address', '')) ?: null;
        $urlMeta = trim((string) data_get($m->payload, 'location.url', '')) ?: null;

        // Mensajes viejos: cuerpo "lat,lng" o "lat, lng"
        if (($lat === null || $lng === null) && is_string($m->cuerpo) && preg_match(
            '/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/',
            $m->cuerpo,
            $match
        )) {
            $lat = $match[1];
            $lng = $match[2];
        }

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return $empty;
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;
        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            return $empty;
        }

        $url = $urlMeta;
        if (! $url || ! str_starts_with($url, 'http')) {
            $url = 'https://www.google.com/maps?q='.rawurlencode($latF.','.$lngF);
        }

        return [
            'lat' => $latF,
            'lng' => $lngF,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'url' => $url,
        ];
    }
}
