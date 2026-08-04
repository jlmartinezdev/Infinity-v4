<?php

namespace App\Http\Controllers;

use App\Jobs\ConsultarLoteSifenJob;
use App\Jobs\EmitirFacturaSifenJob;
use App\Models\Cliente;
use App\Models\CedulaPadron;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturacionParametro;
use App\Models\Impuesto;
use App\Models\Servicio;
use App\Models\SifenConfiguracion;
use App\Services\FacturacionService;
use App\Services\Sifen\SifenBackground;
use App\Services\Sifen\SifenKudeService;
use App\Services\Sifen\SifenService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $query = Factura::with(['cliente', 'usuario'])
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->boolean('lote_pendiente')) {
            $query->lotePendienteSifen();
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_emision', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->hasta);
        }

        $facturas = $query->paginate(15)->withQueryString();
        $clientes = Cliente::orderBy('nombre')->get();

        $mesDashboard = now()->startOfMonth();
        if ($request->filled('mes')) {
            try {
                $mesDashboard = Carbon::createFromFormat('Y-m', (string) $request->mes)->startOfMonth();
            } catch (\Throwable) {
                // Ignorar formato inválido; usar mes actual.
            }
        }

        $statsEmitidasMes = Factura::query()
            ->where('estado', 'emitida')
            ->whereYear('fecha_emision', $mesDashboard->year)
            ->whereMonth('fecha_emision', $mesDashboard->month)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto_total, COALESCE(SUM(total_impuestos), 0) as monto_iva')
            ->first();

        $borradoresMes = Factura::query()
            ->where('estado', 'borrador')
            ->whereYear('fecha_emision', $mesDashboard->year)
            ->whereMonth('fecha_emision', $mesDashboard->month)
            ->count();

        $montoBorradoresMes = (float) Factura::query()
            ->where('estado', 'borrador')
            ->whereYear('fecha_emision', $mesDashboard->year)
            ->whereMonth('fecha_emision', $mesDashboard->month)
            ->sum('total');

        $lotesPendientesCount = Factura::query()->lotePendienteSifen()->count();

        return view('facturas.index', [
            'facturas' => $facturas,
            'clientes' => $clientes,
            'mesDashboard' => $mesDashboard,
            'mesDashboardLabel' => $mesDashboard->locale('es')->isoFormat('MMMM YYYY'),
            'statsEmitidasMes' => $statsEmitidasMes,
            'borradoresMes' => $borradoresMes,
            'montoBorradoresMes' => $montoBorradoresMes,
            'lotesPendientesCount' => $lotesPendientesCount,
        ]);
    }

    public function create(Request $request)
    {
        $periodo = $this->resolverPeriodoFacturacion($request->input('periodo'));
        $mes = $periodo['desde'];
        $mesLabel = $periodo['label'];
        $periodoYm = $periodo['ym'];
        $marcadorPeriodo = $periodo['marcador'];

        $emisionesMes = Factura::query()
            ->where('estado', 'emitida')
            ->where(function ($q) use ($mes, $marcadorPeriodo) {
                $q->where(function ($q2) use ($marcadorPeriodo) {
                    $q2->whereNotNull('observaciones')
                        ->where('observaciones', 'like', '%'.$marcadorPeriodo.'%');
                })->orWhere(function ($q2) use ($mes) {
                    $q2->where(function ($q3) {
                        $q3->whereNull('observaciones')->orWhere('observaciones', 'not like', '%Período facturación:%');
                    })
                        ->whereYear('fecha_emision', $mes->year)
                        ->whereMonth('fecha_emision', $mes->month);
                });
            })
            ->selectRaw('cliente_id, COUNT(*) as cantidad, MAX(id) as ultima_factura_id, MAX(fecha_emision) as ultima_fecha')
            ->groupBy('cliente_id')
            ->get()
            ->keyBy('cliente_id');

        $query = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo'])
            ->orderBy('nombre')
            ->orderBy('apellido');

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('apellido', 'like', '%'.$buscar.'%')
                    ->orWhere('cedula', 'like', '%'.$buscar.'%');
            });
        }

        if ($request->boolean('solo_pendientes')) {
            $query->whereNotIn('cliente_id', $emisionesMes->keys()->all());
        }

        $clientes = $query->paginate(50)->withQueryString();

        $totalActivos = Cliente::whereIn('estado', ['activo', 'inactivo'])->count();
        $emitidosMes = $emisionesMes->count();
        $pendientesMes = max(0, $totalActivos - $emitidosMes);

        $periodosOpciones = $this->opcionesPeriodoFacturacion();

        return view('facturas.seleccionar-cliente', compact(
            'clientes',
            'emisionesMes',
            'mesLabel',
            'periodoYm',
            'periodosOpciones',
            'totalActivos',
            'emitidosMes',
            'pendientesMes',
        ));
    }

    public function createManual()
    {
        $clientes = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo'])
            ->whereNotNull('cedula')
            ->where('cedula', '!=', '')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();
        $impuestos = Impuesto::activos();
        $sifenConfig = SifenConfiguracion::activa();
        $prefill = $this->construirPrefillSifen($sifenConfig);

        return view('facturas.create', [
            'clientes' => $clientes,
            'impuestos' => $impuestos,
            'clienteSeleccionado' => null,
            'prefill' => $prefill,
            'detallesIniciales' => [],
            'sifenConfig' => $sifenConfig,
            'modoManual' => true,
        ]);
    }

    public function createParaCliente(Request $request, Cliente $cliente)
    {
        if (blank($cliente->cedula)) {
            return redirect()->route('facturas.create')
                ->with('error', 'El cliente debe tener cédula o RUC para emitir factura electrónica SIFEN.');
        }

        $periodo = $this->resolverPeriodoFacturacion($request->input('periodo'));
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo'])->orderBy('nombre')->get();
        $impuestos = Impuesto::activos();
        $clienteSeleccionado = $cliente;

        $sifenConfig = SifenConfiguracion::activa();
        $prefill = $this->construirPrefillSifen($sifenConfig);
        $detallesIniciales = $this->construirDetallesDesdeServiciosCliente(
            $cliente,
            $periodo['desde'],
            $periodo['hasta'],
        );

        return view('facturas.create', compact(
            'clientes',
            'impuestos',
            'clienteSeleccionado',
            'prefill',
            'detallesIniciales',
            'sifenConfig',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function construirPrefillSifen(?SifenConfiguracion $config): array
    {
        if (! $config) {
            return [
                'numero_timbrado' => null,
                'timbrado_vigencia_desde' => null,
                'timbrado_vigencia_hasta' => null,
                'establecimiento' => 1,
                'punto_emision' => 1,
            ];
        }

        return [
            'numero_timbrado' => $config->numero_timbrado,
            'timbrado_vigencia_desde' => $config->timbrado_vigencia_desde?->format('Y-m-d'),
            'timbrado_vigencia_hasta' => $config->timbrado_vigencia_hasta?->format('Y-m-d'),
            'establecimiento' => $config->establecimiento ?? 1,
            'punto_emision' => $config->punto_expedicion ?? 1,
        ];
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon, label: string, ym: string, marcador: string}
     */
    private function resolverPeriodoFacturacion(null|string $periodoYm = null): array
    {
        $mes = now()->startOfMonth();

        if (filled($periodoYm)) {
            try {
                $mes = Carbon::createFromFormat('Y-m', (string) $periodoYm)->startOfMonth();
            } catch (\Throwable) {
                // Formato inválido: usar mes actual.
            }
        }

        $desde = $mes->copy()->startOfMonth();
        $hasta = $mes->copy()->endOfMonth();

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'label' => $desde->copy()->locale('es')->isoFormat('MMMM YYYY'),
            'ym' => $desde->format('Y-m'),
            'marcador' => 'Período facturación: '.$desde->format('Y-m'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function opcionesPeriodoFacturacion(): array
    {
        $opciones = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 6; $i++) {
            $mes = $cursor->copy()->subMonths($i);
            $suffix = match ($i) {
                0 => ' (mes actual)',
                1 => ' (mes anterior)',
                default => '',
            };
            $opciones[] = [
                'value' => $mes->format('Y-m'),
                'label' => ucfirst($mes->copy()->locale('es')->isoFormat('MMMM YYYY')).$suffix,
            ];
        }

        return $opciones;
    }

    private function resolverMontoFijoMasivo(?string $modo, mixed $montoOtro): ?float
    {
        return match ($modo) {
            '500000' => 500000.0,
            '1000000' => 1000000.0,
            'otro' => filled($montoOtro) ? (float) $montoOtro : null,
            default => null,
        };
    }

    /**
     * @return list<array{descripcion: string, cantidad: float, precio_unitario: float, impuesto_id: int|null, servicio_id: int|null}>
     */
    private function construirDetallesDesdeServiciosCliente(
        Cliente $cliente,
        ?Carbon $periodoDesde = null,
        ?Carbon $periodoHasta = null,
        ?float $montoFijo = null,
    ): array {
        $impuestoIva = Impuesto::where('codigo', 'IVA10')->first() ?? Impuesto::activos()->firstWhere('porcentaje', '>', 0);
        $periodoDesde = ($periodoDesde ?? now())->copy()->startOfMonth();
        $periodoHasta = ($periodoHasta ?? now())->copy()->endOfMonth();
        $periodoStr = $periodoDesde->format('d/m/Y').' a '.$periodoHasta->format('d/m/Y');

        $servicios = Servicio::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->with('plan')
            ->orderBy('servicio_id')
            ->get();

        if ($montoFijo !== null && $montoFijo > 0) {
            $servicioPrincipal = $servicios->first(function ($servicio) {
                return (float) ($servicio->plan?->precio ?? 0) > 0;
            }) ?? $servicios->first();

            $nombrePlan = $servicioPrincipal?->plan?->nombre ?? 'Servicio de internet';

            return [[
                'descripcion' => sprintf(
                    '%s - %s Gs. - Período %s',
                    $nombrePlan,
                    number_format($montoFijo, 0, ',', '.'),
                    $periodoStr
                ),
                'cantidad' => 1,
                'precio_unitario' => $montoFijo,
                'impuesto_id' => $impuestoIva?->id,
                'servicio_id' => $servicioPrincipal?->servicio_id,
            ]];
        }

        $detalles = [];

        foreach ($servicios as $servicio) {
            $plan = $servicio->plan;
            $precioPlan = (float) ($plan?->precio ?? 0);
            if ($precioPlan <= 0) {
                continue;
            }

            $precio = FacturacionService::calcularPrecioProrrateado(
                $servicio,
                $periodoDesde->copy(),
                $periodoHasta->copy(),
                $precioPlan
            );

            $nombrePlan = $plan?->nombre ?? 'Servicio de internet';
            $detalles[] = [
                'descripcion' => sprintf('%s - %s Gs. - Período %s', $nombrePlan, number_format($precio, 0, ',', '.'), $periodoStr),
                'cantidad' => 1,
                'precio_unitario' => $precio,
                'impuesto_id' => $impuestoIva?->id,
                'servicio_id' => $servicio->servicio_id,
            ];

            $precioApp = (float) ($servicio->precio_app ?? 0);
            if ((bool) ($servicio->app_tv ?? false) && $precioApp > 0) {
                $detalles[] = [
                    'descripcion' => sprintf('Servicio especial - %s Gs. - Período %s', number_format($precioApp, 0, ',', '.'), $periodoStr),
                    'cantidad' => 1,
                    'precio_unitario' => $precioApp,
                    'impuesto_id' => $impuestoIva?->id,
                    'servicio_id' => $servicio->servicio_id,
                ];
            }
        }

        return $detalles;
    }

    public function store(Request $request)
    {
        $reglasBase = [
            'tipo_documento' => ['required', 'string', 'in:factura_contado,factura_credito,nota_credito,nota_debito'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'moneda' => ['required', 'string', 'in:PYG,USD'],
            'numero_timbrado' => ['nullable', 'string', 'max:20'],
            'timbrado_vigencia_desde' => ['nullable', 'date'],
            'timbrado_vigencia_hasta' => ['nullable', 'date'],
            'establecimiento' => ['nullable', 'integer', 'min:1', 'max:255'],
            'punto_emision' => ['nullable', 'integer', 'min:1', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'detalles.*.servicio_id' => ['nullable', 'integer', 'exists:servicios,servicio_id'],
        ];

        if ($request->input('tipo_receptor') === 'ocasional') {
            $validated = $request->validate(array_merge($reglasBase, [
                'tipo_receptor' => ['required', 'in:ocasional'],
                'receptor_cedula' => ['required', 'string', 'max:30'],
                'receptor_nombre' => ['required', 'string', 'max:100'],
                'receptor_apellido' => ['nullable', 'string', 'max:100'],
                'receptor_direccion' => ['nullable', 'string', 'max:255'],
                'receptor_email' => ['nullable', 'email', 'max:100'],
                'receptor_telefono' => ['nullable', 'string', 'max:30'],
            ]));
            $datosReceptor = [
                'cliente_id' => null,
                'es_ocasional' => true,
                'receptor_documento' => trim((string) $validated['receptor_cedula']),
                'receptor_nombre' => trim((string) $validated['receptor_nombre']),
                'receptor_apellido' => filled($validated['receptor_apellido'] ?? null) ? trim((string) $validated['receptor_apellido']) : null,
                'receptor_direccion' => filled($validated['receptor_direccion'] ?? null) ? trim((string) $validated['receptor_direccion']) : null,
                'receptor_email' => filled($validated['receptor_email'] ?? null) ? trim((string) $validated['receptor_email']) : null,
                'receptor_telefono' => filled($validated['receptor_telefono'] ?? null) ? trim((string) $validated['receptor_telefono']) : null,
            ];
        } else {
            $validated = $request->validate(array_merge($reglasBase, [
                'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            ]));
            $datosReceptor = [
                'cliente_id' => (int) $validated['cliente_id'],
                'es_ocasional' => false,
                'receptor_documento' => null,
                'receptor_nombre' => null,
                'receptor_apellido' => null,
                'receptor_direccion' => null,
                'receptor_email' => null,
                'receptor_telefono' => null,
            ];
        }

        $factura = \DB::transaction(function () use ($validated, $request, $datosReceptor) {
            $factura = Factura::create(array_merge([
                'tipo_documento' => $validated['tipo_documento'],
                'estado' => 'borrador',
                'fecha_emision' => $validated['fecha_emision'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'moneda' => $validated['moneda'],
                'numero_timbrado' => $validated['numero_timbrado'] ?? null,
                'timbrado_vigencia_desde' => $validated['timbrado_vigencia_desde'] ?? null,
                'timbrado_vigencia_hasta' => $validated['timbrado_vigencia_hasta'] ?? null,
                'establecimiento' => $validated['establecimiento'] ?? 1,
                'punto_emision' => $validated['punto_emision'] ?? 1,
                'observaciones' => $validated['observaciones'] ?? null,
                'usuario_id' => $request->user()?->usuario_id,
                'subtotal' => 0,
                'total_impuestos' => 0,
                'total' => 0,
            ], $datosReceptor));

            foreach ($validated['detalles'] as $item) {
                $impuesto = isset($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularLinea(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'servicio_id' => $item['servicio_id'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);
            }

            $factura->load('detalles');
            $factura->recalcularTotales();
            return $factura;
        });

        return redirect()->route('facturas.show', $factura)->with('success', 'Factura creada correctamente.');
    }

    /**
     * Crea borradores (y opcionalmente envía a SIFEN) para varios clientes a la vez.
     */
    public function storeMasivo(Request $request)
    {
        $validated = $request->validate([
            'cliente_ids' => ['required', 'array', 'min:1', 'max:50'],
            'cliente_ids.*' => ['integer', 'exists:clientes,cliente_id'],
            'emitir' => ['nullable', 'boolean'],
            'periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'monto_modo' => ['nullable', 'string', 'in:plan,500000,1000000,otro'],
            'monto_fijo' => [
                'nullable',
                'numeric',
                'min:1',
                'max:999999999',
                'required_if:monto_modo,otro',
            ],
        ]);

        $periodo = $this->resolverPeriodoFacturacion($validated['periodo'] ?? null);
        $montoFijo = $this->resolverMontoFijoMasivo(
            $validated['monto_modo'] ?? 'plan',
            $validated['monto_fijo'] ?? null,
        );
        $emitir = $request->boolean('emitir');
        $sifenConfig = SifenConfiguracion::activa();
        $prefill = $this->construirPrefillSifen($sifenConfig);

        $clientes = Cliente::query()
            ->whereIn('cliente_id', $validated['cliente_ids'])
            ->get()
            ->keyBy('cliente_id');

        $creadas = [];
        $enviadas = [];
        $errores = [];

        foreach ($validated['cliente_ids'] as $clienteId) {
            $cliente = $clientes->get($clienteId);
            $etiqueta = $cliente
                ? trim($cliente->nombre.' '.$cliente->apellido)
                : 'Cliente #'.$clienteId;

            if (! $cliente) {
                $errores[] = $etiqueta.': no encontrado.';
                continue;
            }

            if (blank($cliente->cedula)) {
                $errores[] = $etiqueta.': sin cédula/RUC.';
                continue;
            }

            $detalles = $this->construirDetallesDesdeServiciosCliente(
                $cliente,
                $periodo['desde'],
                $periodo['hasta'],
                $montoFijo,
            );
            if ($detalles === []) {
                $errores[] = $etiqueta.': sin servicios activos con precio.';
                continue;
            }

            try {
                $obs = $periodo['marcador'].' ('.$periodo['label'].')';
                if ($montoFijo !== null) {
                    $obs .= ' · Monto fijo '.number_format($montoFijo, 0, ',', '.').' Gs.';
                }

                $factura = $this->crearBorradorElectronico(
                    $cliente,
                    $detalles,
                    $prefill,
                    $request->user()?->usuario_id,
                    $obs,
                );

                $creadas[] = $factura->id;

                if ($emitir) {
                    $factura->update(['set_estado_envio' => 'en_cola']);
                    SifenBackground::dispatch(new EmitirFacturaSifenJob($factura->id));
                    $enviadas[] = $factura->id;
                }
            } catch (\Throwable $e) {
                $errores[] = $etiqueta.': '.$e->getMessage();
            }
        }

        $partes = [];
        if ($creadas !== []) {
            $partes[] = count($creadas).' borrador(es) creado(s) · período '.$periodo['label'];
            if ($montoFijo !== null) {
                $partes[] = 'monto '.number_format($montoFijo, 0, ',', '.').' Gs.';
            }
        }
        if ($emitir && $enviadas !== []) {
            $partes[] = count($enviadas).' encolado(s) para SIFEN (segundo plano)';
        }
        if ($errores !== []) {
            $partes[] = count($errores).' con error';
        }

        $mensaje = $partes !== []
            ? implode(' · ', $partes).'.'
            : 'No se procesó ningún cliente.';

        if ($errores !== []) {
            $mensaje .= ' Detalle: '.implode(' | ', array_slice($errores, 0, 8));
            if (count($errores) > 8) {
                $mensaje .= ' … (+'.(count($errores) - 8).')';
            }
        }

        $tipo = $errores !== [] && $creadas === [] ? 'error' : ($errores !== [] ? 'warning' : 'success');

        if ($emitir && $enviadas !== []) {
            return redirect()
                ->route('facturas.index', ['estado' => 'borrador'])
                ->with(
                    $tipo,
                    $mensaje.' La emisión corre en segundo plano; luego use «Consultar lotes» cuando figuren pendientes.'
                );
        }

        if ($creadas !== [] && count($creadas) === 1 && ! $emitir) {
            return redirect()->route('facturas.show', $creadas[0])->with($tipo, $mensaje);
        }

        return redirect()
            ->route('facturas.index', $creadas !== [] ? ['estado' => 'borrador'] : [])
            ->with($tipo, $mensaje);
    }

    /**
     * @param  list<array{descripcion: string, cantidad: float, precio_unitario: float, impuesto_id: int|null, servicio_id: int}>  $detalles
     * @param  array<string, mixed>  $prefill
     */
    private function crearBorradorElectronico(
        Cliente $cliente,
        array $detalles,
        array $prefill,
        ?int $usuarioId,
        ?string $observaciones = null,
    ): Factura {
        return \DB::transaction(function () use ($cliente, $detalles, $prefill, $usuarioId, $observaciones) {
            $factura = Factura::create([
                'cliente_id' => $cliente->cliente_id,
                'es_ocasional' => false,
                'tipo_documento' => 'factura_contado',
                'estado' => 'borrador',
                'fecha_emision' => now()->toDateString(),
                'fecha_vencimiento' => null,
                'moneda' => 'PYG',
                'numero_timbrado' => $prefill['numero_timbrado'] ?? null,
                'timbrado_vigencia_desde' => $prefill['timbrado_vigencia_desde'] ?? null,
                'timbrado_vigencia_hasta' => $prefill['timbrado_vigencia_hasta'] ?? null,
                'establecimiento' => $prefill['establecimiento'] ?? 1,
                'punto_emision' => $prefill['punto_emision'] ?? 1,
                'observaciones' => $observaciones,
                'usuario_id' => $usuarioId,
                'subtotal' => 0,
                'total_impuestos' => 0,
                'total' => 0,
            ]);

            foreach ($detalles as $item) {
                $impuesto = isset($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularLinea(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'servicio_id' => $item['servicio_id'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);
            }

            $factura->load('detalles');
            $factura->recalcularTotales();

            return $factura;
        });
    }

    public function verificarReceptorDocumento(Request $request)
    {
        $request->validate(['cedula' => ['required', 'string']]);

        $cliente = Cliente::where('cedula', $request->cedula)->first();
        if (! $cliente) {
            return response()->json(['existe' => false]);
        }

        return response()->json([
            'existe' => true,
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
            ],
        ]);
    }

    public function consultarPadronReceptor(Request $request)
    {
        $request->validate(['cedula' => ['required', 'string']]);

        try {
            $cedula = CedulaPadron::buscarPorCedula($request->cedula);
        } catch (\Exception $e) {
            return response()->json([
                'encontrado' => false,
                'error' => 'Error al consultar el padrón: '.$e->getMessage(),
            ], 500);
        }

        if (! $cedula) {
            return response()->json([
                'encontrado' => false,
                'mensaje' => 'No se encontró en el padrón',
            ], 404);
        }

        return response()->json([
            'encontrado' => true,
            'cedula' => $cedula->NRODOC,
            'nombre' => trim($cedula->NOMBRE ?? ''),
            'apellido' => trim($cedula->APELLIDO ?? ''),
            'direccion' => trim($cedula->DIREC ?? ''),
            'domicilio' => trim($cedula->DOMIC ?? ''),
        ]);
    }

    public function show(Factura $factura)
    {
        $factura->load(['cliente', 'detalles.impuesto', 'usuario']);

        return view('facturas.show', compact('factura'));
    }

    public function edit(Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden editar facturas en estado borrador.');
        }
        if ($factura->enColaSifen() || $factura->lotePendienteSifen()) {
            return redirect()->route('facturas.show', $factura)
                ->with('warning', 'No se puede editar mientras la factura está en proceso o en cola SIFEN.');
        }
        $factura->load('detalles');
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo'])->orderBy('nombre')->get();
        $impuestos = Impuesto::activos();

        return view('facturas.edit', [
            'factura' => $factura,
            'clientes' => $clientes,
            'impuestos' => $impuestos,
            'modoManual' => $factura->esOcasional(),
        ]);
    }

    public function update(Request $request, Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.show', $factura)->with('error', 'Solo se pueden editar facturas en borrador.');
        }

        $reglasBase = [
            'tipo_documento' => ['required', 'string', 'in:factura_contado,factura_credito,nota_credito,nota_debito'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'moneda' => ['required', 'string', 'in:PYG,USD'],
            'numero_timbrado' => ['nullable', 'string', 'max:20'],
            'timbrado_vigencia_desde' => ['nullable', 'date'],
            'timbrado_vigencia_hasta' => ['nullable', 'date'],
            'establecimiento' => ['nullable', 'integer', 'min:1', 'max:255'],
            'punto_emision' => ['nullable', 'integer', 'min:1', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'detalles.*.servicio_id' => ['nullable', 'integer', 'exists:servicios,servicio_id'],
        ];

        if ($factura->esOcasional()) {
            $validated = $request->validate(array_merge($reglasBase, [
                'receptor_cedula' => ['required', 'string', 'max:30'],
                'receptor_nombre' => ['required', 'string', 'max:100'],
                'receptor_apellido' => ['nullable', 'string', 'max:100'],
                'receptor_direccion' => ['nullable', 'string', 'max:255'],
                'receptor_email' => ['nullable', 'email', 'max:100'],
                'receptor_telefono' => ['nullable', 'string', 'max:30'],
            ]));
            $datosReceptor = [
                'cliente_id' => null,
                'es_ocasional' => true,
                'receptor_documento' => trim((string) $validated['receptor_cedula']),
                'receptor_nombre' => trim((string) $validated['receptor_nombre']),
                'receptor_apellido' => filled($validated['receptor_apellido'] ?? null) ? trim((string) $validated['receptor_apellido']) : null,
                'receptor_direccion' => filled($validated['receptor_direccion'] ?? null) ? trim((string) $validated['receptor_direccion']) : null,
                'receptor_email' => filled($validated['receptor_email'] ?? null) ? trim((string) $validated['receptor_email']) : null,
                'receptor_telefono' => filled($validated['receptor_telefono'] ?? null) ? trim((string) $validated['receptor_telefono']) : null,
            ];
        } else {
            $validated = $request->validate(array_merge($reglasBase, [
                'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            ]));
            $datosReceptor = [
                'cliente_id' => (int) $validated['cliente_id'],
                'es_ocasional' => false,
            ];
        }

        \DB::transaction(function () use ($factura, $validated, $datosReceptor) {
            $factura->update(array_merge([
                'tipo_documento' => $validated['tipo_documento'],
                'fecha_emision' => $validated['fecha_emision'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'moneda' => $validated['moneda'],
                'numero_timbrado' => $validated['numero_timbrado'] ?? null,
                'timbrado_vigencia_desde' => $validated['timbrado_vigencia_desde'] ?? null,
                'timbrado_vigencia_hasta' => $validated['timbrado_vigencia_hasta'] ?? null,
                'establecimiento' => $validated['establecimiento'] ?? 1,
                'punto_emision' => $validated['punto_emision'] ?? 1,
                'observaciones' => $validated['observaciones'] ?? null,
            ], $datosReceptor));

            $factura->detalles()->delete();
            foreach ($validated['detalles'] as $item) {
                $impuesto = isset($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularLinea(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'servicio_id' => $item['servicio_id'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);
            }
            $factura->load('detalles');
            $factura->recalcularTotales();
        });

        return redirect()->route('facturas.show', $factura)->with('success', 'Factura actualizada correctamente.');
    }

    public function destroy(Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.index')->with('error', 'Solo se pueden eliminar facturas en borrador.');
        }
        $factura->delete();
        return redirect()->route('facturas.index')->with('success', 'Factura eliminada.');
    }

    /**
     * Emite la factura electrónica en segundo plano (cola).
     */
    public function emitir(Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden emitir facturas en estado borrador.');
        }

        if ($factura->enColaSifen()) {
            return redirect()->route('facturas.show', $factura)
                ->with('warning', 'Esta factura ya está en cola de procesamiento SIFEN. Actualice en unos segundos.');
        }

        if ($factura->lotePendienteSifen()) {
            return redirect()->route('facturas.show', $factura)
                ->with('warning', 'Ya tiene un lote pendiente. Use «Consultar lote SIFEN».');
        }

        $factura->update(['set_estado_envio' => 'en_cola']);
        SifenBackground::dispatch(new EmitirFacturaSifenJob($factura->id));

        return redirect()->route('facturas.show', $factura)->with(
            'warning',
            'Emisión encolada en segundo plano. Actualice esta página en unos segundos para ver el resultado o el lote pendiente.'
        );
    }

    /**
     * Consulta el resultado de un lote SIFEN en segundo plano.
     */
    public function consultarLote(Factura $factura)
    {
        if ($factura->estado === 'emitida' && $factura->set_estado_envio === 'autorizado') {
            return redirect()->route('facturas.show', $factura)
                ->with('success', 'La factura ya está autorizada.');
        }

        if (! $factura->lotePendienteSifen() && blank($factura->set_nro_lote)) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Esta factura no tiene un lote pendiente de consulta.');
        }

        if ($factura->set_estado_envio === 'consultando') {
            return redirect()->route('facturas.show', $factura)
                ->with('warning', 'La consulta ya está en cola. Actualice en unos segundos.');
        }

        $factura->update(['set_estado_envio' => 'consultando']);
        SifenBackground::dispatch(new ConsultarLoteSifenJob($factura->id));

        return redirect()->route('facturas.show', $factura)->with(
            'warning',
            'Consulta de lote encolada en segundo plano. Actualice esta página en unos segundos.'
        );
    }

    /**
     * Consulta varios lotes SIFEN pendientes en segundo plano.
     */
    public function consultarLotesPendientes(Request $request)
    {
        $validated = $request->validate([
            'factura_ids' => ['nullable', 'array', 'max:50'],
            'factura_ids.*' => ['integer', 'exists:factura_electronicas,id'],
            'todos' => ['nullable', 'boolean'],
        ]);

        $query = Factura::query()->lotePendienteSifen()->orderBy('id');

        if ($request->boolean('todos') || empty($validated['factura_ids'] ?? [])) {
            $facturas = $query->limit(50)->get();
        } else {
            $facturas = $query->whereIn('id', $validated['factura_ids'])->limit(50)->get();
        }

        if ($facturas->isEmpty()) {
            return redirect()->route('facturas.index', ['lote_pendiente' => 1])
                ->with('warning', 'No hay lotes SIFEN pendientes para consultar.');
        }

        $encoladas = 0;
        foreach ($facturas as $factura) {
            if ($factura->set_estado_envio === 'consultando') {
                continue;
            }
            $factura->update(['set_estado_envio' => 'consultando']);
            SifenBackground::dispatch(new ConsultarLoteSifenJob($factura->id));
            $encoladas++;
        }

        if ($encoladas === 0) {
            return redirect()->route('facturas.index', ['lote_pendiente' => 1])
                ->with('warning', 'Las consultas seleccionadas ya estaban en cola. Actualice en unos segundos.');
        }

        return redirect()
            ->route('facturas.index', ['lote_pendiente' => 1])
            ->with(
                'warning',
                $encoladas.' consulta(s) de lote encolada(s) en segundo plano. Actualice el listado en unos segundos.'
            );
    }

    /**
     * Descarga el KuDE PDF (autorizada o pendiente de aprobación SIFEN).
     */
    public function descargarKude(Factura $factura, SifenKudeService $kudeService)
    {
        if (! $factura->puedeImprimirKude()) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Aún no hay KuDE disponible. Emita el documento a SIFEN primero.');
        }

        $config = SifenConfiguracion::activa();
        if (! $config) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No hay configuración SIFEN activa.');
        }

        try {
            $pdfPath = $kudeService->generar($factura, $config, $factura->set_qr_url);
            $factura->update(['pdf_path' => $pdfPath]);
        } catch (\Throwable $e) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No se pudo generar el KuDE PDF: '.$e->getMessage());
        }

        $ruta = storage_path($pdfPath);

        return response()->download($ruta, basename($ruta));
    }

    /**
     * Vista KuDE formato ticket POS 80 mm (impresión térmica).
     */
    public function verKudePos(Factura $factura, SifenKudeService $kudeService)
    {
        if (! $factura->puedeImprimirKude()) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Aún no hay KuDE disponible. Emita el documento a SIFEN primero.');
        }

        $config = SifenConfiguracion::activa();
        if (! $config) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No hay configuración SIFEN activa.');
        }

        return view('facturas.kude-pos-80', $kudeService->datosParaVista($factura, $config, $factura->set_qr_url));
    }

    /**
     * Descarga el XML firmado del DE.
     */
    public function descargarXml(Factura $factura)
    {
        if (! $factura->xml_path) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No hay XML generado para esta factura.');
        }

        if (str_contains($factura->xml_path, 'DE_borrador_')) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'El XML disponible es solo borrador (sin firma). Emita el documento antes de descargar el XML firmado.');
        }

        $ruta = storage_path($factura->xml_path);
        if (! is_file($ruta)) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Archivo XML no encontrado en el servidor.');
        }

        $nombre = basename($ruta);

        return response()->file($ruta, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * Formulario para generar factura interna (mensual) desde servicios activos del cliente.
     */
    public function generarInterna(Request $request)
    {
        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);
        $mesActual = now()->format('Y-m');
        $periodoDesde = $request->get('periodo_desde', now()->startOfMonth()->toDateString());
        $periodoHasta = $request->get('periodo_hasta', now()->endOfMonth()->toDateString());

        return view('facturas.generar-interna', compact('clientes', 'periodoDesde', 'periodoHasta', 'mesActual'));
    }

    /**
     * Generar y guardar factura interna para el cliente y período indicados.
     */
    public function storeGenerarInterna(Request $request, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);
        $desde = Carbon::parse($validated['periodo_desde']);
        $hasta = Carbon::parse($validated['periodo_hasta']);

        try {
            $factura = $facturacionService->generarFacturaInterna(
                $cliente,
                $desde,
                $hasta,
                $request->user()?->usuario_id
            );
            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna generada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.generar-interna')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Recibe servicio_ids desde el listado de servicios y redirige al formulario de generar factura interna.
     */
    public function prepararInternaDesdeServicios(Request $request)
    {
        $ids = $request->input('servicio_ids', []);
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];

        if (empty($ids)) {
            return redirect()->route('servicios.index')
                ->with('error', 'Seleccione al menos un servicio para generar la factura interna.');
        }

        session(['factura_interna_servicio_ids' => $ids]);
        return redirect()->route('facturas.generar-interna-desde-servicios');
    }

    /**
     * Formulario para generar factura(s) interna(s) con los servicios pre-seleccionados (desde listado de servicios).
     */
    public function generarInternaDesdeServicios()
    {
        $servicioIds = session('factura_interna_servicio_ids', []);

        if (empty($servicioIds)) {
            return redirect()->route('servicios.index')
                ->with('error', 'No hay servicios seleccionados. Marque las filas en el listado de servicios.');
        }

        $servicios = Servicio::whereIn('servicio_id', $servicioIds)
            ->with(['plan', 'cliente'])
            ->orderBy('cliente_id')
            ->get();

        $periodoDesde = now()->startOfMonth()->toDateString();
        $periodoHasta = now()->endOfMonth()->toDateString();
        $periodoDesdeCarbon = Carbon::parse($periodoDesde);
        $periodoHastaCarbon = Carbon::parse($periodoHasta);

        $prorrateosPorServicio = [];
        foreach ($servicios as $s) {
            $precioPlan = $s->plan ? (float) $s->plan->precio : 0;
            $prorrateosPorServicio[$s->servicio_id] = \App\Services\FacturacionService::obtenerDetalleProrrateo($s, $periodoDesdeCarbon, $periodoHastaCarbon, $precioPlan);
        }

        return view('facturas.generar-interna-desde-servicios', [
            'servicios' => $servicios,
            'periodoDesde' => $periodoDesde,
            'periodoHasta' => $periodoHasta,
            'prorrateosPorServicio' => $prorrateosPorServicio,
        ]);
    }

    /**
     * Generar factura(s) interna(s) para los servicios guardados en sesión.
     */
    public function storeGenerarInternaDesdeServicios(Request $request, FacturacionService $facturacionService)
    {
        $servicioIds = session('factura_interna_servicio_ids', []);

        if (empty($servicioIds)) {
            return redirect()->route('servicios.index')
                ->with('error', 'La sesión expiró. Seleccione nuevamente los servicios.');
        }

        $validated = $request->validate([
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
        ]);

        $desde = Carbon::parse($validated['periodo_desde']);
        $hasta = Carbon::parse($validated['periodo_hasta']);

        try {
            $resultado = $facturacionService->generarFacturaInternaDesdeServicios(
                $servicioIds,
                $desde,
                $hasta,
                $request->user()?->usuario_id
            );

            session()->forget('factura_interna_servicio_ids');

            $primera = $resultado['primera'];
            $total = $resultado['facturas']->count();

            $mensaje = $total === 1
                ? 'Factura interna generada correctamente.'
                : "Se generaron {$total} facturas internas (una por cliente).";

            return redirect()->route('factura-internas.show', $primera)
                ->with('success', $mensaje);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.generar-interna-desde-servicios')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Formulario para crear factura interna desde un solo servicio (con campos editables).
     */
    public function crearInternaDesdeServicio(Servicio $servicio)
    {
        $servicio->load(['plan', 'cliente']);
        $impuestos = Impuesto::activos();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $impuestoPlan = Impuesto::where('codigo', 'IVA10')->first() ?? $impuestoExento;

        $periodoDesde = now()->startOfMonth()->toDateString();
        $periodoHasta = now()->endOfMonth()->toDateString();
        $fechaEmision = now()->toDateString();

        $diaVenc = FacturacionParametro::diaVencimiento();
        $diaCobro = FacturacionParametro::diaFechaCobro();
        $fechaVencimiento = Carbon::createFromDate(now()->year, now()->month, 1)->addDays(min($diaVenc, 28) - 1);
        if ($fechaVencimiento->isPast()) {
            $fechaVencimiento = Carbon::createFromDate(now()->year, now()->month, 1)->addMonth()->addDays(min($diaVenc, 28) - 1);
        }
        $fechaVencimiento = $fechaVencimiento->toDateString();

        $fechaPagoCarbon = Carbon::createFromDate(now()->year, now()->month, 1)->addDays(min($diaCobro, 28) - 1);
        if ($fechaPagoCarbon->isPast()) {
            $fechaPagoCarbon = Carbon::createFromDate(now()->year, now()->month, 1)->addMonth()->addDays(min($diaCobro, 28) - 1);
        }
        $fechaPago = $fechaPagoCarbon->toDateString();
        $precioPlan = $servicio->plan ? (float) $servicio->plan->precio : 0;
        $periodoDesdeCarbon = Carbon::parse($periodoDesde);
        $periodoHastaCarbon = Carbon::parse($periodoHasta);
        $precio = \App\Services\FacturacionService::calcularPrecioProrrateado($servicio, $periodoDesdeCarbon, $periodoHastaCarbon, $precioPlan);
        $descripcion = $servicio->plan
            ? sprintf('%s - %s Gs. - Período %s a %s', $servicio->plan->nombre, number_format($precio, 0, ',', '.'), now()->format('d/m/Y'), now()->endOfMonth()->format('d/m/Y'))
            : 'Servicio';

        $prorrateoInfo = \App\Services\FacturacionService::obtenerDetalleProrrateo($servicio, $periodoDesdeCarbon, $periodoHastaCarbon, $precioPlan);

        return view('facturas.crear-interna-servicio', compact(
            'servicio',
            'impuestos',
            'impuestoExento',
            'impuestoPlan',
            'periodoDesde',
            'periodoHasta',
            'fechaEmision',
            'fechaVencimiento',
            'fechaPago',
            'precio',
            'descripcion',
            'prorrateoInfo',
        ));
    }

    /**
     * Guarda factura interna creada desde un servicio con datos editables.
     */
    public function storeCrearInternaDesdeServicio(Request $request, Servicio $servicio, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.impuesto_id' => ['nullable', 'exists:impuestos,id'],
        ]);

        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }

        try {
            $factura = $facturacionService->generarFacturaInternaDesdeUnServicio(
                $servicio,
                Carbon::parse($validated['periodo_desde']),
                Carbon::parse($validated['periodo_hasta']),
                $validated['fecha_emision'],
                $validated['fecha_vencimiento'],
                $validated['fecha_pago'] ?? null,
                (float) ($validated['descuento'] ?? 0),
                $validated['items'],
                $request->user()?->usuario_id
            );

            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.crear-interna-servicio', $servicio)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Formulario: factura interna por una fracción del saldo pendiente del cliente (desde el servicio).
     */
    public function crearInternaFraccionDeudaServicio(Servicio $servicio, FacturacionService $facturacionService)
    {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->route('servicios.index')->with('error', 'No se puede emitir factura en un servicio cancelado.');
        }

        $saldoPendiente = $facturacionService->saldoPendienteInternasCliente((int) $servicio->cliente_id);
        if ($saldoPendiente <= 0) {
            return redirect()->route('servicios.index')->with('error', 'Este cliente no tiene saldo pendiente en facturas internas para fraccionar.');
        }

        $periodoDesde = now()->startOfMonth()->toDateString();
        $periodoHasta = now()->endOfMonth()->toDateString();
        $fechaEmision = now()->toDateString();

        $diaVenc = FacturacionParametro::diaVencimiento();
        $diaCobro = FacturacionParametro::diaFechaCobro();
        $fechaVencimiento = Carbon::createFromDate(now()->year, now()->month, 1)->addDays(min($diaVenc, 28) - 1);
        if ($fechaVencimiento->isPast()) {
            $fechaVencimiento = Carbon::createFromDate(now()->year, now()->month, 1)->addMonth()->addDays(min($diaVenc, 28) - 1);
        }
        $fechaVencimiento = $fechaVencimiento->toDateString();

        $fechaPagoCarbon = Carbon::createFromDate(now()->year, now()->month, 1)->addDays(min($diaCobro, 28) - 1);
        if ($fechaPagoCarbon->isPast()) {
            $fechaPagoCarbon = Carbon::createFromDate(now()->year, now()->month, 1)->addMonth()->addDays(min($diaCobro, 28) - 1);
        }
        $fechaPago = $fechaPagoCarbon->toDateString();

        $descripcionLineaDefault = sprintf('Fracción deuda — Servicio #%s', $servicio->servicio_id);

        return view('facturas.crear-interna-servicio-fraccion-deuda', compact(
            'servicio',
            'saldoPendiente',
            'periodoDesde',
            'periodoHasta',
            'fechaEmision',
            'fechaVencimiento',
            'fechaPago',
            'descripcionLineaDefault',
        ));
    }

    /**
     * Guarda factura interna con un ítem exento por el monto fraccionado (≤ saldo pendiente del cliente).
     */
    public function storeInternaFraccionDeudaServicio(Request $request, Servicio $servicio, FacturacionService $facturacionService)
    {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->route('servicios.index')->with('error', 'No se puede emitir factura en un servicio cancelado.');
        }

        $saldoMax = $facturacionService->saldoPendienteInternasCliente((int) $servicio->cliente_id);
        if ($saldoMax <= 0) {
            return redirect()->route('servicios.index')->with('error', 'Este cliente no tiene saldo pendiente en facturas internas.');
        }

        $validated = $request->validate([
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
            'monto_fraccion' => ['required', 'numeric', 'min:1'],
            'descripcion_linea' => ['nullable', 'string', 'max:500'],
        ]);

        $monto = (float) $validated['monto_fraccion'];
        $saldoActual = $facturacionService->saldoPendienteInternasCliente((int) $servicio->cliente_id);
        if ($monto > $saldoActual + 0.0001) {
            return redirect()->route('facturas.crear-interna-servicio-fraccion-deuda', $servicio)
                ->withInput()
                ->with('error', 'El monto no puede superar el saldo pendiente actual ('.number_format($saldoActual, 0, ',', '.').' Gs.).');
        }

        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $lineaDesc = trim((string) ($validated['descripcion_linea'] ?? '')) !== ''
            ? trim($validated['descripcion_linea'])
            : sprintf('Fracción deuda — %s Gs. — Servicio #%s', number_format($monto, 0, ',', '.'), $servicio->servicio_id);

        $items = [[
            'descripcion' => $lineaDesc,
            'cantidad' => 1,
            'precio_unitario' => $monto,
            'impuesto_id' => $impuestoExento->id,
        ]];

        $obs = sprintf(
            'Factura fraccionada por deuda — Servicio #%d — Período %s a %s',
            $servicio->servicio_id,
            Carbon::parse($validated['periodo_desde'])->format('d/m/Y'),
            Carbon::parse($validated['periodo_hasta'])->format('d/m/Y')
        );

        try {
            $factura = $facturacionService->generarFacturaInternaDesdeUnServicio(
                $servicio,
                Carbon::parse($validated['periodo_desde']),
                Carbon::parse($validated['periodo_hasta']),
                $validated['fecha_emision'],
                $validated['fecha_vencimiento'],
                $validated['fecha_pago'] ?? null,
                0.0,
                $items,
                $request->user()?->usuario_id,
                true,
                $obs
            );

            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna fraccionada por deuda creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.crear-interna-servicio-fraccion-deuda', $servicio)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Formulario: factura interna por servicio especial (sin período ni vencimiento).
     */
    public function crearInternaServicioEspecial(Servicio $servicio)
    {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->route('servicios.index')->with('error', 'No se puede facturar un servicio cancelado.');
        }

        $impuestos = Impuesto::activos();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $fechaEmision = now()->toDateString();
        $precioPlan = $servicio->plan ? (float) $servicio->plan->precio : 0;
        $descripcion = $servicio->plan
            ? sprintf('Servicio especial — %s', $servicio->plan->nombre)
            : sprintf('Servicio especial #%d', $servicio->servicio_id);

        return view('facturas.crear-interna-servicio-especial', compact(
            'servicio',
            'impuestos',
            'impuestoExento',
            'fechaEmision',
            'precioPlan',
            'descripcion',
        ));
    }

    /**
     * Guarda factura interna por servicio especial.
     */
    public function storeCrearInternaServicioEspecial(Request $request, Servicio $servicio, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'fecha_emision' => ['required', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.impuesto_id' => ['nullable', 'exists:impuestos,id'],
        ]);

        $servicio->load(['plan', 'cliente']);

        try {
            $factura = $facturacionService->generarFacturaInternaServicioEspecial(
                $servicio,
                $validated['fecha_emision'],
                (float) ($validated['descuento'] ?? 0),
                $validated['items'],
                $request->user()?->usuario_id,
                $validated['observaciones'] ?? null
            );

            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna por servicio especial creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.crear-interna-servicio-especial', $servicio)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Ejecuta suspensión por falta de pago: servicios de clientes con facturas vencidas y saldo pendiente se marcan como suspendidos.
     */
    public function suspenderFaltaPago(FacturacionService $facturacionService)
    {
        $suspendidos = $facturacionService->suspenderPorFaltaPago();
        $cantidad = count($suspendidos);
        return redirect()->route('facturas.index')
            ->with('success', $cantidad > 0
                ? "Suspensión aplicada: {$cantidad} servicio(s) suspendido(s) por falta de pago."
                : 'No había servicios pendientes de suspender por falta de pago.');
    }
}
