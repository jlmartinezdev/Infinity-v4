<?php

namespace App\Services\WhatsApp;

use App\Models\WaAgentEvento;
use App\Models\WaAgentUsoModelo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WaAgentKpiService
{
    /**
     * @return array{desde: Carbon, hasta: Carbon, etiqueta: string, periodo: string}
     */
    public function resolverPeriodo(?string $periodo, ?string $desde = null, ?string $hasta = null): array
    {
        $periodo = $periodo ?: 'mes';
        $ahora = now();

        if ($periodo === 'custom' && $desde && $hasta) {
            $d = Carbon::parse($desde)->startOfDay();
            $h = Carbon::parse($hasta)->endOfDay();

            return [
                'desde' => $d,
                'hasta' => $h,
                'etiqueta' => $d->format('d/m/Y').' – '.$h->format('d/m/Y'),
                'periodo' => 'custom',
            ];
        }

        return match ($periodo) {
            'dia', 'hoy' => [
                'desde' => $ahora->copy()->startOfDay(),
                'hasta' => $ahora->copy()->endOfDay(),
                'etiqueta' => 'Hoy',
                'periodo' => 'dia',
            ],
            'semana' => [
                'desde' => $ahora->copy()->startOfWeek(),
                'hasta' => $ahora->copy()->endOfWeek(),
                'etiqueta' => 'Esta semana',
                'periodo' => 'semana',
            ],
            'anio', 'año' => [
                'desde' => $ahora->copy()->startOfYear(),
                'hasta' => $ahora->copy()->endOfYear(),
                'etiqueta' => 'Año '.$ahora->year,
                'periodo' => 'anio',
            ],
            default => [
                'desde' => $ahora->copy()->startOfMonth(),
                'hasta' => $ahora->copy()->endOfMonth(),
                'etiqueta' => $ahora->translatedFormat('F Y'),
                'periodo' => 'mes',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function resumen(Carbon $desde, Carbon $hasta, bool $incluirSandbox = false): array
    {
        $base = WaAgentEvento::query()
            ->whereBetween('ocurrido_at', [$desde, $hasta]);
        if (! $incluirSandbox) {
            $base->where('sandbox', false);
        }

        $porTipo = (clone $base)
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $respuestas = (int) ($porTipo[WaAgentEvento::TIPO_RESPUESTA] ?? 0);
        $resueltoAuto = (int) ($porTipo[WaAgentEvento::TIPO_RESUELTO_AUTO] ?? 0);
        $tickets = (int) ($porTipo[WaAgentEvento::TIPO_ESCALADO_TICKET] ?? 0);
        $comprobantes = (int) ($porTipo[WaAgentEvento::TIPO_COMPROBANTE] ?? 0);
        $zonaAviso = (int) ($porTipo[WaAgentEvento::TIPO_ZONA_AVISO] ?? 0);
        $zonaOk = (int) ($porTipo[WaAgentEvento::TIPO_ZONA_OK] ?? 0);
        $ventas = (int) ($porTipo[WaAgentEvento::TIPO_VENTA_ATRIBUIDA] ?? 0);
        $errores = (int) ($porTipo[WaAgentEvento::TIPO_ERROR] ?? 0);

        $conversaciones = (int) (clone $base)
            ->where('tipo', WaAgentEvento::TIPO_RESPUESTA)
            ->whereNotNull('telefono')
            ->selectRaw('COUNT(DISTINCT telefono) as c')
            ->value('c');

        $pctResuelto = $respuestas > 0
            ? round(($resueltoAuto / $respuestas) * 100, 1)
            : 0.0;

        $latencias = (clone $base)
            ->where('tipo', WaAgentEvento::TIPO_RESPUESTA)
            ->whereNotNull('latency_ms')
            ->where('latency_ms', '>', 0)
            ->orderBy('latency_ms')
            ->pluck('latency_ms')
            ->map(fn ($v) => (int) $v)
            ->values();

        $costoQ = WaAgentUsoModelo::query()
            ->whereBetween('ocurrido_at', [$desde, $hasta]);
        if (! $incluirSandbox) {
            $costoQ->where('sandbox', false);
        }
        $costoUsd = (float) $costoQ->sum('costo_usd_estimado');
        $llamadasModelo = (int) $costoQ->sum('llamadas');

        $ventasMeta = (clone $base)
            ->where('tipo', WaAgentEvento::TIPO_VENTA_ATRIBUIDA)
            ->get(['meta', 'pedido_id', 'cobro_id', 'telefono', 'ocurrido_at', 'modo']);

        $montoVentas = 0.0;
        foreach ($ventasMeta as $ev) {
            $montoVentas += (float) data_get($ev->meta, 'monto', 0);
        }

        $csatScores = (clone $base)
            ->where('tipo', WaAgentEvento::TIPO_CSAT)
            ->get()
            ->map(fn (WaAgentEvento $e) => (int) data_get($e->meta, 'score', 0))
            ->filter(fn ($s) => $s >= 1 && $s <= 5)
            ->values();
        $csatPromedio = $csatScores->isNotEmpty()
            ? round($csatScores->avg(), 2)
            : null;
        $csatN = $csatScores->count();

        $eps = 0.000001;
        $roi = ($montoVentas - $costoUsd) / max($costoUsd, $eps);

        $porModo = (clone $base)
            ->where('tipo', WaAgentEvento::TIPO_RESPUESTA)
            ->selectRaw("COALESCE(modo, 'desconocido') as modo, COUNT(*) as total")
            ->groupByRaw("COALESCE(modo, 'desconocido')")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['modo' => (string) $r->modo, 'total' => (int) $r->total])
            ->all();

        return [
            'conversaciones' => $conversaciones,
            'respuestas' => $respuestas,
            'resuelto_auto' => $resueltoAuto,
            'pct_resuelto_auto' => $pctResuelto,
            'tickets' => $tickets,
            'comprobantes' => $comprobantes,
            'zona_aviso' => $zonaAviso,
            'zona_ok' => $zonaOk,
            'ventas_n' => $ventas,
            'ventas_monto' => round($montoVentas, 2),
            'csat_promedio' => $csatPromedio,
            'csat_n' => $csatN,
            'latency_p50' => $this->percentil($latencias, 50),
            'latency_p95' => $this->percentil($latencias, 95),
            'costo_usd' => round($costoUsd, 4),
            'llamadas_modelo' => $llamadasModelo,
            'roi' => round($roi, 2),
            'errores' => $errores,
            'por_modo' => $porModo,
            'por_tipo' => $porTipo->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Heatmap: respuestas y ventas por día de semana (0=dom) × hora.
     *
     * @return array{respuestas: array<int, array<int, int>>, ventas: array<int, array<int, int>>}
     */
    public function heatmap(Carbon $desde, Carbon $hasta, bool $incluirSandbox = false): array
    {
        $q = WaAgentEvento::query()
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->whereIn('tipo', [WaAgentEvento::TIPO_RESPUESTA, WaAgentEvento::TIPO_VENTA_ATRIBUIDA]);
        if (! $incluirSandbox) {
            $q->where('sandbox', false);
        }

        $respuestas = array_fill(0, 7, array_fill(0, 24, 0));
        $ventas = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($q->get(['tipo', 'ocurrido_at']) as $ev) {
            /** @var Carbon $at */
            $at = $ev->ocurrido_at;
            $d = (int) $at->dayOfWeek;
            $h = (int) $at->hour;
            if ($ev->tipo === WaAgentEvento::TIPO_VENTA_ATRIBUIDA) {
                $ventas[$d][$h]++;
            } else {
                $respuestas[$d][$h]++;
            }
        }

        return ['respuestas' => $respuestas, 'ventas' => $ventas];
    }

    /**
     * @return Collection<int, WaAgentEvento>
     */
    public function ventasRecientes(Carbon $desde, Carbon $hasta, int $limit = 40, bool $incluirSandbox = false): Collection
    {
        $q = WaAgentEvento::query()
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->where('tipo', WaAgentEvento::TIPO_VENTA_ATRIBUIDA)
            ->orderByDesc('ocurrido_at')
            ->limit($limit);
        if (! $incluirSandbox) {
            $q->where('sandbox', false);
        }

        return $q->get();
    }

    /**
     * @return Collection<int, WaAgentEvento>
     */
    public function eventosRecientes(Carbon $desde, Carbon $hasta, int $limit = 50, bool $incluirSandbox = false): Collection
    {
        $q = WaAgentEvento::query()
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->orderByDesc('ocurrido_at')
            ->limit($limit);
        if (! $incluirSandbox) {
            $q->where('sandbox', false);
        }

        return $q->get();
    }

    /**
     * @return list<array{tipo: string, telefono: ?string, modo: ?string, ocurrido_at: string, meta: mixed}>
     */
    public function exportFilas(Carbon $desde, Carbon $hasta, bool $incluirSandbox = false): array
    {
        $q = WaAgentEvento::query()
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->orderBy('ocurrido_at');
        if (! $incluirSandbox) {
            $q->where('sandbox', false);
        }

        return $q->get()->map(fn (WaAgentEvento $e) => [
            'ocurrido_at' => optional($e->ocurrido_at)->toDateTimeString(),
            'tipo' => $e->tipo,
            'modo' => $e->modo,
            'telefono' => $e->telefono,
            'cliente_id' => $e->cliente_id,
            'ticket_id' => $e->ticket_id,
            'pedido_id' => $e->pedido_id,
            'cobro_id' => $e->cobro_id,
            'latency_ms' => $e->latency_ms,
            'meta' => $e->meta ? json_encode($e->meta, JSON_UNESCAPED_UNICODE) : '',
        ])->all();
    }

    /**
     * @param  Collection<int, int>  $ordenado
     */
    private function percentil(Collection $ordenado, int $p): ?int
    {
        $n = $ordenado->count();
        if ($n === 0) {
            return null;
        }
        $idx = (int) ceil(($p / 100) * $n) - 1;
        $idx = max(0, min($n - 1, $idx));

        return (int) $ordenado[$idx];
    }
}
