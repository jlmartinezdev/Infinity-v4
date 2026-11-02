<?php

namespace App\Services\WhatsApp;

use App\Models\WaAgentEvento;
use App\Models\WaAgentUsoModelo;
use App\Models\WhatsappMensaje;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WaAgentKpiService
{
    /** @var list<string> */
    public const CTX_HUMANO = ['manual_panel', 'app_whatsapp'];

    /** @var list<string> */
    public const CTX_AGENTE = ['wa_agent', 'whatsapp_auto_reply'];

    /**
     * @return array{desde: Carbon, hasta: Carbon, etiqueta: string, periodo: string}
     */
    public function resolverPeriodo(?string $periodo, ?string $desde = null, ?string $hasta = null): array
    {
        $periodo = $periodo ?: 'dia';
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

        // Día concreto: periodo=dia&fecha=YYYY-MM-DD
        if (in_array($periodo, ['dia', 'hoy'], true) && filled($desde) && blank($hasta)) {
            $d = Carbon::parse($desde)->startOfDay();

            return [
                'desde' => $d,
                'hasta' => $d->copy()->endOfDay(),
                'etiqueta' => $d->isToday() ? 'Hoy' : $d->format('d/m/Y'),
                'periodo' => 'dia',
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
     * Serie lineal: cantidad vs tiempo (hora / día / mes según el rango).
     *
     * @return array{
     *   granularidad: string,
     *   labels: list<string>,
     *   entradas: list<int>,
     *   humano: list<int>,
     *   agente: list<int>,
     *   sla_ok: list<int>,
     *   sla_fail: list<int>
     * }
     */
    public function serieLineal(Carbon $desde, Carbon $hasta, string $periodo, bool $incluirSandbox = false): array
    {
        $horas = $desde->diffInHours($hasta);
        $granularidad = match (true) {
            $periodo === 'dia' || $horas <= 36 => 'hora',
            $periodo === 'anio' || $desde->diffInDays($hasta) > 90 => 'mes',
            default => 'dia',
        };

        [$labels, $keys] = $this->bucketsLineales($desde, $hasta, $granularidad);
        $entradas = array_fill(0, count($keys), 0);
        $humano = array_fill(0, count($keys), 0);
        $agente = array_fill(0, count($keys), 0);
        $index = array_flip($keys);

        $q = WhatsappMensaje::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->whereIn('direccion', [
                WhatsappMensaje::DIRECCION_ENTRADA,
                WhatsappMensaje::DIRECCION_SALIDA,
            ]);
        if (! $incluirSandbox) {
            $q->where('telefono', 'not like', WhatsappMensaje::TELEFONO_SANDBOX_PREFIX.'%')
                ->where(function ($w) {
                    $w->whereNull('contexto_tipo')
                        ->orWhere('contexto_tipo', '!=', WhatsappMensaje::CONTEXTO_TEST_N8N);
                });
        }

        foreach ($q->get(['direccion', 'contexto_tipo', 'created_at']) as $m) {
            $key = $this->bucketKey(Carbon::parse($m->created_at), $granularidad);
            if (! isset($index[$key])) {
                continue;
            }
            $i = $index[$key];
            if ($m->direccion === WhatsappMensaje::DIRECCION_ENTRADA) {
                $entradas[$i]++;

                continue;
            }
            $ctx = (string) ($m->contexto_tipo ?? '');
            if (in_array($ctx, self::CTX_HUMANO, true)) {
                $humano[$i]++;
            } elseif (in_array($ctx, self::CTX_AGENTE, true)) {
                $agente[$i]++;
            }
        }

        $slaBuckets = $this->slaPorBucket($desde, $hasta, $granularidad, $keys, $incluirSandbox);

        return [
            'granularidad' => $granularidad,
            'labels' => $labels,
            'entradas' => $entradas,
            'humano' => $humano,
            'agente' => $agente,
            'sla_ok' => $slaBuckets['ok'],
            'sla_fail' => $slaBuckets['fail'],
        ];
    }

    /**
     * Primera respuesta (humano o agente) tras cada mensaje entrante.
     *
     * @return array<string, mixed>
     */
    public function slaPrimeraRespuesta(Carbon $desde, Carbon $hasta, bool $incluirSandbox = false): array
    {
        $metaSeg = (int) config('whatsapp.agent.kpi.sla_primera_respuesta_seg', 120);
        $lookAhead = $hasta->copy()->addDay();

        $q = WhatsappMensaje::query()
            ->whereBetween('created_at', [$desde, $lookAhead])
            ->where('created_at', '>=', $desde->copy()->subHours(2))
            ->whereIn('direccion', [
                WhatsappMensaje::DIRECCION_ENTRADA,
                WhatsappMensaje::DIRECCION_SALIDA,
            ])
            ->orderBy('telefono')
            ->orderBy('created_at')
            ->orderBy('id');
        if (! $incluirSandbox) {
            $q->where('telefono', 'not like', WhatsappMensaje::TELEFONO_SANDBOX_PREFIX.'%')
                ->where(function ($w) {
                    $w->whereNull('contexto_tipo')
                        ->orWhere('contexto_tipo', '!=', WhatsappMensaje::CONTEXTO_TEST_N8N);
                });
        }

        $porTel = [];
        foreach ($q->get(['id', 'telefono', 'direccion', 'contexto_tipo', 'created_at']) as $m) {
            $tel = (string) $m->telefono;
            $porTel[$tel][] = $m;
        }

        $segundos = [];
        $ok = 0;
        $fail = 0;
        $sinRespuesta = 0;
        $porQuien = ['humano' => 0, 'agente' => 0];
        $muestras = 0;

        foreach ($porTel as $msgs) {
            $n = count($msgs);
            for ($i = 0; $i < $n; $i++) {
                $msg = $msgs[$i];
                if ($msg->direccion !== WhatsappMensaje::DIRECCION_ENTRADA) {
                    continue;
                }
                $at = Carbon::parse($msg->created_at);
                if ($at->lt($desde) || $at->gt($hasta)) {
                    continue;
                }
                $muestras++;
                $reply = null;
                $quien = null;
                for ($j = $i + 1; $j < $n; $j++) {
                    $cand = $msgs[$j];
                    if ($cand->direccion !== WhatsappMensaje::DIRECCION_SALIDA) {
                        // Otra entrada del cliente antes de que contestemos: sigue esperando la misma cola
                        continue;
                    }
                    $ctx = (string) ($cand->contexto_tipo ?? '');
                    if (in_array($ctx, self::CTX_HUMANO, true)) {
                        $reply = $cand;
                        $quien = 'humano';
                        break;
                    }
                    if (in_array($ctx, self::CTX_AGENTE, true)) {
                        $reply = $cand;
                        $quien = 'agente';
                        break;
                    }
                }
                if (! $reply || ! $quien) {
                    $sinRespuesta++;

                    continue;
                }
                $sec = max(0, Carbon::parse($msg->created_at)->diffInSeconds(Carbon::parse($reply->created_at)));
                $segundos[] = $sec;
                $porQuien[$quien]++;
                if ($sec <= $metaSeg) {
                    $ok++;
                } else {
                    $fail++;
                }
            }
        }

        sort($segundos);
        $col = collect($segundos);
        $respondidas = $ok + $fail;
        $pctOk = $respondidas > 0 ? round(($ok / $respondidas) * 100, 1) : null;
        $pctOkSobreTodas = $muestras > 0 ? round(($ok / $muestras) * 100, 1) : null;

        return [
            'meta_seg' => $metaSeg,
            'meta_min' => round($metaSeg / 60, 1),
            'muestras' => $muestras,
            'respondidas' => $respondidas,
            'sin_respuesta' => $sinRespuesta,
            'ok' => $ok,
            'fail' => $fail,
            'pct_ok' => $pctOk,
            'pct_ok_todas' => $pctOkSobreTodas,
            'p50_seg' => $this->percentil($col, 50),
            'p95_seg' => $this->percentil($col, 95),
            'promedio_seg' => $col->isNotEmpty() ? (int) round($col->avg()) : null,
            'humano' => $porQuien['humano'],
            'agente' => $porQuien['agente'],
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
     * @return array{0: list<string>, 1: list<string>}
     */
    private function bucketsLineales(Carbon $desde, Carbon $hasta, string $granularidad): array
    {
        $labels = [];
        $keys = [];
        $cursor = $desde->copy();

        if ($granularidad === 'hora') {
            $cursor->startOfHour();
            $fin = $hasta->copy()->startOfHour();
            while ($cursor->lte($fin)) {
                $keys[] = $cursor->format('Y-m-d H');
                $labels[] = $cursor->format('H:00');
                $cursor->addHour();
            }
        } elseif ($granularidad === 'mes') {
            $cursor->startOfMonth();
            $fin = $hasta->copy()->startOfMonth();
            while ($cursor->lte($fin)) {
                $keys[] = $cursor->format('Y-m');
                $labels[] = $cursor->translatedFormat('M Y');
                $cursor->addMonth();
            }
        } else {
            $cursor->startOfDay();
            $fin = $hasta->copy()->startOfDay();
            while ($cursor->lte($fin)) {
                $keys[] = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $cursor->addDay();
            }
        }

        return [$labels, $keys];
    }

    private function bucketKey(Carbon $at, string $granularidad): string
    {
        return match ($granularidad) {
            'hora' => $at->format('Y-m-d H'),
            'mes' => $at->format('Y-m'),
            default => $at->format('Y-m-d'),
        };
    }

    /**
     * @param  list<string>  $keys
     * @return array{ok: list<int>, fail: list<int>}
     */
    private function slaPorBucket(Carbon $desde, Carbon $hasta, string $granularidad, array $keys, bool $incluirSandbox): array
    {
        $ok = array_fill(0, count($keys), 0);
        $fail = array_fill(0, count($keys), 0);
        $index = array_flip($keys);
        $metaSeg = (int) config('whatsapp.agent.kpi.sla_primera_respuesta_seg', 120);
        $lookAhead = $hasta->copy()->addDay();

        $q = WhatsappMensaje::query()
            ->whereBetween('created_at', [$desde->copy()->subHours(2), $lookAhead])
            ->whereIn('direccion', [
                WhatsappMensaje::DIRECCION_ENTRADA,
                WhatsappMensaje::DIRECCION_SALIDA,
            ])
            ->orderBy('telefono')
            ->orderBy('created_at')
            ->orderBy('id');
        if (! $incluirSandbox) {
            $q->where('telefono', 'not like', WhatsappMensaje::TELEFONO_SANDBOX_PREFIX.'%')
                ->where(function ($w) {
                    $w->whereNull('contexto_tipo')
                        ->orWhere('contexto_tipo', '!=', WhatsappMensaje::CONTEXTO_TEST_N8N);
                });
        }

        $porTel = [];
        foreach ($q->get(['telefono', 'direccion', 'contexto_tipo', 'created_at']) as $m) {
            $porTel[(string) $m->telefono][] = $m;
        }

        foreach ($porTel as $msgs) {
            $n = count($msgs);
            for ($i = 0; $i < $n; $i++) {
                $msg = $msgs[$i];
                if ($msg->direccion !== WhatsappMensaje::DIRECCION_ENTRADA) {
                    continue;
                }
                $at = Carbon::parse($msg->created_at);
                if ($at->lt($desde) || $at->gt($hasta)) {
                    continue;
                }
                $key = $this->bucketKey($at, $granularidad);
                if (! isset($index[$key])) {
                    continue;
                }
                $replyAt = null;
                for ($j = $i + 1; $j < $n; $j++) {
                    $cand = $msgs[$j];
                    if ($cand->direccion !== WhatsappMensaje::DIRECCION_SALIDA) {
                        continue;
                    }
                    $ctx = (string) ($cand->contexto_tipo ?? '');
                    if (in_array($ctx, self::CTX_HUMANO, true) || in_array($ctx, self::CTX_AGENTE, true)) {
                        $replyAt = Carbon::parse($cand->created_at);
                        break;
                    }
                }
                if (! $replyAt) {
                    $fail[$index[$key]]++;

                    continue;
                }
                $sec = max(0, $at->diffInSeconds($replyAt));
                if ($sec <= $metaSeg) {
                    $ok[$index[$key]]++;
                } else {
                    $fail[$index[$key]]++;
                }
            }
        }

        return ['ok' => $ok, 'fail' => $fail];
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
