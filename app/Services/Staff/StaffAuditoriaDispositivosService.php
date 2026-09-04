<?php

namespace App\Services\Staff;

use App\Models\Dispositivo;
use App\Models\SolicitudAcceso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StaffAuditoriaDispositivosService
{
    /**
     * @param  array{app_activa?: mixed, q?: string|null, recencia?: string|null, page?: int, per_page?: int}  $filtros
     */
    public function paginar(array $filtros = []): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filtros['per_page'] ?? 50)));
        $page = max(1, (int) ($filtros['page'] ?? 1));

        $query = Dispositivo::query()
            ->with(['cliente:cliente_id,nombre,apellido,cedula,app_activa'])
            ->whereHas('cliente');

        $this->aplicarFiltroAppActiva($query, $filtros['app_activa'] ?? 1);
        $this->aplicarBusqueda($query, trim((string) ($filtros['q'] ?? '')));
        $this->aplicarRecencia($query, strtolower(trim((string) ($filtros['recencia'] ?? ''))));

        $paginator = $query
            ->orderByDesc('last_seen')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Dispositivo $d) => $this->toItem($d))
        );

        return $paginator;
    }

    private function aplicarFiltroAppActiva(Builder $query, mixed $appActiva): void
    {
        if ($appActiva === '0' || $appActiva === 0 || $appActiva === false || $appActiva === 'false') {
            return;
        }

        $query->where(function (Builder $q) {
            $q->where('dispositivos.app_activa', true)
                ->orWhereHas('cliente', fn ($c) => $c->where('app_activa', true));
        });

        $query->whereExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('solicitudes_acceso')
                ->whereColumn('solicitudes_acceso.cliente_id', 'dispositivos.cliente_id')
                ->where('solicitudes_acceso.estado', SolicitudAcceso::ESTADO_APROBADA);
        });
    }

    private function aplicarBusqueda(Builder $query, string $q): void
    {
        if (mb_strlen($q) < 2) {
            return;
        }

        $like = '%'.$q.'%';
        $query->where(function (Builder $inner) use ($like) {
            $inner->where('dispositivos.nombre', 'like', $like)
                ->orWhere('dispositivos.app_version', 'like', $like)
                ->orWhereHas('cliente', function (Builder $c) use ($like) {
                    $c->where('nombre', 'like', $like)
                        ->orWhere('apellido', 'like', $like)
                        ->orWhereRaw("CONCAT(COALESCE(nombre,''),' ',COALESCE(apellido,'')) like ?", [$like])
                        ->orWhere('cedula', 'like', $like);
                });
        });
    }

    private function aplicarRecencia(Builder $query, string $recencia): void
    {
        if ($recencia === '24h') {
            $query->where('last_seen', '>=', now()->subDay());

            return;
        }

        if ($recencia === '7d') {
            $query->where('last_seen', '>=', now()->subDays(7))
                ->where('last_seen', '<', now()->subDay());

            return;
        }

        if ($recencia === 'gt7d') {
            $query->where(function (Builder $q) {
                $q->whereNull('last_seen')
                    ->orWhere('last_seen', '<', now()->subDays(7));
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toItem(Dispositivo $d): array
    {
        $c = $d->cliente;
        $nombre = $c
            ? trim(($c->nombre ?? '').' '.($c->apellido ?? ''))
            : '';

        $lastSeen = $d->last_seen;
        $lastLogin = $d->last_login;
        $actividad = $lastSeen;
        if ($lastLogin && (! $actividad || $lastLogin->gt($actividad))) {
            $actividad = $lastLogin;
        }

        return [
            'id' => (int) $d->id,
            'cliente_id' => (int) $d->cliente_id,
            'cliente' => $nombre !== '' ? $nombre : 'Cliente #'.$d->cliente_id,
            'documento' => $c?->cedula,
            'dispositivo' => $d->nombre,
            'app_version' => $d->app_version,
            'app_activa' => (bool) ($d->app_activa || $c?->app_activa),
            'last_seen' => Dispositivo::formatIso($actividad),
            'last_login' => Dispositivo::formatIso($lastLogin),
            'ultimo_ingreso' => Dispositivo::formatIso($actividad),
        ];
    }
}
