<?php

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsappEncargoException;
use App\Models\User;
use App\Models\WhatsappContacto;
use App\Notifications\WhatsappEncargoNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappEncargoService
{
    /** Actividad reciente en el panel para el punto verde. */
    private const EN_LINEA_SEGUNDOS = 180;

    /** @var array<int, true>|null */
    private ?array $enLineaCache = null;

    public function __construct(private readonly WhatsAppPhoneNormalizer $telefonos) {}

    public function normalizar(mixed $telefono): string
    {
        $tel = $this->telefonos->normalize(is_string($telefono) ? $telefono : null);
        if (! $tel) {
            throw new WhatsappEncargoException('Teléfono requerido');
        }

        return $tel;
    }

    /**
     * @return array{telefono: string, encargado: ?array{usuario_id: int, nombre: string, mio: bool, desde: ?string}}
     */
    public function estado(User $user, string $telefono): array
    {
        $tel = $this->normalizar($telefono);
        $contacto = WhatsappContacto::query()->with('encargado:usuario_id,name')->where('telefono', $tel)->first();

        return [
            'telefono' => $tel,
            'encargado' => $this->payload($contacto, (int) $user->usuario_id),
        ];
    }

    /**
     * @return list<array{telefono: string, usuario_id: int, nombre: string, mio: bool, en_linea: bool}>
     */
    public function activos(User $user): array
    {
        $yo = (int) $user->usuario_id;

        return WhatsappContacto::query()
            ->with('encargado:usuario_id,name')
            ->whereNotNull('encargado_usuario_id')
            ->where('encargado_at', '>=', now()->startOfDay())
            ->get()
            ->map(function (WhatsappContacto $contacto) use ($yo) {
                $encargado = $this->payload($contacto, $yo);
                if (! $encargado) {
                    return null;
                }

                return [
                    'telefono' => $contacto->telefono,
                    'usuario_id' => $encargado['usuario_id'],
                    'nombre' => $encargado['nombre'],
                    'mio' => $encargado['mio'],
                    'en_linea' => $encargado['en_linea'],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{usuario_id: int, name: string, cargo: ?string}>
     */
    public function staff(User $user): array
    {
        return User::query()
            ->with('rol')
            ->staff()
            ->activos()
            ->where('usuario_id', '!=', $user->usuario_id)
            ->whereDoesntHave('rol', function ($rol) {
                $rol->whereRaw('LOWER(descripcion) = ?', ['cliente app']);
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (User $otro) => $otro->tienePermiso('whatsapp.ver'))
            ->map(fn (User $otro) => [
                'usuario_id' => (int) $otro->usuario_id,
                'name' => $otro->name,
                'cargo' => $otro->cargo,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{telefono: string, encargado: array{usuario_id: int, nombre: string, mio: bool, desde: ?string}}
     */
    public function encargarse(User $user, string $telefono): array
    {
        $tel = $this->normalizar($telefono);

        $contacto = DB::transaction(function () use ($user, $tel) {
            $contacto = $this->contactoBloqueado($tel);
            $this->exigirLibreOMio($contacto, $user);
            $contacto->encargado_usuario_id = $user->usuario_id;
            $contacto->encargado_at = now();
            $contacto->save();

            return $contacto;
        });

        $contacto->load('encargado:usuario_id,name');

        return [
            'telefono' => $tel,
            'encargado' => $this->payload($contacto, (int) $user->usuario_id),
        ];
    }

    /**
     * @return array{telefono: string, encargado: null}
     */
    public function soltar(User $user, string $telefono): array
    {
        $tel = $this->normalizar($telefono);

        DB::transaction(function () use ($user, $tel) {
            $contacto = $this->contactoBloqueado($tel);
            $this->exigirMio($contacto, $user);
            $contacto->encargado_usuario_id = null;
            $contacto->encargado_at = null;
            $contacto->save();
        });

        return [
            'telefono' => $tel,
            'encargado' => null,
        ];
    }

    /**
     * @return array{telefono: string, encargado: array{usuario_id: int, nombre: string, mio: bool, desde: ?string}}
     */
    public function transferir(User $user, string $telefono, int $destinoId): array
    {
        $tel = $this->normalizar($telefono);
        if ($destinoId === (int) $user->usuario_id) {
            throw new WhatsappEncargoException('Elegí a otra persona.');
        }

        $destino = User::query()->with('rol')->staff()->activos()->where('usuario_id', $destinoId)->first();
        if (! $destino || ! $destino->tienePermiso('whatsapp.ver')) {
            throw new WhatsappEncargoException('Esa persona no tiene permiso de WhatsApp.', 404);
        }

        $contacto = DB::transaction(function () use ($user, $tel, $destino) {
            $contacto = $this->contactoBloqueado($tel);
            $this->exigirMio($contacto, $user);
            $contacto->encargado_usuario_id = $destino->usuario_id;
            $contacto->encargado_at = now();
            $contacto->save();

            return $contacto;
        });

        $contacto->load(['encargado:usuario_id,name', 'cliente:cliente_id,nombre,apellido']);
        $quien = $this->nombreChat($contacto) ?: $tel;
        $destino->notify(new WhatsappEncargoNotification(
            mensaje: $user->name.' te pasó el chat de WhatsApp de '.$quien.'.',
            url: route('whatsapp.mensajes', ['tel' => $tel]),
            de: (string) $user->name,
        ));

        return [
            'telefono' => $tel,
            'encargado' => $this->payload($contacto, (int) $user->usuario_id),
        ];
    }

    /**
     * Suelta los encargos de días anteriores. El de hoy sigue hasta la medianoche.
     */
    public function liberarVencidos(): int
    {
        return WhatsappContacto::query()
            ->whereNotNull('encargado_usuario_id')
            ->where(function ($query) {
                $query->whereNull('encargado_at')
                    ->orWhere('encargado_at', '<', now()->startOfDay());
            })
            ->update([
                'encargado_usuario_id' => null,
                'encargado_at' => null,
            ]);
    }

    /**
     * @return array{usuario_id: int, nombre: string, mio: bool, en_linea: bool, desde: ?string}|null
     */
    public function payload(?WhatsappContacto $contacto, ?int $yoId): ?array
    {
        if (! $contacto || ! $this->vigente($contacto)) {
            return null;
        }

        $nombre = $contacto->relationLoaded('encargado')
            ? $contacto->encargado?->name
            : $contacto->encargado()->value('name');
        if (! $nombre) {
            return null;
        }

        return [
            'usuario_id' => (int) $contacto->encargado_usuario_id,
            'nombre' => (string) $nombre,
            'mio' => $yoId !== null && (int) $contacto->encargado_usuario_id === $yoId,
            'en_linea' => $this->estaEnLinea((int) $contacto->encargado_usuario_id),
            'desde' => $contacto->encargado_at?->toIso8601String(),
        ];
    }

    private function estaEnLinea(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }
        if ($this->enLineaCache === null) {
            $this->enLineaCache = [];
            if (Schema::hasTable('sessions')) {
                $umbral = time() - self::EN_LINEA_SEGUNDOS;
                foreach (DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', $umbral)->pluck('user_id') as $id) {
                    $this->enLineaCache[(int) $id] = true;
                }
            }
        }

        return isset($this->enLineaCache[$usuarioId]);
    }

    private function contactoBloqueado(string $tel): WhatsappContacto
    {
        $contacto = WhatsappContacto::query()->where('telefono', $tel)->lockForUpdate()->first();
        if ($contacto) {
            return $contacto;
        }

        $contacto = new WhatsappContacto(['telefono' => $tel]);
        $contacto->save();

        return WhatsappContacto::query()->where('telefono', $tel)->lockForUpdate()->firstOrFail();
    }

    private function vigente(WhatsappContacto $contacto): bool
    {
        return (bool) $contacto->encargado_usuario_id
            && $contacto->encargado_at
            && $contacto->encargado_at->gte(now()->startOfDay());
    }

    private function exigirLibreOMio(WhatsappContacto $contacto, User $user): void
    {
        if (! $this->vigente($contacto)) {
            return;
        }

        $actual = (int) $contacto->encargado_usuario_id;
        if ($actual === (int) $user->usuario_id) {
            return;
        }

        $nombre = User::query()->where('usuario_id', $actual)->value('name') ?: 'otra persona';
        throw new WhatsappEncargoException('Ya se encarga '.$nombre.'.', 409);
    }

    private function exigirMio(WhatsappContacto $contacto, User $user): void
    {
        if (! $this->vigente($contacto)) {
            throw new WhatsappEncargoException('El encargo de ayer ya se cerró. Hay que encargarse de nuevo.', 403);
        }

        if ((int) $contacto->encargado_usuario_id !== (int) $user->usuario_id) {
            throw new WhatsappEncargoException('Solo quien se encarga puede hacer esto.', 403);
        }
    }

    private function nombreChat(WhatsappContacto $contacto): string
    {
        $crm = trim((string) (($contacto->cliente->nombre ?? '').' '.($contacto->cliente->apellido ?? '')));
        if ($crm !== '') {
            return $crm;
        }

        return trim((string) ($contacto->nombre ?? ''));
    }
}
