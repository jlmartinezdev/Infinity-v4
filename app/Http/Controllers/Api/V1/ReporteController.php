<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FacturaInterna;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reportes agregados para integraciones (N8N, bots, etc.).
 */
class ReporteController extends ApiController
{
    /**
     * Clientes con facturas internas pendientes (saldo > 0), agrupados.
     *
     * Query params:
     * - solo_vencidos (default 1): solo con al menos una factura vencida
     * - con_telefono (default 1): solo con teléfono no vacío
     * - min_dias_mora (default 1): días desde la fecha de vencimiento más antigua
     * - max_dias_mora: opcional
     * - min_saldo: opcional
     * - solo_cobrables (default 1): cliente no inactivo y con servicio no cancelado
     * - incluir_facturas (default 0): detalle de facturas por cliente
     * - per_page (1–200, default 50), page
     */
    public function morosos(Request $request)
    {
        $soloVencidos = $this->boolParam($request, 'solo_vencidos', true);
        $conTelefono = $this->boolParam($request, 'con_telefono', true);
        $soloCobrables = $this->boolParam($request, 'solo_cobrables', true);
        $incluirFacturas = $this->boolParam($request, 'incluir_facturas', false);
        $minDiasMora = max(0, (int) $request->input('min_dias_mora', 1));
        $maxDiasMora = $request->filled('max_dias_mora') ? max(0, (int) $request->input('max_dias_mora')) : null;
        $minSaldo = $request->filled('min_saldo') ? (float) str_replace(',', '.', (string) $request->input('min_saldo')) : null;
        $perPage = min(200, max(1, (int) $request->input('per_page', 50)));
        $page = max(1, (int) $request->input('page', 1));

        $hoy = now()->toDateString();
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();

        $inner = FacturaInterna::query()
            ->whereIn('factura_internas.estado', ['pendiente', 'emitida'])
            ->whereRaw($saldoExpr.' > 0.00001')
            ->select([
                'factura_internas.id',
                'factura_internas.cliente_id',
                'factura_internas.fecha_vencimiento',
                'factura_internas.periodo_desde',
                'factura_internas.periodo_hasta',
                'factura_internas.total',
                'factura_internas.moneda',
                DB::raw('('.$saldoExpr.') as saldo_calc'),
            ]);

        if ($soloCobrables) {
            $inner->whereRaw(FacturaInterna::sqlClienteCuentaEnTotalPendiente('factura_internas.cliente_id'));
        }

        if ($soloVencidos) {
            $inner->whereNotNull('factura_internas.fecha_vencimiento')
                ->whereDate('factura_internas.fecha_vencimiento', '<', $hoy);
        }

        $grouped = DB::query()
            ->fromSub($inner, 'fi')
            ->join('clientes as c', 'c.cliente_id', '=', 'fi.cliente_id')
            ->select([
                'fi.cliente_id',
                DB::raw("TRIM(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido,''))) as nombre"),
                'c.cedula',
                'c.telefono',
                'c.estado',
                'c.calificacion_pago',
                DB::raw('COUNT(*) as facturas_count'),
                DB::raw('SUM(fi.saldo_calc) as saldo_pendiente'),
                DB::raw('MIN(fi.fecha_vencimiento) as fecha_vencimiento_mas_antigua'),
                DB::raw('MAX(fi.fecha_vencimiento) as fecha_vencimiento_mas_reciente'),
                DB::raw("GROUP_CONCAT(fi.id ORDER BY fi.fecha_vencimiento IS NULL ASC, fi.fecha_vencimiento ASC, fi.id ASC SEPARATOR ',') as factura_ids_csv"),
                DB::raw('MAX(fi.moneda) as moneda'),
                DB::raw("DATEDIFF('{$hoy}', MIN(fi.fecha_vencimiento)) as dias_mora"),
            ])
            ->groupBy([
                'fi.cliente_id',
                'c.nombre',
                'c.apellido',
                'c.cedula',
                'c.telefono',
                'c.estado',
                'c.calificacion_pago',
            ]);

        if ($conTelefono) {
            $grouped->whereNotNull('c.telefono')
                ->whereRaw("TRIM(c.telefono) != ''");
        }

        $grouped->havingRaw("DATEDIFF('{$hoy}', MIN(fi.fecha_vencimiento)) >= ?", [$minDiasMora]);

        if ($maxDiasMora !== null) {
            $grouped->havingRaw("DATEDIFF('{$hoy}', MIN(fi.fecha_vencimiento)) <= ?", [$maxDiasMora]);
        }

        if ($minSaldo !== null && $minSaldo > 0) {
            $grouped->havingRaw('SUM(fi.saldo_calc) >= ?', [$minSaldo]);
        }

        $grouped->orderByDesc('dias_mora')
            ->orderByDesc('saldo_pendiente');

        $total = (int) DB::query()
            ->fromSub(clone $grouped, 'm')
            ->count();

        $lastPage = $total > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
        $page = min($page, $lastPage);

        $rows = (clone $grouped)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $allFacturaIds = [];
        foreach ($rows as $r) {
            if (! empty($r->factura_ids_csv)) {
                foreach (explode(',', (string) $r->factura_ids_csv) as $part) {
                    $part = trim($part);
                    if ($part !== '' && ctype_digit($part)) {
                        $allFacturaIds[] = (int) $part;
                    }
                }
            }
        }
        $allFacturaIds = array_values(array_unique($allFacturaIds));

        $facturasById = collect();
        if ($incluirFacturas && $allFacturaIds !== []) {
            $facturasById = FacturaInterna::query()
                ->whereIn('id', $allFacturaIds)
                ->orderBy('fecha_vencimiento')
                ->orderBy('id')
                ->get()
                ->keyBy('id');
        }

        $items = $rows->map(function ($r) use ($incluirFacturas, $facturasById, $hoy) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($r->factura_ids_csv ?? '')))));
            $diasMora = (int) ($r->dias_mora ?? 0);
            if ($diasMora < 0) {
                $diasMora = 0;
            }

            $item = [
                'cliente_id' => (int) $r->cliente_id,
                'nombre' => trim((string) ($r->nombre ?? '')),
                'cedula' => $r->cedula,
                'telefono' => $r->telefono ? trim((string) $r->telefono) : null,
                'estado' => $r->estado,
                'calificacion_pago' => $r->calificacion_pago,
                'saldo_pendiente' => round((float) $r->saldo_pendiente, 2),
                'moneda' => (string) ($r->moneda ?? 'PYG'),
                'facturas_count' => (int) $r->facturas_count,
                'factura_ids' => $ids,
                'fecha_vencimiento_mas_antigua' => $r->fecha_vencimiento_mas_antigua
                    ? substr((string) $r->fecha_vencimiento_mas_antigua, 0, 10)
                    : null,
                'fecha_vencimiento_mas_reciente' => $r->fecha_vencimiento_mas_reciente
                    ? substr((string) $r->fecha_vencimiento_mas_reciente, 0, 10)
                    : null,
                'dias_mora' => $diasMora,
            ];

            if ($incluirFacturas) {
                $hoyCarbon = Carbon::parse($hoy)->startOfDay();
                $item['facturas'] = collect($ids)
                    ->map(function (int $id) use ($facturasById, $hoyCarbon) {
                        $f = $facturasById->get($id);
                        if (! $f) {
                            return null;
                        }
                        $ven = $f->fecha_vencimiento?->toDateString();
                        $dias = 0;
                        if ($f->fecha_vencimiento) {
                            $venc = $f->fecha_vencimiento->copy()->startOfDay();
                            if ($venc->lt($hoyCarbon)) {
                                $dias = (int) $venc->diffInDays($hoyCarbon);
                            }
                        }

                        return [
                            'id' => $f->id,
                            'saldo_pendiente' => (float) $f->saldo_pendiente,
                            'total' => (float) $f->total,
                            'fecha_vencimiento' => $ven,
                            'periodo_desde' => $f->periodo_desde?->toDateString(),
                            'periodo_hasta' => $f->periodo_hasta?->toDateString(),
                            'dias_mora' => $dias,
                            'estado' => $f->estado,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }

            return $item;
        })->values()->all();

        return $this->ok([
            'items' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? null : (($page - 1) * $perPage + 1),
                'to' => $total === 0 ? null : min($page * $perPage, $total),
            ],
            'filtros' => [
                'solo_vencidos' => $soloVencidos,
                'con_telefono' => $conTelefono,
                'solo_cobrables' => $soloCobrables,
                'min_dias_mora' => $minDiasMora,
                'max_dias_mora' => $maxDiasMora,
                'min_saldo' => $minSaldo,
                'incluir_facturas' => $incluirFacturas,
                'fecha_referencia' => $hoy,
            ],
        ]);
    }

    private function boolParam(Request $request, string $key, bool $default): bool
    {
        if (! $request->has($key)) {
            return $default;
        }

        $v = $request->input($key);
        if (is_bool($v)) {
            return $v;
        }

        $s = strtolower(trim((string) $v));

        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}
