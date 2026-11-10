<?php

namespace App\Services;

use App\Events\AccionChatAtendida;
use App\Events\MensajeChatCreado;
use App\Exceptions\ChatInternoException;
use App\Models\ChatAdjunto;
use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Models\ChatMensajeAccion;
use App\Models\ChatParticipante;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatInternoService
{
    public function __construct(private readonly FcmPushService $fcm) {}

    /**
     * @return array{conversaciones: list<array<string, mixed>>}
     */
    public function listarConversaciones(User $user): array
    {
        $this->asegurarStaff($user);

        $conversaciones = ChatConversacion::query()
            ->whereHas('participantes', fn ($q) => $q->where('usuario_id', $user->usuario_id))
            ->with([
                'participantes.usuario:usuario_id,name',
                'ultimoMensaje.adjunto',
            ])
            ->get()
            ->sortByDesc(fn (ChatConversacion $c) => $c->ultimoMensaje?->id ?? 0)
            ->values();

        $noLeidos = $this->noLeidosPorConversacion($user, $conversaciones->pluck('id')->all());

        return [
            'conversaciones' => $conversaciones->map(function (ChatConversacion $conversacion) use ($user, $noLeidos) {
                return $this->serializarConversacion($conversacion, $user, (int) ($noLeidos[$conversacion->id] ?? 0));
            })->all(),
        ];
    }

    /**
     * @return array{usuarios: list<array<string, mixed>>}
     */
    public function listarUsuarios(User $user): array
    {
        $this->asegurarStaff($user);

        $usuarios = User::query()
            ->staff()
            ->activos()
            ->where('usuario_id', '!=', $user->usuario_id)
            ->orderBy('name')
            ->get(['usuario_id', 'name', 'cargo']);

        return [
            'usuarios' => $usuarios->map(fn (User $otro) => [
                'usuario_id' => (int) $otro->usuario_id,
                'name' => $otro->name,
                'cargo' => $otro->cargo,
            ])->all(),
        ];
    }

    /**
     * @return array{conversacion: array<string, mixed>, creada: bool}
     */
    public function abrirDirecto(User $autor, int $otroId): array
    {
        $this->asegurarStaff($autor);
        if ($otroId === (int) $autor->usuario_id) {
            throw new ChatInternoException('No puedes escribirte a ti mismo.');
        }
        $otro = $this->staffActivo($otroId);
        $clave = $this->claveDirecto((int) $autor->usuario_id, (int) $otro->usuario_id);

        $creada = false;
        $conversacion = DB::transaction(function () use ($autor, $otro, $clave, &$creada) {
            $existente = ChatConversacion::query()->where('directo_clave', $clave)->lockForUpdate()->first();
            if ($existente) {
                return $existente;
            }

            $creada = true;
            $nueva = ChatConversacion::query()->create([
                'tipo' => 'directo',
                'titulo' => null,
                'creado_por' => $autor->usuario_id,
                'directo_clave' => $clave,
            ]);
            $this->agregarParticipantes($nueva, [(int) $autor->usuario_id, (int) $otro->usuario_id]);

            return $nueva;
        });

        $conversacion->load(['participantes.usuario:usuario_id,name', 'ultimoMensaje.adjunto']);

        return [
            'conversacion' => $this->serializarConversacion($conversacion, $autor, 0),
            'creada' => $creada,
        ];
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return array{conversacion: array<string, mixed>}
     */
    public function crearGrupo(User $autor, string $titulo, array $usuarioIds): array
    {
        $this->asegurarStaff($autor);
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new ChatInternoException('El grupo necesita un nombre.');
        }

        $ids = collect($usuarioIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $autor->usuario_id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new ChatInternoException('Elige al menos un integrante.');
        }

        foreach ($ids as $id) {
            $this->staffActivo($id);
        }

        $conversacion = DB::transaction(function () use ($autor, $titulo, $ids) {
            $nueva = ChatConversacion::query()->create([
                'tipo' => 'grupo',
                'titulo' => Str::limit($titulo, 120, ''),
                'creado_por' => $autor->usuario_id,
                'directo_clave' => null,
            ]);
            $this->agregarParticipantes($nueva, $ids->prepend((int) $autor->usuario_id)->all());

            return $nueva;
        });

        $conversacion->load(['participantes.usuario:usuario_id,name', 'ultimoMensaje.adjunto']);

        return [
            'conversacion' => $this->serializarConversacion($conversacion, $autor, 0),
        ];
    }

    /**
     * @return array{conversacion: array<string, mixed>, mensajes: list<array<string, mixed>>}
     */
    public function mensajes(User $user, int $conversacionId, ?int $despuesDe = null): array
    {
        $conversacion = $this->conversacionDe($user, $conversacionId);
        $consulta = $conversacion->mensajes()->with(['autor:usuario_id,name', 'adjunto', 'acciones']);
        if ($despuesDe) {
            $consulta->where('id', '>', $despuesDe)->orderBy('id');
        } else {
            $ids = (clone $consulta)->orderByDesc('id')->limit(80)->pluck('id');
            $consulta->whereIn('id', $ids)->orderBy('id');
        }

        $mensajes = $consulta->get();
        $ultimo = ChatMensaje::query()->where('conversacion_id', $conversacion->id)->max('id');
        if ($ultimo) {
            ChatParticipante::query()
                ->where('conversacion_id', $conversacion->id)
                ->where('usuario_id', $user->usuario_id)
                ->update(['ultimo_leido_mensaje_id' => $ultimo]);
        }

        $conversacion->load(['participantes.usuario:usuario_id,name', 'ultimoMensaje.adjunto']);

        return [
            'conversacion' => $this->serializarConversacion($conversacion, $user, 0),
            'mensajes' => $mensajes->map(fn (ChatMensaje $mensaje) => $this->serializarMensaje($mensaje, $conversacion))->all(),
        ];
    }

    /**
     * @param  list<int>  $asignadosIds
     * @return array{mensaje: array<string, mixed>}
     */
    public function enviar(
        User $autor,
        int $conversacionId,
        ?string $cuerpo,
        ?UploadedFile $archivo,
        bool $requiereAccion,
        array $asignadosIds,
    ): array {
        $conversacion = $this->conversacionDe($autor, $conversacionId);
        $texto = trim((string) $cuerpo);
        if ($texto === '' && $archivo === null) {
            throw new ChatInternoException('Escribe un mensaje o adjunta un archivo.');
        }
        if ($archivo !== null) {
            $this->validarArchivo($archivo);
        }

        $otros = $conversacion->participantes
            ->pluck('usuario_id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $autor->usuario_id)
            ->values();

        $asignados = [];
        if ($requiereAccion) {
            if ($conversacion->tipo === 'directo') {
                $asignados = $otros->all();
            } else {
                $pedidos = collect($asignadosIds)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->filter(fn (int $id) => $otros->contains($id))
                    ->values();
                $asignados = $pedidos->isEmpty() ? $otros->all() : $pedidos->all();
            }
            if ($asignados === []) {
                throw new ChatInternoException('No hay destinatarios para la acción.');
            }
        }

        $mensaje = DB::transaction(function () use ($autor, $conversacion, $texto, $archivo, $asignados) {
            $nuevo = ChatMensaje::query()->create([
                'conversacion_id' => $conversacion->id,
                'usuario_id' => $autor->usuario_id,
                'cuerpo' => $texto !== '' ? $texto : null,
            ]);

            if ($archivo !== null) {
                $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension() ?: 'bin');
                $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
                $ruta = $archivo->storeAs(
                    'chat/'.$conversacion->id,
                    (string) Str::uuid().'.'.$extension,
                    'local'
                );
                ChatAdjunto::query()->create([
                    'mensaje_id' => $nuevo->id,
                    'disco' => 'local',
                    'ruta' => $ruta,
                    'nombre_original' => $this->nombreSeguro($archivo->getClientOriginalName()),
                    'mime' => (string) ($archivo->getMimeType() ?: 'application/octet-stream'),
                    'tamano' => (int) $archivo->getSize(),
                ]);
            }

            foreach ($asignados as $usuarioId) {
                ChatMensajeAccion::query()->create([
                    'mensaje_id' => $nuevo->id,
                    'usuario_id' => $usuarioId,
                ]);
            }

            ChatParticipante::query()
                ->where('conversacion_id', $conversacion->id)
                ->where('usuario_id', $autor->usuario_id)
                ->update(['ultimo_leido_mensaje_id' => $nuevo->id]);

            return $nuevo;
        });

        $mensaje->load(['autor:usuario_id,name', 'adjunto', 'acciones']);
        $conversacion->load('participantes.usuario:usuario_id,name,push_token');
        $payload = $this->serializarMensaje($mensaje, $conversacion);
        $participantes = $conversacion->participantes->pluck('usuario_id')->map(fn ($id) => (int) $id)->all();

        event(new MensajeChatCreado($payload, $participantes));
        $this->avisarPush($autor, $conversacion, $mensaje, $asignados);

        return ['mensaje' => $payload];
    }

    /**
     * @return array{mensaje: array<string, mixed>}
     */
    public function atender(User $user, int $mensajeId): array
    {
        $this->asegurarStaff($user);
        $mensaje = ChatMensaje::query()->with(['acciones', 'conversacion.participantes', 'autor:usuario_id,name', 'adjunto'])->find($mensajeId);
        if (! $mensaje || ! $mensaje->conversacion) {
            throw new ChatInternoException('El mensaje no existe.', 404);
        }
        $conversacion = $mensaje->conversacion;
        if (! $this->esParticipante($user, $conversacion)) {
            throw new ChatInternoException('No participas de esta conversación.', 403);
        }

        $propia = $mensaje->acciones->firstWhere('usuario_id', $user->usuario_id);
        if ($propia) {
            if ($propia->atendido_at === null) {
                $propia->forceFill(['atendido_at' => now()])->save();
            }
        } elseif ($user->esAdministrador()) {
            ChatMensajeAccion::query()
                ->where('mensaje_id', $mensaje->id)
                ->whereNull('atendido_at')
                ->update(['atendido_at' => now()]);
        } else {
            throw new ChatInternoException('Esta acción no está asignada a ti.', 403);
        }

        $mensaje->load('acciones');
        $resuelta = $mensaje->acciones->isNotEmpty() && $mensaje->acciones->every(fn (ChatMensajeAccion $accion) => $accion->atendido_at !== null);
        $participantes = $conversacion->participantes->pluck('usuario_id')->map(fn ($id) => (int) $id)->all();

        event(new AccionChatAtendida(
            (int) $mensaje->id,
            (int) $conversacion->id,
            (int) $user->usuario_id,
            $resuelta,
            $participantes,
        ));

        return ['mensaje' => $this->serializarMensaje($mensaje, $conversacion)];
    }

    /**
     * @return array{no_leidos: int, acciones: list<array<string, mixed>>}
     */
    public function pendientes(User $user): array
    {
        $this->asegurarStaff($user);

        $acciones = ChatMensajeAccion::query()
            ->where('usuario_id', $user->usuario_id)
            ->whereNull('atendido_at')
            ->with(['mensaje.autor:usuario_id,name', 'mensaje.adjunto', 'mensaje.conversacion.participantes.usuario:usuario_id,name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'no_leidos' => $this->contarNoLeidos($user),
            'acciones' => $acciones->map(function (ChatMensajeAccion $accion) use ($user) {
                $mensaje = $accion->mensaje;
                $conversacion = $mensaje?->conversacion;

                return [
                    'mensaje_id' => (int) $accion->mensaje_id,
                    'conversacion_id' => (int) ($mensaje->conversacion_id ?? 0),
                    'titulo' => $conversacion ? $this->tituloPara($conversacion, $user) : 'Chat interno',
                    'autor' => $mensaje?->autor?->name ?? 'Staff',
                    'cuerpo' => $mensaje?->cuerpo,
                    'adjunto_nombre' => $mensaje?->adjunto?->nombre_original,
                ];
            })->all(),
        ];
    }

    public function adjuntoPara(User $user, int $adjuntoId): ChatAdjunto
    {
        $this->asegurarStaff($user);
        $adjunto = ChatAdjunto::query()->with('mensaje.conversacion.participantes')->find($adjuntoId);
        $conversacion = $adjunto?->mensaje?->conversacion;
        if (! $adjunto || ! $conversacion || ! $this->esParticipante($user, $conversacion)) {
            throw new ChatInternoException('No puedes descargar este archivo.', 403);
        }

        return $adjunto;
    }

    private function asegurarStaff(User $user): void
    {
        if (! $user->esStaff()) {
            throw new ChatInternoException('Acceso reservado al personal.', 403);
        }
    }

    private function staffActivo(int $usuarioId): User
    {
        $otro = User::query()->where('usuario_id', $usuarioId)->where('estado', 'activo')->first();
        if (! $otro || ! $otro->esStaff()) {
            throw new ChatInternoException('Ese usuario no está disponible.');
        }

        return $otro;
    }

    private function conversacionDe(User $user, int $id): ChatConversacion
    {
        $this->asegurarStaff($user);
        $conversacion = ChatConversacion::query()->with('participantes')->find($id);
        if (! $conversacion || ! $this->esParticipante($user, $conversacion)) {
            throw new ChatInternoException('No participas de esta conversación.', 403);
        }

        return $conversacion;
    }

    private function esParticipante(User $user, ChatConversacion $conversacion): bool
    {
        if ($conversacion->relationLoaded('participantes')) {
            return $conversacion->participantes->contains(fn (ChatParticipante $p) => (int) $p->usuario_id === (int) $user->usuario_id);
        }

        return $conversacion->participantes()->where('usuario_id', $user->usuario_id)->exists();
    }

    /**
     * @param  list<int>  $usuarioIds
     */
    private function agregarParticipantes(ChatConversacion $conversacion, array $usuarioIds): void
    {
        foreach (array_unique($usuarioIds) as $usuarioId) {
            ChatParticipante::query()->create([
                'conversacion_id' => $conversacion->id,
                'usuario_id' => $usuarioId,
            ]);
        }
    }

    private function claveDirecto(int $a, int $b): string
    {
        $menor = min($a, $b);
        $mayor = max($a, $b);

        return $menor.'-'.$mayor;
    }

    private function validarArchivo(UploadedFile $archivo): void
    {
        $maximo = 15 * 1024 * 1024;
        if ($archivo->getSize() > $maximo) {
            throw new ChatInternoException('El archivo supera los 15 MB.');
        }

        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension() ?: '');
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
        if (! in_array($extension, $permitidas, true)) {
            throw new ChatInternoException('Ese tipo de archivo no está permitido.');
        }
    }

    private function nombreSeguro(string $nombre): string
    {
        $base = basename(str_replace('\\', '/', $nombre));
        $base = preg_replace('/[^\w.\- ()áéíóúñÁÉÍÓÚÑüÜ]+/u', '_', $base) ?: 'archivo';

        return Str::limit($base, 180, '');
    }

    /**
     * @param  list<int>  $asignados
     */
    private function avisarPush(User $autor, ChatConversacion $conversacion, ChatMensaje $mensaje, array $asignados): void
    {
        $preview = $mensaje->cuerpo
            ? Str::limit($mensaje->cuerpo, 140, '…')
            : 'Archivo: '.($mensaje->adjunto?->nombre_original ?? 'adjunto');
        $asignadosSet = array_fill_keys($asignados, true);

        foreach ($conversacion->participantes as $participante) {
            $destinoId = (int) $participante->usuario_id;
            if ($destinoId === (int) $autor->usuario_id) {
                continue;
            }
            $destino = $participante->usuario ?? User::query()->find($destinoId);
            if (! $destino) {
                continue;
            }
            $pideAccion = isset($asignadosSet[$destinoId]);
            try {
                $this->fcm->notifyUser(
                    $destino,
                    $pideAccion ? 'Acción requerida' : $autor->name,
                    $preview,
                    [
                        'type' => 'chat_interno',
                        'tipo' => 'chat_interno',
                        'conversacion_id' => (string) $conversacion->id,
                        'mensaje_id' => (string) $mensaje->id,
                        'requiere_accion' => $pideAccion ? '1' : '0',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Chat interno: el push no se envió', [
                    'usuario_id' => $destinoId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $conversacionIds
     * @return array<int, int>
     */
    private function noLeidosPorConversacion(User $user, array $conversacionIds): array
    {
        if ($conversacionIds === []) {
            return [];
        }

        $filas = ChatMensaje::query()
            ->select('chat_mensajes.conversacion_id', DB::raw('count(*) as total'))
            ->join('chat_participantes as p', function ($join) use ($user) {
                $join->on('p.conversacion_id', '=', 'chat_mensajes.conversacion_id')
                    ->where('p.usuario_id', $user->usuario_id);
            })
            ->whereIn('chat_mensajes.conversacion_id', $conversacionIds)
            ->where('chat_mensajes.usuario_id', '!=', $user->usuario_id)
            ->where(function ($q) {
                $q->whereNull('p.ultimo_leido_mensaje_id')
                    ->orWhereColumn('chat_mensajes.id', '>', 'p.ultimo_leido_mensaje_id');
            })
            ->groupBy('chat_mensajes.conversacion_id')
            ->pluck('total', 'conversacion_id');

        $conteo = [];
        foreach ($filas as $id => $total) {
            $conteo[(int) $id] = (int) $total;
        }

        return $conteo;
    }

    private function contarNoLeidos(User $user): int
    {
        return (int) ChatMensaje::query()
            ->join('chat_participantes as p', function ($join) use ($user) {
                $join->on('p.conversacion_id', '=', 'chat_mensajes.conversacion_id')
                    ->where('p.usuario_id', $user->usuario_id);
            })
            ->where('chat_mensajes.usuario_id', '!=', $user->usuario_id)
            ->where(function ($q) {
                $q->whereNull('p.ultimo_leido_mensaje_id')
                    ->orWhereColumn('chat_mensajes.id', '>', 'p.ultimo_leido_mensaje_id');
            })
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarConversacion(ChatConversacion $conversacion, User $user, int $noLeidos): array
    {
        $ultimo = $conversacion->ultimoMensaje;

        return [
            'id' => (int) $conversacion->id,
            'tipo' => $conversacion->tipo,
            'titulo' => $this->tituloPara($conversacion, $user),
            'no_leidos' => $noLeidos,
            'participantes' => $conversacion->participantes->map(fn (ChatParticipante $p) => [
                'usuario_id' => (int) $p->usuario_id,
                'name' => $p->usuario->name ?? 'Staff',
            ])->values()->all(),
            'ultimo' => $ultimo ? [
                'id' => (int) $ultimo->id,
                'cuerpo' => $ultimo->cuerpo,
                'adjunto_nombre' => $ultimo->adjunto?->nombre_original,
                'usuario_id' => (int) $ultimo->usuario_id,
                'creado_at' => $ultimo->created_at?->toIso8601String(),
            ] : null,
        ];
    }

    private function tituloPara(ChatConversacion $conversacion, User $user): string
    {
        if ($conversacion->tipo === 'grupo') {
            return (string) ($conversacion->titulo ?: 'Grupo');
        }

        $otro = $conversacion->participantes->first(fn (ChatParticipante $p) => (int) $p->usuario_id !== (int) $user->usuario_id);

        return $otro?->usuario?->name ?? 'Mensaje directo';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarMensaje(ChatMensaje $mensaje, ChatConversacion $conversacion): array
    {
        $adjunto = $mensaje->adjunto;

        return [
            'id' => (int) $mensaje->id,
            'conversacion_id' => (int) $mensaje->conversacion_id,
            'usuario_id' => (int) $mensaje->usuario_id,
            'autor' => $mensaje->autor->name ?? 'Staff',
            'cuerpo' => $mensaje->cuerpo,
            'creado_at' => $mensaje->created_at?->toIso8601String(),
            'titulo' => $conversacion->tipo === 'grupo' ? (string) ($conversacion->titulo ?: 'Grupo') : 'Mensaje directo',
            'requiere_accion' => $mensaje->acciones->isNotEmpty(),
            'asignados' => $mensaje->acciones->map(fn (ChatMensajeAccion $accion) => [
                'usuario_id' => (int) $accion->usuario_id,
                'atendido_at' => $accion->atendido_at?->toIso8601String(),
            ])->values()->all(),
            'adjunto' => $adjunto ? [
                'id' => (int) $adjunto->id,
                'nombre' => $adjunto->nombre_original,
                'mime' => $adjunto->mime,
                'tamano' => (int) $adjunto->tamano,
                'es_imagen' => str_starts_with((string) $adjunto->mime, 'image/'),
                'url' => route('chat.adjuntos', $adjunto->id),
                'api_url' => url('/api/v1/staff/chat/adjuntos/'.$adjunto->id),
            ] : null,
        ];
    }
}
