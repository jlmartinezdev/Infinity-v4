<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Models\AjustesGenerales;
use App\Models\Cobro;
use App\Models\Cliente;
use App\Models\FacturaDetalle;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\Impuesto;
use App\Models\Nodo;
use App\Models\Servicio;
use App\Services\FacturacionService;
use App\Services\Tpago\TpagoPaymentLinkService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacturaInternaController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);

        return view('factura-internas.index', compact('clientes'));
    }

    /**
     * Listado paginado para la SPA Vue (filtros + búsqueda).
     */
    public function list(Request $request)
    {
        $query = FacturaInterna::query()
            ->with(['cliente'])
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_emision', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->hasta);
        }
        if ($request->boolean('pendiente_saldo_cero')) {
            $query->whereIn('factura_internas.estado', ['pendiente', 'emitida'])
                ->whereRaw(FacturaInterna::sqlSaldoPendienteExpr().' <= 0.00001');
        }
        $busqueda = trim($request->input('q', ''));
        if ($busqueda !== '') {
            $raw = $busqueda;
            $term = '%'.addcslashes($raw, '%_\\').'%';
            $query->where(function ($w) use ($raw, $term) {
                if (ctype_digit($raw)) {
                    $w->where('factura_internas.id', (int) $raw);
                }
                $w->orWhereHas('cliente', function ($cq) use ($term) {
                    $cq->where('nombre', 'like', $term)
                        ->orWhere('apellido', 'like', $term)
                        ->orWhere('cedula', 'like', $term);
                });
            });
        }

        $statsQuery = (clone $query)->reorder();
        $saldoSql = FacturaInterna::sqlSaldoPendienteExpr();
        $cuentaPendiente = FacturaInterna::sqlClienteCuentaEnTotalPendiente('factura_internas.cliente_id');
        $totalPendiente = (float) (clone $statsQuery)
            ->selectRaw("SUM(CASE WHEN {$cuentaPendiente} THEN {$saldoSql} ELSE 0 END) as total_pendiente")
            ->value('total_pendiente');
        $cantidadFacturas = (int) (clone $statsQuery)->count('factura_internas.id');
        $totalGenerado = (float) (clone $statsQuery)
            ->selectRaw('SUM(factura_internas.total) as total_generado')
            ->value('total_generado');

        $perPage = min(50, max(5, (int) $request->get('per_page', 15)));
        $paginator = $query->paginate($perPage);

        $paginator->through(function (FacturaInterna $f) {
            $saldo = (float) $f->saldo_pendiente;
            $puedeNotaCredito = in_array($f->estado, ['pendiente', 'emitida'], true) && $saldo > 0.00001;

            return [
                'id' => $f->id,
                'cliente_id' => $f->cliente_id,
                'cliente_nombre' => trim(($f->cliente->nombre ?? '').' '.($f->cliente->apellido ?? '')),
                'cliente_cedula' => $f->cliente->cedula ?? null,
                'tipo_factura' => $f->tipo_factura ?? FacturaInterna::TIPO_SERVICIO,
                'tipo_factura_etiqueta' => $f->etiquetaTipoFactura(),
                'periodo_desde' => $f->periodo_desde?->format('Y-m-d'),
                'periodo_hasta' => $f->periodo_hasta?->format('Y-m-d'),
                'fecha_emision' => $f->fecha_emision?->format('Y-m-d'),
                'estado' => $f->estado,
                'total' => (float) $f->total,
                'saldo_pendiente' => $saldo,
                'moneda' => $f->moneda ?? 'PYG',
                'puede_emitir_nota_credito' => $puedeNotaCredito,
            ];
        });

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => [
                'cantidad_facturas' => $cantidadFacturas,
                'total_pendiente' => $totalPendiente,
                'total_generado' => $totalGenerado,
            ],
        ]);
    }

    /**
     * Ejecuta el comando Artisan crear-factura-internas (facturas automáticas del mes, con --force).
     */
    public function ejecutarCrearFacturaInternas()
    {
        try {
            Artisan::call('crear-factura-internas');
            $output = trim(Artisan::output());
            $resumen = $output !== ''
                ? Str::limit(preg_replace('/\s+/', ' ', $output), 500, '…')
                : 'Sin salida del comando.';

            return redirect()
                ->route('factura-internas.index')
                ->with('success', 'Tarea crear-factura-internas ejecutada. '.$resumen);
        } catch (\Throwable $e) {
            return redirect()
                ->route('factura-internas.index')
                ->with('error', 'No se pudo ejecutar la tarea: '.$e->getMessage());
        }
    }

    /**
     * Lista de facturas internas con saldo pendiente de pago (para cobro).
     * Vista SPA Vue; datos JSON en pendientesList().
     */
    public function pendientes(Request $request)
    {
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('factura-internas.pendientes', compact('nodos'));
    }

    /**
     * Listado paginado JSON para la vista Vue (filtros, orden y paginación).
     * Una fila por cliente: facturas pendientes del mismo cliente unificadas.
     */
    public function pendientesList(Request $request)
    {
        $cobradoExpr = 'LEAST(factura_internas.total, '.FacturaInterna::sqlSumCobros().')';
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $promExpr = '(SELECT MAX(vencimiento_at) FROM promesa_pagos pp WHERE pp.factura_interna_id = factura_internas.id)';

        $inner = $this->facturasPendientesQuery($request);
        $inner->select([
            'factura_internas.id',
            'factura_internas.cliente_id',
            'factura_internas.total',
            'factura_internas.periodo_desde',
            'factura_internas.periodo_hasta',
            'factura_internas.fecha_vencimiento',
            'factura_internas.moneda',
            DB::raw('('.$cobradoExpr.') as cobrado_calc'),
            DB::raw('('.$saldoExpr.') as saldo_calc'),
            DB::raw('('.$promExpr.') as prom_calc'),
        ]);

        $cuentaSaldoPendiente = FacturaInterna::sqlClienteCuentaEnTotalPendiente('fi_stats.cliente_id');
        $statsBase = DB::query()
            ->fromSub(clone $inner, 'fi_stats')
            ->selectRaw("
                COUNT(*) as cantidad_facturas,
                COUNT(DISTINCT fi_stats.cliente_id) as cantidad_clientes,
                COALESCE(SUM(fi_stats.total), 0) as monto_total,
                COALESCE(SUM(fi_stats.cobrado_calc), 0) as monto_cobrado,
                COALESCE(SUM(CASE WHEN {$cuentaSaldoPendiente} THEN fi_stats.saldo_calc ELSE 0 END), 0) as monto_saldo
            ")
            ->first();
        $hoy = now()->toDateString();
        $cuentaSaldoVencido = FacturaInterna::sqlClienteCuentaEnTotalPendiente('fi_venc.cliente_id');
        $statsVencidos = DB::query()
            ->fromSub(clone $inner, 'fi_venc')
            ->whereNotNull('fi_venc.fecha_vencimiento')
            ->whereDate('fi_venc.fecha_vencimiento', '<', $hoy)
            ->selectRaw("
                COUNT(*) as facturas_vencidas,
                COUNT(DISTINCT fi_venc.cliente_id) as clientes_vencidos,
                COALESCE(SUM(CASE WHEN {$cuentaSaldoVencido} THEN fi_venc.saldo_calc ELSE 0 END), 0) as saldo_vencido
            ")
            ->first();

        $perPage = 20;
        $page = max(1, (int) $request->input('page', 1));

        $totalClientes = (int) DB::query()
            ->fromSub(clone $inner, 'fi_cnt')
            ->selectRaw('COUNT(DISTINCT fi_cnt.cliente_id) as c')
            ->value('c');

        $lastPage = $totalClientes > 0 ? max(1, (int) ceil($totalClientes / $perPage)) : 1;
        $page = min($page, $lastPage);

        $grouped = DB::query()
            ->fromSub($inner, 'fi')
            ->select([
                'fi.cliente_id',
                DB::raw('COUNT(*) as facturas_count'),
                DB::raw('MIN(fi.id) as min_factura_id'),
                DB::raw('SUM(fi.total) as sum_total'),
                DB::raw('SUM(fi.cobrado_calc) as sum_cobrado'),
                DB::raw('SUM(fi.saldo_calc) as sum_saldo'),
                DB::raw('MIN(fi.fecha_vencimiento) as min_fecha_vencimiento'),
                DB::raw('MAX(fi.fecha_vencimiento) as max_fecha_vencimiento'),
                DB::raw('MIN(fi.periodo_desde) as min_periodo_desde'),
                DB::raw('MAX(fi.periodo_hasta) as max_periodo_hasta'),
                DB::raw('MAX(fi.moneda) as moneda'),
                DB::raw("GROUP_CONCAT(fi.id ORDER BY fi.fecha_vencimiento IS NULL ASC, fi.fecha_vencimiento ASC, fi.id ASC SEPARATOR ',') as factura_ids_csv"),
                DB::raw('MAX(fi.prom_calc) as max_prom_calc'),
            ])
            ->groupBy('fi.cliente_id');

        $this->applyPendientesAgrupadoOrden($request, $grouped);

        $slice = (clone $grouped)->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $allFacturaIds = [];
        foreach ($slice as $r) {
            if (! empty($r->factura_ids_csv)) {
                foreach (explode(',', (string) $r->factura_ids_csv) as $idPart) {
                    $idPart = trim($idPart);
                    if ($idPart !== '' && ctype_digit($idPart)) {
                        $allFacturaIds[] = (int) $idPart;
                    }
                }
            }
        }
        $allFacturaIds = array_values(array_unique($allFacturaIds));

        $facturasCargadas = collect();
        if ($allFacturaIds !== []) {
            $facturasCargadas = FacturaInterna::query()
                ->whereIn('id', $allFacturaIds)
                ->with(['cliente', 'promesaPago'])
                ->get()
                ->keyBy('id');
        }

        $user = auth()->user();

        $clienteIdsPagina = $slice->pluck('cliente_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $conServicioActivo = $this->clienteIdsConServicioActivo($clienteIdsPagina);

        $data = $slice->map(function ($g) use ($facturasCargadas, $user, $conServicioActivo) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $g->factura_ids_csv))));
            $facturas = collect($ids)
                ->map(fn (int $id) => $facturasCargadas->get($id))
                ->filter()
                ->values();
            $c = $facturas->first()?->cliente;
            $contacto = [
                'cliente_id' => (int) $g->cliente_id,
                'nombre' => $c ? trim(($c->nombre ?? '').' '.($c->apellido ?? '')) : '',
                'cedula' => $c?->cedula ?? '',
                'celular' => $c?->telefono ?? '',
                'email' => $c?->email ?? '',
                'direccion' => $c?->direccion ?? '',
                'url_ubicacion' => $c?->url_ubicacion ?? '',
                'detalle_url' => ($c && $user?->tienePermiso('clientes.ver'))
                    ? route('clientes.detalle', $c)
                    : '',
            ];

            $promesaLabel = null;
            $soonest = null;
            foreach ($facturas as $f) {
                if ($f->promesaPago && $f->promesaPago->vencimiento_at) {
                    $at = $f->promesaPago->vencimiento_at->timezone(config('app.timezone'));
                    if ($soonest === null || $at->lt($soonest)) {
                        $soonest = $at->copy();
                        $promesaLabel = 'Hasta '.$at->format('d/m/Y H:i');
                    }
                }
            }

            return [
                'cliente_id' => (int) $g->cliente_id,
                'min_factura_id' => (int) $g->min_factura_id,
                'facturas_count' => (int) $g->facturas_count,
                'factura_ids' => $ids,
                'cliente_nombre' => $c ? trim(($c->nombre ?? '').' '.($c->apellido ?? '')) : '',
                'cliente_dado_baja' => $this->clienteEsDadoDeBaja($c, $conServicioActivo),
                'periodo_desde' => $g->min_periodo_desde ? Carbon::parse($g->min_periodo_desde)->format('Y-m-d') : null,
                'periodo_hasta' => $g->max_periodo_hasta ? Carbon::parse($g->max_periodo_hasta)->format('Y-m-d') : null,
                'fecha_vencimiento' => $g->min_fecha_vencimiento ? Carbon::parse($g->min_fecha_vencimiento)->format('Y-m-d') : null,
                'fecha_vencimiento_max' => $g->max_fecha_vencimiento ? Carbon::parse($g->max_fecha_vencimiento)->format('Y-m-d') : null,
                'total' => (float) $g->sum_total,
                'monto_pagado' => (float) $g->sum_cobrado,
                'saldo_pendiente' => (float) $g->sum_saldo,
                'moneda' => (string) ($g->moneda ?? 'PYG'),
                'promesa_label' => $promesaLabel,
                'contacto_cliente' => $contacto,
                'facturas' => $facturas->map(function (FacturaInterna $f) {
                    return [
                        'id' => $f->id,
                        'periodo_desde' => $f->periodo_desde?->format('Y-m-d'),
                        'periodo_hasta' => $f->periodo_hasta?->format('Y-m-d'),
                        'fecha_vencimiento' => $f->fecha_vencimiento?->format('Y-m-d'),
                        'total' => (float) $f->total,
                        'monto_pagado' => (float) $f->monto_pagado,
                        'saldo_pendiente' => (float) $f->saldo_pendiente,
                        'moneda' => $f->moneda ?? 'PYG',
                        'promesa_label' => $f->promesaPago
                            ? 'Hasta '.$f->promesaPago->vencimiento_at->timezone(config('app.timezone'))->format('d/m/Y H:i')
                            : null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $totalClientes,
                'from' => $totalClientes === 0 ? null : (($page - 1) * $perPage + 1),
                'to' => $totalClientes === 0 ? null : min($page * $perPage, $totalClientes),
            ],
            'stats' => [
                'cantidad_facturas' => (int) ($statsBase->cantidad_facturas ?? 0),
                'cantidad_clientes' => (int) ($statsBase->cantidad_clientes ?? 0),
                'monto_total' => (float) ($statsBase->monto_total ?? 0),
                'monto_cobrado' => (float) ($statsBase->monto_cobrado ?? 0),
                'monto_saldo' => (float) ($statsBase->monto_saldo ?? 0),
                'facturas_vencidas' => (int) ($statsVencidos->facturas_vencidas ?? 0),
                'clientes_vencidos' => (int) ($statsVencidos->clientes_vencidos ?? 0),
                'saldo_vencido' => (float) ($statsVencidos->saldo_vencido ?? 0),
            ],
        ]);
    }

    /**
     * Clientes con saldo pendiente que tengan coordenadas extraíbles desde url_ubicacion (mismos filtros que el listado).
     * No resuelve enlaces cortos de Maps por rendimiento; usar URL o coordenadas completas en la ficha del cliente.
     */
    public function pendientesMapaPuntos(Request $request)
    {
        $cobradoExpr = 'LEAST(factura_internas.total, '.FacturaInterna::sqlSumCobros().')';
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $promExpr = '(SELECT MAX(vencimiento_at) FROM promesa_pagos pp WHERE pp.factura_interna_id = factura_internas.id)';

        $inner = $this->facturasPendientesQuery($request);
        $inner->select([
            'factura_internas.id',
            'factura_internas.cliente_id',
            'factura_internas.total',
            'factura_internas.periodo_desde',
            'factura_internas.periodo_hasta',
            'factura_internas.fecha_vencimiento',
            'factura_internas.moneda',
            DB::raw('('.$cobradoExpr.') as cobrado_calc'),
            DB::raw('('.$saldoExpr.') as saldo_calc'),
            DB::raw('('.$promExpr.') as prom_calc'),
        ]);

        $grouped = DB::query()
            ->fromSub($inner, 'fi')
            ->select([
                'fi.cliente_id',
                DB::raw('SUM(fi.saldo_calc) as sum_saldo'),
                DB::raw('MAX(fi.moneda) as moneda'),
            ])
            ->groupBy('fi.cliente_id');

        $this->applyPendientesAgrupadoOrden($request, $grouped);

        $rows = $grouped->get();
        if ($rows->isEmpty()) {
            return response()->json([
                'puntos' => [],
                'stats_mapa' => [
                    'total_clientes' => 0,
                    'con_coordenadas' => 0,
                    'sin_coordenadas' => 0,
                ],
            ]);
        }

        $clienteIds = $rows->pluck('cliente_id')->unique()->filter()->map(fn ($id) => (int) $id)->values()->all();
        $clientes = Cliente::query()
            ->whereIn('cliente_id', $clienteIds)
            ->get(['cliente_id', 'nombre', 'apellido', 'direccion', 'url_ubicacion']);

        $byId = $clientes->keyBy('cliente_id');

        $puntos = [];
        $sinCoord = 0;
        foreach ($rows as $r) {
            $cid = (int) $r->cliente_id;
            $c = $byId->get($cid);
            $url = trim((string) ($c?->url_ubicacion ?? ''));
            $coords = MapsUrlHelper::extractLatLonFromMapsUrl($url !== '' ? $url : null, false);
            if ($coords['lat'] === null || $coords['lon'] === null) {
                $sinCoord++;

                continue;
            }
            $nombre = $c ? trim(($c->nombre ?? '').' '.($c->apellido ?? '')) : '';
            $puntos[] = [
                'cliente_id' => $cid,
                'lat' => $coords['lat'],
                'lon' => $coords['lon'],
                'nombre' => $nombre,
                'saldo_pendiente' => (float) $r->sum_saldo,
                'moneda' => (string) ($r->moneda ?? 'PYG'),
                'direccion' => $c?->direccion ?? '',
                'url_ubicacion' => $url,
            ];
        }

        return response()->json([
            'puntos' => $puntos,
            'stats_mapa' => [
                'total_clientes' => $rows->count(),
                'con_coordenadas' => count($puntos),
                'sin_coordenadas' => $sinCoord,
            ],
        ]);
    }

    /**
     * PDF con todas las facturas internas pendientes de un cliente (una sección por factura).
     */
    public function pdfPendientesPorCliente(Cliente $cliente)
    {
        $facturas = FacturaInterna::query()
            ->where('factura_internas.cliente_id', $cliente->cliente_id)
            ->whereIn('factura_internas.estado', ['pendiente', 'emitida'])
            ->whereRaw('factura_internas.total > COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)')
            ->with(['cliente', 'detalles.impuesto', 'usuario', 'cobros'])
            ->orderByRaw('factura_internas.fecha_vencimiento IS NULL ASC')
            ->orderBy('factura_internas.fecha_vencimiento')
            ->orderBy('factura_internas.id')
            ->get();

        if ($facturas->isEmpty()) {
            abort(404);
        }

        $ajustes = AjustesGenerales::obtener();

        $logoBase64 = null;
        if ($ajustes && $ajustes->logo && Storage::disk('public')->exists($ajustes->logo)) {
            $mime = Storage::disk('public')->mimeType($ajustes->logo) ?? 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($ajustes->logo));
        }

        $moneda = (string) ($facturas->first()->moneda ?? 'PYG');
        $resumenTotales = [
            'moneda' => $moneda,
            'sum_total_facturado' => (float) $facturas->sum(fn (FacturaInterna $f) => (float) $f->total),
            'sum_monto_cobrado' => (float) $facturas->sum(fn (FacturaInterna $f) => $f->monto_pagado),
            'sum_saldo_pendiente' => (float) $facturas->sum(fn (FacturaInterna $f) => $f->saldo_pendiente),
        ];

        $nombreArchivo = 'facturas-pendientes-cliente-'.$cliente->cliente_id.'.pdf';

        return Pdf::loadView('factura-internas.pdf-pendientes-cliente', [
            'facturas' => $facturas,
            'ajustes' => $ajustes,
            'logoBase64' => $logoBase64,
            'resumenTotales' => $resumenTotales,
        ])->setPaper('a4', 'portrait')->download($nombreArchivo);
    }

    /**
     * Exportar pendientes de pago a Excel (CSV UTF-8 con separador ;), mismo criterio y filtro de búsqueda que el listado.
     *
     * Columnas: nombre cliente, monto deuda, dirección, celular, fecha instalación (servicios del cliente).
     */
    public function exportarPendientesExcel(Request $request): Response
    {
        $query = $this->facturasPendientesQuery($request);
        $this->applyFacturasPendientesOrden($request, $query);
        $facturas = $query->with(['cliente.servicios'])->get();

        $baseFilename = 'pagos-pendientes-'.now()->format('Y-m-d-His');

        $headers = [
            'Nombre cliente',
            'Monto deuda',
            'Dirección',
            'Celular',
            'Fecha instalación',
        ];
        $rows = [];
        foreach ($facturas as $f) {
            $c = $f->cliente;
            $rows[] = [
                $c ? trim(($c->nombre ?? '').' '.($c->apellido ?? '')) : '',
                number_format((float) $f->saldo_pendiente, 0, ',', '.').' '.($f->moneda ?? ''),
                $c?->direccion ?? '',
                $c?->telefono ?? '',
                $this->fechaInstalacionMasAntigua($c) ?? '',
            ];
        }

        if (class_exists(\ZipArchive::class)) {
            $tmpPath = $this->crearXlsxSimple($headers, $rows);

            return response()->download(
                $tmpPath,
                $baseFilename.'.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend(true);
        }

        // Fallback para servidores sin extension zip: Excel abre este .xls sin problemas.
        return response()->streamDownload(function () use ($headers, $rows) {
            echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
            echo '<tr>';
            foreach ($headers as $h) {
                echo '<th>'.$this->xlsxXml((string) $h).'</th>';
            }
            echo '</tr>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>'.$this->xlsxXml((string) ($cell ?? '')).'</td>';
                }
                echo '</tr>';
            }
            echo '</table></body></html>';
        }, $baseFilename.'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$baseFilename.'.xls"',
        ]);
    }

    private function crearXlsxSimple(array $headers, array $rows): string
    {
        $tmpXlsx = tempnam(sys_get_temp_dir(), 'pp_xlsx_');
        if ($tmpXlsx === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para XLSX.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($tmpXlsx, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo inicializar el archivo XLSX.');
        }

        $sheetRows = [];
        $sheetRows[] = $this->xlsxRowXml(1, $headers);
        foreach ($rows as $index => $row) {
            $sheetRows[] = $this->xlsxRowXml($index + 2, $row);
        }
        $sheetData = implode('', $sheetRows);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>');

        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Infinity ISP</Application>
</Properties>');

        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:creator>Infinity ISP</dc:creator>
  <cp:lastModifiedBy>Infinity ISP</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">'.now()->toAtomString().'</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">'.now()->toAtomString().'</dcterms:modified>
</cp:coreProperties>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Pendientes" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>'.$sheetData.'</sheetData>
</worksheet>');

        $zip->close();

        return $tmpXlsx;
    }

    private function xlsxRowXml(int $rowNumber, array $values): string
    {
        $cells = '';
        foreach (array_values($values) as $colIndex => $value) {
            $cellRef = $this->xlsxColumnLetter($colIndex + 1).$rowNumber;
            $safe = $this->xlsxXml((string) ($value ?? ''));
            $cells .= '<c r="'.$cellRef.'" t="inlineStr"><is><t xml:space="preserve">'.$safe.'</t></is></c>';
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private function xlsxColumnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = (int) floor(($index - $mod) / 26);
        }

        return $letters;
    }

    private function xlsxXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Consulta base: facturas internas pendientes con saldo > 0.
     */
    private function facturasPendientesQuery(Request $request)
    {
        $query = FacturaInterna::query()
            ->whereIn('factura_internas.estado', ['pendiente', 'emitida'])
            ->whereRaw(FacturaInterna::sqlSaldoPendienteExpr().' > 0.00001');

        if ($request->filled('buscar')) {
            $term = '%'.trim($request->buscar).'%';
            $query->whereHas('cliente', function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                    ->orWhere('apellido', 'like', $term)
                    ->orWhere('cedula', 'like', $term);
            });
        }

        if ($request->filled('nodo_id')) {
            $nodoId = (int) $request->nodo_id;
            if ($nodoId > 0) {
                $query->whereHas('cliente.servicios.pool.router', function ($q) use ($nodoId) {
                    $q->where('nodo_id', $nodoId);
                });
            }
        }

        $this->applyFacturasPendientesFiltrosColumna($request, $query);

        return $query;
    }

    /**
     * Filtros opcionales por columna (query string pf_*), usados desde la lista pendientes de pago.
     */
    private function applyFacturasPendientesFiltrosColumna(Request $request, $query): void
    {
        if ($request->filled('pf_id')) {
            $raw = trim((string) $request->pf_id);
            if ($raw !== '') {
                if (ctype_digit($raw)) {
                    $query->where('factura_internas.id', (int) $raw);
                } else {
                    $query->whereRaw('CAST(factura_internas.id AS CHAR) LIKE ?', ['%'.$raw.'%']);
                }
            }
        }

        if ($request->filled('pf_cliente')) {
            $term = '%'.Str::limit(trim((string) $request->pf_cliente), 200, '').'%';
            $query->whereHas('cliente', function ($q) use ($term) {
                $q->whereRaw("CONCAT(COALESCE(nombre,''), ' ', COALESCE(apellido,'')) LIKE ?", [$term])
                    ->orWhere('cedula', 'like', $term);
            });
        }

        try {
            if ($request->filled('pf_per_desde')) {
                $query->where('factura_internas.periodo_hasta', '>=', Carbon::parse($request->pf_per_desde)->startOfDay());
            }
            if ($request->filled('pf_per_hasta')) {
                $query->where('factura_internas.periodo_desde', '<=', Carbon::parse($request->pf_per_hasta)->endOfDay());
            }
        } catch (\Throwable) {
            // fechas inválidas: se ignoran
        }

        try {
            if ($request->filled('pf_ven_desde')) {
                $query->whereNotNull('factura_internas.fecha_vencimiento')
                    ->whereDate('factura_internas.fecha_vencimiento', '>=', Carbon::parse($request->pf_ven_desde)->toDateString());
            }
            if ($request->filled('pf_ven_hasta')) {
                $query->whereNotNull('factura_internas.fecha_vencimiento')
                    ->whereDate('factura_internas.fecha_vencimiento', '<=', Carbon::parse($request->pf_ven_hasta)->toDateString());
            }
        } catch (\Throwable) {
        }

        if ($request->filled('pf_total_min')) {
            $query->where('factura_internas.total', '>=', (float) str_replace(',', '.', (string) $request->pf_total_min));
        }
        if ($request->filled('pf_total_max')) {
            $query->where('factura_internas.total', '<=', (float) str_replace(',', '.', (string) $request->pf_total_max));
        }

        $cobradoExpr = 'LEAST(factura_internas.total, '.FacturaInterna::sqlSumCobros().')';
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();

        if ($request->filled('pf_cob_min')) {
            $query->whereRaw($cobradoExpr.' >= ?', [(float) str_replace(',', '.', (string) $request->pf_cob_min)]);
        }
        if ($request->filled('pf_cob_max')) {
            $query->whereRaw($cobradoExpr.' <= ?', [(float) str_replace(',', '.', (string) $request->pf_cob_max)]);
        }

        if ($request->filled('pf_saldo_min')) {
            $query->whereRaw($saldoExpr.' >= ?', [(float) str_replace(',', '.', (string) $request->pf_saldo_min)]);
        }
        if ($request->filled('pf_saldo_max')) {
            $query->whereRaw($saldoExpr.' <= ?', [(float) str_replace(',', '.', (string) $request->pf_saldo_max)]);
        }

        $prom = $request->input('pf_promesa');
        if ($prom === 'con') {
            $query->whereHas('promesaPago');
        } elseif ($prom === 'sin') {
            $query->whereDoesntHave('promesaPago');
        }
    }

    private function fechaInstalacionMasAntigua(?Cliente $cliente): ?string
    {
        if (! $cliente) {
            return null;
        }
        $min = null;
        foreach ($cliente->servicios as $servicio) {
            $fi = $servicio->fecha_instalacion;
            if (! $fi instanceof CarbonInterface) {
                continue;
            }
            if ($min === null || $fi->lt($min)) {
                $min = $fi->copy();
            }
        }

        return $min?->format('d/m/Y');
    }

    /**
     * Orden del listado agrupado por cliente (subconsulta alias fi).
     */
    private function applyPendientesAgrupadoOrden(Request $request, $query): void
    {
        $allowed = ['id', 'cliente', 'periodo', 'vencimiento', 'total', 'cobrado', 'saldo', 'promesa'];
        $sort = $request->input('sort', 'vencimiento');
        if (! in_array($sort, $allowed, true)) {
            $sort = 'vencimiento';
        }
        $dir = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        // MariaDB no admite ORDER BY sobre alias de funciones de agregado en el mismo SELECT;
        // se ordena por la expresión agregada (MIN/SUM/MAX sobre fi).
        switch ($sort) {
            case 'id':
                $query->orderByRaw('MIN(fi.id) '.$dir);
                break;
            case 'cliente':
                $query->orderByRaw("(SELECT TRIM(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido,''))) FROM clientes c WHERE c.cliente_id = fi.cliente_id LIMIT 1) {$dir}");
                break;
            case 'periodo':
                $query->orderByRaw('MIN(fi.periodo_desde) '.$dir)
                    ->orderByRaw('MAX(fi.periodo_hasta) '.$dir);
                break;
            case 'vencimiento':
                $query->orderByRaw('MIN(fi.fecha_vencimiento) IS NULL ASC')
                    ->orderByRaw('MIN(fi.fecha_vencimiento) '.$dir);
                break;
            case 'total':
                $query->orderByRaw('SUM(fi.total) '.$dir);
                break;
            case 'cobrado':
                $query->orderByRaw('SUM(fi.cobrado_calc) '.$dir);
                break;
            case 'saldo':
                $query->orderByRaw('SUM(fi.saldo_calc) '.$dir);
                break;
            case 'promesa':
                $query->orderByRaw('MAX(fi.prom_calc) IS NULL ASC')
                    ->orderByRaw('MAX(fi.prom_calc) '.$dir);
                break;
        }

        $query->orderByRaw('MIN(fi.id) asc');
    }

    /**
     * @param  list<int>  $clienteIds
     * @return \Illuminate\Support\Collection<int, int> claves = cliente_id con al menos un servicio no cancelado
     */
    private function clienteIdsConServicioActivo(array $clienteIds): \Illuminate\Support\Collection
    {
        if ($clienteIds === []) {
            return collect();
        }

        return Servicio::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
            ->distinct()
            ->pluck('cliente_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
    }

    private function clienteEsDadoDeBaja(?Cliente $c, \Illuminate\Support\Collection $conServicioActivo): bool
    {
        if (! $c) {
            return false;
        }
        if ((string) $c->estado === 'inactivo') {
            return true;
        }

        return ! $conServicioActivo->has((int) $c->cliente_id);
    }

    /**
     * Orden del listado / export / API pendientes. Parámetros: sort, direction (asc|desc).
     */
    private function applyFacturasPendientesOrden(Request $request, $query): void
    {
        $allowed = ['id', 'cliente', 'periodo', 'vencimiento', 'total', 'cobrado', 'saldo', 'promesa'];
        $sort = $request->input('sort', 'vencimiento');
        if (! in_array($sort, $allowed, true)) {
            $sort = 'vencimiento';
        }
        $dir = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $cobradoExpr = 'LEAST(factura_internas.total, '.FacturaInterna::sqlSumCobros().')';
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $promExpr = '(SELECT MAX(vencimiento_at) FROM promesa_pagos pp WHERE pp.factura_interna_id = factura_internas.id)';

        switch ($sort) {
            case 'id':
                $query->orderBy('factura_internas.id', $dir);
                break;
            case 'cliente':
                $query->leftJoin('clientes as pf_ord_cli', 'pf_ord_cli.cliente_id', '=', 'factura_internas.cliente_id')
                    ->select('factura_internas.*')
                    ->orderBy('pf_ord_cli.nombre', $dir)
                    ->orderBy('pf_ord_cli.apellido', $dir);
                break;
            case 'periodo':
                $query->orderBy('factura_internas.periodo_desde', $dir)
                    ->orderBy('factura_internas.periodo_hasta', $dir);
                break;
            case 'vencimiento':
                $query->orderByRaw('factura_internas.fecha_vencimiento IS NULL ASC')
                    ->orderBy('factura_internas.fecha_vencimiento', $dir);
                break;
            case 'total':
                $query->orderBy('factura_internas.total', $dir);
                break;
            case 'cobrado':
                $query->orderByRaw($cobradoExpr.' '.$dir);
                break;
            case 'saldo':
                $query->orderByRaw($saldoExpr.' '.$dir);
                break;
            case 'promesa':
                $query->orderByRaw($promExpr.' IS NULL ASC')
                    ->orderByRaw($promExpr.' '.$dir);
                break;
        }

        $query->orderBy('factura_internas.id', 'asc');
    }

    public function show(FacturaInterna $factura_interna, TpagoPaymentLinkService $tpagoLinks)
    {
        $factura_interna->load(['cliente', 'detalles.impuesto', 'usuario', 'cobros', 'notasCredito.usuario']);
        $ajustes = AjustesGenerales::obtener();
        $tpagoLink = $tpagoLinks->ultimoLinkActivo($factura_interna);
        $tpagoDisponible = $tpagoLinks->disponible();

        $saldoAFavorCliente = 0.0;
        if ($factura_interna->cliente_id) {
            $saldoAFavorCliente = round((float) Servicio::query()
                ->where('cliente_id', $factura_interna->cliente_id)
                ->sum('saldo_a_favor'), 2);
        }

        return view('factura-internas.show', compact(
            'factura_interna',
            'ajustes',
            'tpagoLink',
            'tpagoDisponible',
            'saldoAFavorCliente'
        ));
    }

    /**
     * Aplica saldo a favor del cliente al saldo pendiente de la factura.
     */
    public function aplicarSaldoAFavor(
        Request $request,
        FacturaInterna $factura_interna,
        FacturacionService $facturacionService
    ) {
        try {
            $resultado = $facturacionService->aplicarSaldoAFavorAFacturaPendiente($factura_interna);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('factura-internas.show', $factura_interna)
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()
                ->route('factura-internas.show', $factura_interna)
                ->with('error', 'No se pudo aplicar el saldo a favor: '.$e->getMessage());
        }

        $msg = 'Se aplicaron '.number_format($resultado['monto_aplicado'], 0, ',', '.')
            .' '.$factura_interna->moneda.' de saldo a favor a la factura.';
        if ($resultado['pagada']) {
            $msg .= ' La factura quedó pagada.';
        } else {
            $msg .= ' Saldo pendiente: '.number_format($resultado['saldo_pendiente'], 0, ',', '.')
                .' '.$factura_interna->moneda.'.';
        }
        if (! empty($resultado['avisos'])) {
            $msg .= ' '.implode(' ', $resultado['avisos']);
        }

        return redirect()
            ->route('factura-internas.show', $factura_interna)
            ->with('success', $msg);
    }

    /**
     * Genera (o reusa) un link de pago TPago para el saldo de la factura.
     */
    public function generarLinkTpago(
        Request $request,
        FacturaInterna $factura_interna,
        TpagoPaymentLinkService $tpagoLinks
    ) {
        try {
            $result = $tpagoLinks->paraFactura(
                $factura_interna,
                $request->boolean('force_new')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo generar el link.';

            return back()->with('error', (string) $msg);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $url = $result['checkout_url'];
        $msg = ($result['reused'] ? 'Link TPago reutilizado: ' : 'Link TPago generado: ').$url;

        return back()->with('success', $msg)->with('tpago_link_url', $url);
    }

    /**
     * Emite nota de crédito sobre la factura (reduce saldo pendiente).
     */
    public function emitirNotaCredito(Request $request, FacturaInterna $factura_interna, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $nota = $facturacionService->emitirNotaCredito(
                $factura_interna,
                (float) $validated['monto'],
                $validated['motivo'] ?? null,
                auth()->user()?->usuario_id
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        $msg = 'Nota de crédito emitida por '.number_format((float) $nota->monto, 0, ',', '.').' '.$factura_interna->moneda.'.';
        if ($request->expectsJson()) {
            $factura_interna->refresh();

            return response()->json([
                'message' => $msg,
                'nota' => [
                    'id' => $nota->id,
                    'monto' => (float) $nota->monto,
                    'motivo' => $nota->motivo,
                    'created_at' => $nota->created_at?->toIso8601String(),
                ],
                'factura' => [
                    'estado' => $factura_interna->estado,
                    'saldo_pendiente' => (float) $factura_interna->saldo_pendiente,
                    'monto_notas_credito' => (float) $factura_interna->monto_notas_credito,
                    'esta_pagada' => $factura_interna->esta_pagada,
                ],
            ]);
        }

        return redirect()
            ->route('factura-internas.show', $factura_interna)
            ->with('success', $msg);
    }

    /**
     * Descarga la factura interna en PDF (misma información que la vista de detalle).
     */
    public function pdf(FacturaInterna $factura_interna)
    {
        $factura_interna->load(['cliente', 'detalles.impuesto', 'usuario', 'cobros', 'notasCredito.usuario']);
        $ajustes = AjustesGenerales::obtener();

        $logoBase64 = null;
        if ($ajustes && $ajustes->logo && Storage::disk('public')->exists($ajustes->logo)) {
            $mime = Storage::disk('public')->mimeType($ajustes->logo) ?? 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($ajustes->logo));
        }

        $nombreArchivo = 'factura-interna-'.$factura_interna->id.'.pdf';

        return Pdf::loadView('factura-internas.pdf', [
            'factura_interna' => $factura_interna,
            'ajustes' => $ajustes,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'portrait')->download($nombreArchivo);
    }

    public function edit(FacturaInterna $factura_interna)
    {
        $factura_interna->load(['cliente', 'detalles.impuesto']);

        $fechaPagoParaEdicion = $factura_interna->fecha_pago;
        $fechaPagoDesdeCobros = false;
        $cobrosFactura = collect();
        $formaPagoActual = null;

        if ($factura_interna->estado === 'pagada') {
            $ultimoCobro = $factura_interna->cobros()
                ->orderByDesc('fecha_pago')
                ->orderByDesc('id')
                ->first();
            if ($ultimoCobro && $ultimoCobro->fecha_pago) {
                $fechaPagoParaEdicion = $ultimoCobro->fecha_pago;
                $fechaPagoDesdeCobros = true;
            }

            $factura_interna->load('cobros');
            $cobrosFactura = $factura_interna->cobros->sortByDesc('id')->values();
            if ($cobrosFactura->isNotEmpty()) {
                $formas = $cobrosFactura->pluck('forma_pago')->filter()->unique();
                $formaPagoActual = $formas->count() === 1 ? $formas->first() : $cobrosFactura->first()->forma_pago;
            }
        }

        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);
        $impuestos = Impuesto::activos();
        $estados = FacturaInterna::estados();
        $formasPago = Cobro::formasPago();

        return view('factura-internas.edit', compact(
            'factura_interna',
            'clientes',
            'impuestos',
            'estados',
            'fechaPagoParaEdicion',
            'fechaPagoDesdeCobros',
            'cobrosFactura',
            'formaPagoActual',
            'formasPago'
        ));
    }

    public function update(Request $request, FacturaInterna $factura_interna, FacturacionService $facturacionService)
    {
        $estadoAnterior = $factura_interna->estado;
        $esEspecial = $factura_interna->esServicioEspecial();

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'periodo_desde' => $esEspecial ? ['nullable', 'date'] : ['required', 'date'],
            'periodo_hasta' => $esEspecial
                ? ['nullable', 'date']
                : ['required', 'date', 'after_or_equal:periodo_desde'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => $esEspecial ? ['nullable', 'date'] : ['required', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'string', 'in:emitida,anulada,pendiente,pagada,cancelada'],
            'moneda' => ['required', 'string', 'max:10'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric'],
            'detalles.*.impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'forma_pago' => ['nullable', 'string', 'in:efectivo,transferencia,tarjeta,cheque,otro'],
        ]);

        $factura_interna->load('detalles');
        $detallesRequest = $request->input('detalles', []);
        $idsEnRequest = collect($detallesRequest)->pluck('id')->filter()->values()->all();

        $montoCobradoAntes = (float) DB::table('cobro_factura_interna')
            ->where('factura_interna_id', $factura_interna->id)
            ->sum('monto');
        $estabaCobrada = $montoCobradoAntes > 0.009 && $factura_interna->saldo_pendiente <= 0.009;
        $detallesAntes = $factura_interna->detalles->keyBy('id');

        $montoSaldoFavorGenerado = 0.0;

        DB::transaction(function () use ($factura_interna, $validated, $detallesRequest, $idsEnRequest, $estadoAnterior, $facturacionService, $esEspecial, $estabaCobrada, $detallesAntes, $montoCobradoAntes, &$montoSaldoFavorGenerado) {
            $factura_interna->update([
                'cliente_id' => $validated['cliente_id'],
                'periodo_desde' => $esEspecial ? null : $validated['periodo_desde'],
                'periodo_hasta' => $esEspecial ? null : $validated['periodo_hasta'],
                'fecha_emision' => $validated['fecha_emision'],
                'fecha_vencimiento' => $esEspecial ? null : $validated['fecha_vencimiento'],
                'fecha_pago' => $validated['fecha_pago'] ?? null,
                'estado' => $validated['estado'],
                'moneda' => $validated['moneda'],
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            $detalleIdsDeEstaFactura = $factura_interna->detalles->pluck('id')->all();
            $idsAEliminar = array_diff($detalleIdsDeEstaFactura, $idsEnRequest);
            if (! empty($idsAEliminar)) {
                FacturaInternaDetalle::where('factura_interna_id', $factura_interna->id)
                    ->whereIn('id', $idsAEliminar)
                    ->delete();
            }

            $subtotal = 0;
            $totalImpuestos = 0;
            $total = 0;

            foreach ($detallesRequest as $item) {
                $cantidad = (float) $item['cantidad'];
                $precioUnitario = (float) $item['precio_unitario'];
                $impuesto = ! empty($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularDesdePrecio($cantidad, $precioUnitario, $impuesto);

                $datosDetalle = [
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ];

                if (! empty($item['id']) && in_array((int) $item['id'], $detalleIdsDeEstaFactura, true)) {
                    $detalle = FacturaInternaDetalle::where('id', $item['id'])->where('factura_interna_id', $factura_interna->id)->first();
                    if ($detalle) {
                        $datosDetalle['servicio_id'] = $detalle->servicio_id;
                        $detalle->update($datosDetalle);
                    }
                } else {
                    $datosDetalle['factura_interna_id'] = $factura_interna->id;
                    $datosDetalle['servicio_id'] = null;
                    FacturaInternaDetalle::create($datosDetalle);
                }

                $subtotal += $calc['subtotal'];
                $totalImpuestos += $calc['monto_impuesto'];
                $total += $calc['total'];
            }

            $descuento = round((float) ($validated['descuento'] ?? 0), 2);
            $totalBruto = round($total, 2);
            $totalFinal = max(0, $totalBruto - $descuento);

            $factura_interna->update([
                'subtotal' => round($subtotal, 2),
                'total_impuestos' => round($totalImpuestos, 2),
                'descuento' => $descuento,
                'total' => $totalFinal,
            ]);

            if ($estabaCobrada && ! empty($idsAEliminar)) {
                $detallesEliminados = $detallesAntes->only($idsAEliminar)->values();
                $montoSaldoFavorGenerado = $facturacionService->aplicarSaldoFavorPorDetallesEliminadosEnFacturaCobrada(
                    $factura_interna,
                    $detallesEliminados,
                    $montoCobradoAntes,
                    $totalFinal,
                    (int) $validated['cliente_id'],
                );
            }

            if ($estadoAnterior === 'pagada' && $validated['estado'] !== 'pagada') {
                $this->removerCobrosAlDejarDeEstarPagada($factura_interna, $facturacionService);
            }

            $factura_interna->refresh();

            if ($validated['estado'] === 'pagada') {
                $factura_interna->load('cobros');
                if ($factura_interna->cobros->isNotEmpty() && ! empty($validated['forma_pago'])) {
                    foreach ($factura_interna->cobros as $cobro) {
                        $cobro->update(['forma_pago' => $validated['forma_pago']]);
                    }
                }

                if (! empty($validated['fecha_pago'])) {
                    $ultimoCobro = $factura_interna->cobros()
                        ->orderByDesc('fecha_pago')
                        ->orderByDesc('id')
                        ->first();
                    if ($ultimoCobro) {
                        $nuevaFecha = Carbon::parse($validated['fecha_pago'])->startOfDay();
                        if ($ultimoCobro->fecha_pago instanceof \DateTimeInterface) {
                            $nuevaFecha = Carbon::parse($validated['fecha_pago'])
                                ->setTimeFromTimeString(Carbon::parse($ultimoCobro->fecha_pago)->format('H:i:s'));
                        }
                        $ultimoCobro->update(['fecha_pago' => $nuevaFecha]);
                    }
                }
            }
        });

        $mensaje = 'Factura interna actualizada.';
        if ($montoSaldoFavorGenerado > 0.009) {
            $mensaje .= ' Se registraron '.number_format($montoSaldoFavorGenerado, 0, ',', '.').' PYG como saldo a favor por líneas eliminadas.';
        }

        return redirect()->route('factura-internas.index')
            ->with('success', $mensaje);
    }

    public function destroy(Request $request, FacturaInterna $factura_interna, FacturacionService $facturacionService)
    {
        $clienteId = $factura_interna->cliente_id;
        $servicioIds = $factura_interna->detalles()->whereNotNull('servicio_id')->pluck('servicio_id')->unique()->values()->all();

        $factura_interna->cobros()->delete();
        $factura_interna->detalles()->delete();
        $factura_interna->delete();

        $facturacionService->revisarEstadoPagoServiciosTrasEliminarFacturaInterna($clienteId, $servicioIds);

        if ($request->ajax()) {
            return response()->json(['message' => 'Factura interna eliminada.']);
        }

        return redirect()->route('factura-internas.index')
            ->with('success', 'Factura interna eliminada.');
    }

    /**
     * Al pasar de pagada a otro estado (p. ej. pendiente): elimina o desvincula cobros.
     * Si el cobro solo cubría esta factura, se borra el registro de cobro; si cubría varias, solo se quita el vínculo y el monto correspondiente.
     */
    private function removerCobrosAlDejarDeEstarPagada(FacturaInterna $factura, FacturacionService $facturacionService): void
    {
        $factura->load('cobros');
        foreach ($factura->cobros as $cobro) {
            $otras = $cobro->facturaInternas()->where('factura_internas.id', '!=', $factura->id)->count();
            if ($otras === 0) {
                $facturacionService->eliminarCobro($cobro);

                continue;
            }

            $pivot = DB::table('cobro_factura_interna')
                ->where('cobro_id', $cobro->id)
                ->where('factura_interna_id', $factura->id)
                ->first();
            if (! $pivot) {
                continue;
            }
            $montoQuitar = (float) $pivot->monto;
            $cobro->facturaInternas()->detach($factura->id);
            $cobro->refresh();
            $nuevoMonto = max(0, (float) $cobro->monto - $montoQuitar);
            if ($nuevoMonto <= 0) {
                $facturacionService->eliminarCobro($cobro);
            } else {
                $cobro->update(['monto' => $nuevoMonto]);
            }
        }

        $facturacionService->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, 'pendiente');
    }
}
