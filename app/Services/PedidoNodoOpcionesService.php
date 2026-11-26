<?php

namespace App\Services;

use App\Models\EstadoPedidoDetalle;
use App\Models\Nodo;
use App\Models\Plan;
use App\Models\RouterIpPool;
use App\Models\TipoTecnologia;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PedidoNodoOpcionesService
{
    public static function descripcionEsGpon(?string $descripcion): bool
    {
        return (bool) preg_match('/gpon|epon|ftth|fibra|fiber|pon|xg-pon/i', (string) $descripcion);
    }

    public static function descripcionEsWireless(?string $descripcion): bool
    {
        return (bool) preg_match('/wireless|inalambr|anten|radio|wifi/i', (string) $descripcion);
    }

    /**
     * @return Collection<int, TipoTecnologia>
     */
    public function tiposTecnologiaCompatiblesConNodo(Nodo $nodo, ?Collection $tipos = null): Collection
    {
        $tipos = $tipos ?? TipoTecnologia::orderBy('descripcion')->get();
        $compatibles = collect();

        if ($nodo->manejaGpon()) {
            $compatibles = $compatibles->merge(
                $tipos->filter(fn (TipoTecnologia $t) => self::descripcionEsGpon($t->descripcion))
            );
        }
        if ($nodo->manejaWireless()) {
            $compatibles = $compatibles->merge(
                $tipos->filter(fn (TipoTecnologia $t) => self::descripcionEsWireless($t->descripcion))
            );
        }

        return $compatibles->unique('tecnologia_id')->values();
    }

    /**
     * @return Collection<int, RouterIpPool>
     */
    public function poolsActivosDelNodo(Nodo $nodo): Collection
    {
        return RouterIpPool::query()
            ->where('activo', true)
            ->whereHas('router', fn ($q) => $q->where('nodo_id', $nodo->nodo_id))
            ->with('router:router_id,nombre,nodo_id')
            ->orderBy('descripcion')
            ->orderBy('pool_id')
            ->get();
    }

    /**
     * @return array{
     *     tecnologia_id_auto: int|null,
     *     requiere_seleccion_tecnologia: bool,
     *     tecnologias: list<array{tecnologia_id: int, descripcion: string|null}>,
     *     pool_id_auto: int|null,
     *     requiere_seleccion_pool: bool,
     *     pools: list<array{pool_id: int, label: string}>
     * }
     */
    public function opcionesParaNodo(int $nodoId): array
    {
        $nodo = Nodo::findOrFail($nodoId);
        $compatibles = $this->tiposTecnologiaCompatiblesConNodo($nodo);
        $pools = $this->poolsActivosDelNodo($nodo);

        $poolsPayload = $pools->map(function (RouterIpPool $p) {
            $routerNombre = $p->router?->nombre ?? 'Router';
            $detalle = trim((string) ($p->descripcion ?: $p->ip_range ?: ''));
            $label = $detalle !== '' ? "{$routerNombre} — {$detalle}" : $routerNombre;

            return [
                'pool_id' => (int) $p->pool_id,
                'label' => $label,
            ];
        })->values()->all();

        return [
            'nodo_id' => (int) $nodo->nodo_id,
            'tecnologia_gpon' => $nodo->manejaGpon(),
            'tecnologia_wireless' => $nodo->manejaWireless(),
            'tecnologia_id_auto' => $compatibles->count() === 1 ? (int) $compatibles->first()->tecnologia_id : null,
            'requiere_seleccion_tecnologia' => $compatibles->count() > 1,
            'tecnologias' => $compatibles->map(fn (TipoTecnologia $t) => [
                'tecnologia_id' => (int) $t->tecnologia_id,
                'descripcion' => $t->descripcion,
            ])->all(),
            'pool_id_auto' => $pools->count() === 1 ? (int) $pools->first()->pool_id : null,
            'requiere_seleccion_pool' => $pools->count() > 1,
            'pools' => $poolsPayload,
            'sin_tecnologia_configurada' => $compatibles->isEmpty(),
            'sin_pools_activos' => $pools->isEmpty(),
        ];
    }

    /**
     * @return true Validación OK
     *
     * @throws ValidationException
     */
    public function validarSeleccionAprobacion(int $nodoId, ?int $tecnologiaId, ?int $poolId): bool
    {
        $opciones = $this->opcionesParaNodo($nodoId);

        if ($opciones['sin_pools_activos']) {
            throw ValidationException::withMessages([
                'nodo_id' => 'El nodo seleccionado no tiene pools de IP activos.',
            ]);
        }

        if ($opciones['sin_tecnologia_configurada']) {
            throw ValidationException::withMessages([
                'nodo_id' => 'El nodo no tiene tipos de tecnología compatibles configurados (GPON/Wireless en catálogo).',
            ]);
        }

        $tecnologiaFinal = $tecnologiaId;
        if ($tecnologiaFinal === null && $opciones['tecnologia_id_auto']) {
            $tecnologiaFinal = $opciones['tecnologia_id_auto'];
        }

        if ($opciones['requiere_seleccion_tecnologia'] && $tecnologiaFinal === null) {
            throw ValidationException::withMessages([
                'tecnologia_id' => 'Seleccioná el tipo de tecnología (el nodo maneja GPON y Wireless).',
            ]);
        }

        if ($tecnologiaFinal !== null) {
            $idsValidos = collect($opciones['tecnologias'])->pluck('tecnologia_id')->map(fn ($id) => (int) $id);
            if (! $idsValidos->contains($tecnologiaFinal)) {
                throw ValidationException::withMessages([
                    'tecnologia_id' => 'La tecnología elegida no corresponde a las que maneja este nodo.',
                ]);
            }
        }

        $poolFinal = $poolId;
        if ($poolFinal === null && $opciones['pool_id_auto']) {
            $poolFinal = $opciones['pool_id_auto'];
        }

        if ($opciones['requiere_seleccion_pool'] && $poolFinal === null) {
            throw ValidationException::withMessages([
                'pool_id' => 'Seleccioná el pool de IP (el nodo tiene más de uno).',
            ]);
        }

        if ($poolFinal !== null) {
            $poolIds = collect($opciones['pools'])->pluck('pool_id')->map(fn ($id) => (int) $id);
            if (! $poolIds->contains($poolFinal)) {
                throw ValidationException::withMessages([
                    'pool_id' => 'El pool elegido no pertenece al nodo seleccionado.',
                ]);
            }
        }

        return true;
    }

    /**
     * Resuelve tecnología y pool finales (auto-asignación cuando aplica).
     *
     * @return array{tecnologia_id: int|null, pool_id: int|null}
     */
    public function resolverSeleccionFinal(int $nodoId, ?int $tecnologiaId, ?int $poolId): array
    {
        $opciones = $this->opcionesParaNodo($nodoId);

        $tecnologiaFinal = $tecnologiaId ?? $opciones['tecnologia_id_auto'];
        $poolFinal = $poolId ?? $opciones['pool_id_auto'];

        $this->validarSeleccionAprobacion($nodoId, $tecnologiaFinal, $poolFinal);

        return [
            'tecnologia_id' => $tecnologiaFinal,
            'pool_id' => $poolFinal,
        ];
    }

    /**
     * Tecnología vigente: la del último estado aprobado. Un pendiente viejo no pisa el nodo nuevo.
     */
    public function tecnologiaVigenteId(Collection $detalles): ?int
    {
        $fuente = $this->detalleMasReciente($detalles->where('estado', 'A')->whereNotNull('tecnologia_id'));
        if (! $fuente) {
            $fuente = $this->detalleMasReciente($detalles->whereNotNull('tecnologia_id'));
        }

        return $fuente && $fuente->tecnologia_id ? (int) $fuente->tecnologia_id : null;
    }

    /**
     * Al aprobar, no conservar un plan de otra tecnología.
     * Si el estado pide plan y el heredado no sirve, hay que elegir uno de la tecnología del nodo.
     *
     * @return array{plan_id: int|null, error: string|null}
     */
    public function resolverPlanAlAprobar(
        ?int $planEnviadoId,
        ?int $planHeredadoId,
        ?int $tecnologiaNodoId,
        ?int $tecnologiaPlanEnviado,
        ?int $tecnologiaPlanHeredado,
        bool $exigePlan,
    ): array {
        $usarEnviado = $planEnviadoId !== null;
        $candidato = $usarEnviado ? $planEnviadoId : $planHeredadoId;
        $techPlan = $usarEnviado ? $tecnologiaPlanEnviado : $tecnologiaPlanHeredado;

        if ($candidato !== null && $tecnologiaNodoId !== null && (int) ($techPlan ?? 0) !== (int) $tecnologiaNodoId) {
            if ($usarEnviado || $exigePlan) {
                return [
                    'plan_id' => null,
                    'error' => 'El plan no corresponde a la tecnología del nodo. Elegí un plan de esa tecnología.',
                ];
            }

            $candidato = null;
        }

        if ($exigePlan && $candidato === null) {
            return [
                'plan_id' => null,
                'error' => 'Elegí un plan de la tecnología del nodo.',
            ];
        }

        return ['plan_id' => $candidato, 'error' => null];
    }

    public function tecnologiaDePlan(?int $planId): ?int
    {
        if (! $planId) {
            return null;
        }
        $id = Plan::query()->where('plan_id', $planId)->value('tecnologia_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * El nodo nuevo dejó atrás la selección de antena en estados que todavía no se confirmaron.
     */
    public function soltarSeleccionDeOtraTecnologia(int $pedidoId, int $estadoId, int $tecnologiaId): void
    {
        $otros = EstadoPedidoDetalle::query()
            ->where('pedido_id', $pedidoId)
            ->where('estado_id', '!=', $estadoId)
            ->with('plan:plan_id,tecnologia_id')
            ->get();

        foreach ($otros as $otro) {
            $techPlan = $otro->plan?->tecnologia_id;
            $planNoSirve = $otro->plan_id && (int) $techPlan !== $tecnologiaId;
            $techNoSirve = $otro->tecnologia_id && (int) $otro->tecnologia_id !== $tecnologiaId;
            if (! $planNoSirve && ! $techNoSirve) {
                continue;
            }

            if ($otro->estado === 'P') {
                $otro->nodo_id = null;
                $otro->tecnologia_id = null;
                $otro->pool_id = null;
                $otro->plan_id = null;
            } elseif ($planNoSirve) {
                $otro->plan_id = null;
            }
            $otro->save();
        }
    }

    private function detalleMasReciente(Collection $detalles): ?EstadoPedidoDetalle
    {
        $detalle = $detalles
            ->sortByDesc(function (EstadoPedidoDetalle $d) {
                return optional($d->fecha)?->timestamp
                    ?? optional($d->created_at)?->timestamp
                    ?? 0;
            })
            ->first();

        return $detalle instanceof EstadoPedidoDetalle ? $detalle : null;
    }
}
