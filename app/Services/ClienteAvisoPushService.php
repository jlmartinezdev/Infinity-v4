<?php

namespace App\Services;

use App\Models\PushAviso;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Envío voluntario de push FCM a clientes (promos / avisos desde el panel).
 */
class ClienteAvisoPushService
{
    public function __construct(
        protected FcmPushService $fcm
    ) {}

    /**
     * Cantidad de usuarios portal activos con token FCM.
     */
    public function contarConPush(): int
    {
        return $this->queryConPush()->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function destinatariosTodos(): Collection
    {
        return $this->queryConPush()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderBy('cliente_id')
            ->get();
    }

    /**
     * @param  list<int>  $clienteIds
     * @return Collection<int, User>
     */
    public function destinatariosSeleccionados(array $clienteIds): Collection
    {
        $ids = collect($clienteIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return $this->queryConPush()
            ->whereIn('cliente_id', $ids)
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderBy('cliente_id')
            ->get();
    }

    /**
     * @param  list<int>  $clienteIds  vacío si destino=todos
     */
    public function enviar(
        string $titulo,
        string $cuerpo,
        string $tipo,
        string $destino,
        array $clienteIds,
        ?int $creadoPor,
    ): PushAviso {
        $titulo = trim($titulo);
        $cuerpo = trim($cuerpo);
        $tipo = in_array($tipo, array_keys(PushAviso::tipos()), true) ? $tipo : 'aviso';
        $destino = $destino === 'seleccionados' ? 'seleccionados' : 'todos';

        $users = $destino === 'todos'
            ? $this->destinatariosTodos()
            : $this->destinatariosSeleccionados($clienteIds);

        // Un usuario por cliente (el más reciente con token ya viene del query).
        $porCliente = $users->unique('cliente_id')->values();

        $aviso = PushAviso::query()->create([
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'tipo' => $tipo,
            'destino' => $destino,
            'cliente_ids' => $destino === 'seleccionados'
                ? $porCliente->pluck('cliente_id')->map(fn ($id) => (int) $id)->values()->all()
                : null,
            'total_destinatarios' => $porCliente->count(),
            'enviados' => 0,
            'fallidos' => 0,
            'omitidos' => 0,
            'creado_por' => $creadoPor,
        ]);

        $enviados = 0;
        $fallidos = 0;

        foreach ($porCliente as $user) {
            $ok = false;
            try {
                $ok = $this->fcm->notifyUser($user, $titulo, $cuerpo, [
                    'tipo' => $tipo,
                    'aviso_id' => (string) $aviso->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Aviso push excepción: '.$e->getMessage(), [
                    'aviso_id' => $aviso->id,
                    'cliente_id' => $user->cliente_id,
                ]);
            }

            if ($ok) {
                $enviados++;
            } else {
                $fallidos++;
            }
        }

        $aviso->update([
            'enviados' => $enviados,
            'fallidos' => $fallidos,
            'omitidos' => 0,
        ]);

        return $aviso->fresh() ?? $aviso;
    }

    /**
     * Buscar clientes con push para el selector del panel.
     *
     * @return list<array{cliente_id:int,nombre:?string,apellido:?string,cedula:?string}>
     */
    public function buscarConPush(string $q, int $limit = 15): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $like = '%'.$q.'%';

        return $this->queryConPush()
            ->whereHas('cliente', function ($query) use ($like, $q) {
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('nombre', 'like', $like)
                        ->orWhere('apellido', 'like', $like)
                        ->orWhere('cedula', 'like', $like)
                        ->orWhere('telefono', 'like', $like);
                    if (ctype_digit($q)) {
                        $inner->orWhere('cliente_id', (int) $q);
                    }
                });
            })
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderByDesc('ultimo_acceso_at')
            ->limit($limit)
            ->get()
            ->map(function (User $user) {
                $c = $user->cliente;

                return [
                    'cliente_id' => (int) $user->cliente_id,
                    'nombre' => $c?->nombre,
                    'apellido' => $c?->apellido,
                    'cedula' => $c?->cedula,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Reenvía un aviso existente (crea un nuevo registro en historial).
     */
    public function reenviar(PushAviso $aviso, ?int $creadoPor): PushAviso
    {
        return $this->enviar(
            (string) $aviso->titulo,
            (string) $aviso->cuerpo,
            (string) $aviso->tipo,
            (string) $aviso->destino,
            is_array($aviso->cliente_ids) ? $aviso->cliente_ids : [],
            $creadoPor
        );
    }

    private function queryConPush()
    {
        return User::query()
            ->whereNotNull('cliente_id')
            ->activos()
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '');
    }
}
