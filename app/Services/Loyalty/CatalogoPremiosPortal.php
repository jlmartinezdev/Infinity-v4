<?php

namespace App\Services\Loyalty;

use App\Models\Canje;
use App\Models\Premio;
use Illuminate\Support\Collection;

/**
 * Catálogo que ve un cliente logueado.
 * Incluye premios de 0 puntos. No hay tier de cliente: un tier en el premio no lo oculta.
 * Una vez de por vida ya usado sale del catálogo (sigue en Mis cupones).
 */
class CatalogoPremiosPortal
{
    public function __construct(
        private readonly PuntosService $puntos,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listar(int $clienteId): Collection
    {
        $saldo = $this->puntos->saldo($clienteId);
        $puedeMes = $this->puntos->puedeCanjear($clienteId);
        $usos = $this->usosPorPremio($clienteId);

        return $this->base()
            ->reject(function (Premio $premio) use ($usos) {
                $fila = $usos->get($premio->id);

                return $premio->una_vez_por_cliente && $fila && $fila['total'] > 0;
            })
            ->map(fn (Premio $premio) => $this->fila($premio, $saldo, $puedeMes, $usos->get($premio->id)))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function destacado(int $clienteId): ?array
    {
        $lista = $this->listar($clienteId);
        $marcado = $lista->first(fn (array $fila) => ! empty($fila['destacado']));

        return $marcado ?: $lista->first();
    }

    /**
     * @return Collection<int, Premio>
     */
    private function base(): Collection
    {
        return Premio::disponiblesPortal()
            ->orderByDesc('destacado')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return Collection<int, array{total: int, anio: int}>
     */
    private function usosPorPremio(int $clienteId): Collection
    {
        return Canje::query()
            ->where('cliente_id', $clienteId)
            ->where('estado', '!=', Canje::ESTADO_CANCELADO)
            ->get(['premio_id', 'created_at'])
            ->groupBy(fn (Canje $canje) => (int) $canje->premio_id)
            ->map(function (Collection $filas) {
                return [
                    'total' => $filas->count(),
                    'anio' => $filas->filter(
                        fn (Canje $canje) => $canje->created_at && (int) $canje->created_at->year === (int) now()->year
                    )->count(),
                ];
            });
    }

    /**
     * @param  array{total: int, anio: int}|null  $usos
     * @return array<string, mixed>
     */
    private function fila(Premio $premio, int $saldo, bool $puedeMes, ?array $usos): array
    {
        $requeridos = (int) $premio->puntos_requeridos;
        $usosAnio = (int) ($usos['anio'] ?? 0);
        $puede = true;
        $motivo = null;

        if ($premio->tope_anual_por_cliente !== null && $usosAnio >= (int) $premio->tope_anual_por_cliente) {
            $puede = false;
            $motivo = 'Alcanzaste el tope anual de este premio.';
        } elseif ($requeridos > 0 && $saldo < $requeridos) {
            $puede = false;
            $motivo = 'Te faltan puntos.';
        } elseif (! $puedeMes) {
            $puede = false;
            $motivo = 'Ya usaste el canje de este mes.';
        }

        return array_merge($premio->toPortalArray(), [
            'puede_canjear' => $puede,
            'motivo' => $motivo,
            'etiqueta_puntos' => $requeridos === 0 ? 'GRATIS' : $requeridos.' pts',
            'usos_este_anio' => $usosAnio,
        ]);
    }
}
