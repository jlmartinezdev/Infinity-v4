<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\TvCuenta;
use App\Models\TvCuentaAsignacion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TvCuentaController extends Controller
{
    private function calcularFechaVencimiento(int $dia): string
    {
        $hoy = Carbon::today();
        $diaAjustado = min($dia, $hoy->copy()->endOfMonth()->day);
        $fecha = $hoy->copy()->day($diaAjustado);

        if ($fecha->lt($hoy)) {
            $siguienteMes = $hoy->copy()->addMonthNoOverflow()->startOfMonth();
            $diaSiguienteMes = min($dia, $siguienteMes->copy()->endOfMonth()->day);
            $fecha = $siguienteMes->day($diaSiguienteMes);
        }

        return $fecha->toDateString();
    }

    private function perfilesV2Disponible(): bool
    {
        return Schema::hasColumns('tv_cuentas', ['perfil_1', 'perfil_2', 'perfil_3']);
    }

    private function asignacionPerfilesV2Disponible(): bool
    {
        return Schema::hasColumns('tv_cuenta_asignaciones', ['servicio_id', 'perfil_numero', 'fecha_activacion']);
    }

    /**
     * Recalcula app_tv, cantidad_perfil_app y precio_app en servicios según todas las asignaciones TV de ese servicio.
     */
    private function sincronizarAppTvEnServicio(int $servicioId): void
    {
        $asignaciones = TvCuentaAsignacion::query()
            ->where('servicio_id', $servicioId)
            ->get();

        if ($asignaciones->isEmpty()) {
            Servicio::where('servicio_id', $servicioId)->update([
                'app_tv' => false,
                'cantidad_perfil_app' => null,
                'precio_app' => null,
            ]);

            return;
        }

        $cantidad = $asignaciones->count();
        $suma = 0.0;
        if (Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')) {
            foreach ($asignaciones as $a) {
                $suma += (float) ($a->precio_aplicado ?? 0);
            }
        }

        Servicio::where('servicio_id', $servicioId)->update([
            'app_tv' => true,
            'cantidad_perfil_app' => $cantidad,
            'precio_app' => $suma > 0 ? round($suma, 2) : null,
        ]);
    }

    public function index(Request $request)
    {
        $filtro = $request->get('estado', 'todos');
        if (! in_array($filtro, ['todos', 'vencido', 'por_vencer', 'ok'], true)) {
            $filtro = 'todos';
        }

        $todas = TvCuenta::query()
            ->withCount('asignaciones')
            ->with([
                'asignaciones' => fn ($q) => $q->orderBy('perfil_numero')->orderBy('id'),
                'asignaciones.servicio.cliente',
                'asignaciones.servicio.plan',
            ])
            ->get();

        $stats = [
            'total' => $todas->count(),
            'vencido' => 0,
            'por_vencer' => 0,
            'ok' => 0,
            'asignaciones' => (int) $todas->sum('asignaciones_count'),
            'cupos_totales' => (int) $todas->sum(fn (TvCuenta $c) => $c->maxAsignaciones()),
        ];

        foreach ($todas as $cuenta) {
            $stats[$cuenta->estadoVencimiento()]++;
        }

        $prioridad = ['vencido' => 0, 'por_vencer' => 1, 'ok' => 2];
        $ordenadas = $todas->sort(function (TvCuenta $a, TvCuenta $b) use ($prioridad) {
            $ea = $a->estadoVencimiento();
            $eb = $b->estadoVencimiento();
            $cmpEstado = ($prioridad[$ea] ?? 9) <=> ($prioridad[$eb] ?? 9);
            if ($cmpEstado !== 0) {
                return $cmpEstado;
            }

            return $a->diasParaVencimiento() <=> $b->diasParaVencimiento();
        })->values();

        if ($filtro !== 'todos') {
            $ordenadas = $ordenadas
                ->filter(fn (TvCuenta $c) => $c->estadoVencimiento() === $filtro)
                ->values();
        }

        $perPage = 20;
        $page = max(1, (int) $request->get('page', 1));
        $cuentas = new LengthAwarePaginator(
            $ordenadas->forPage($page, $perPage)->values(),
            $ordenadas->count(),
            $perPage,
            $page,
            ['path' => route('tv-cuentas.index'), 'query' => $request->query()]
        );

        return view('tv-cuentas.index', compact('cuentas', 'stats', 'filtro'));
    }

    /**
     * Exportar clientes con app TV asignada a Excel (CSV UTF-8 con separador ;).
     * Una fila por asignación (cliente + perfil/pantalla en cuenta TV).
     */
    public function exportarExcel(Request $request): StreamedResponse
    {
        $filtro = $request->get('estado', 'todos');
        if (! in_array($filtro, ['todos', 'vencido', 'por_vencer', 'ok'], true)) {
            $filtro = 'todos';
        }

        $asignaciones = TvCuentaAsignacion::query()
            ->with([
                'tvCuenta',
                'servicio.cliente',
                'servicio.plan',
            ])
            ->whereHas('tvCuenta')
            ->get()
            ->filter(function (TvCuentaAsignacion $asig) use ($filtro) {
                $cuenta = $asig->tvCuenta;
                if (! $cuenta) {
                    return false;
                }

                return $filtro === 'todos' || $cuenta->estadoVencimiento() === $filtro;
            })
            ->sort(function (TvCuentaAsignacion $a, TvCuentaAsignacion $b) {
                $clienteA = $a->servicio?->cliente;
                $clienteB = $b->servicio?->cliente;
                $nombreA = mb_strtolower(trim(($clienteA?->nombre ?? '').' '.($clienteA?->apellido ?? '')));
                $nombreB = mb_strtolower(trim(($clienteB?->nombre ?? '').' '.($clienteB?->apellido ?? '')));
                $cmp = $nombreA <=> $nombreB;
                if ($cmp !== 0) {
                    return $cmp;
                }

                $appCmp = ($a->tvCuenta?->aplicacion ?? '') <=> ($b->tvCuenta?->aplicacion ?? '');
                if ($appCmp !== 0) {
                    return $appCmp;
                }

                $cuentaCmp = ($a->tvCuenta?->usuario_app ?? '') <=> ($b->tvCuenta?->usuario_app ?? '');
                if ($cuentaCmp !== 0) {
                    return $cuentaCmp;
                }

                return ((int) ($a->perfil_numero ?? 0)) <=> ((int) ($b->perfil_numero ?? 0));
            })
            ->values();

        $filename = 'clientes-app-tv-'.now()->format('Y-m-d-His').'.csv';
        $aplicaciones = TvCuenta::aplicaciones();
        $estadosServicio = Servicio::estadosDisponibles();

        return response()->streamDownload(function () use ($asignaciones, $aplicaciones, $estadosServicio) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'ID cliente',
                'Cliente',
                'Cédula',
                'Teléfono',
                'Email',
                'ID servicio',
                'Plan',
                'Estado servicio',
                'App',
                'Cuenta TV',
                'Usuario app',
                'Contraseña app',
                'Perfil/Pantalla',
                'Nombre perfil',
                'Precio aplicado (Gs.)',
                'Promo',
                'TV box comodato',
                'Fecha activación',
                'Vencimiento cuenta',
                'Estado vencimiento',
                'Día vencimiento mensual',
            ], ';');

            foreach ($asignaciones as $asig) {
                $cuenta = $asig->tvCuenta;
                $servicio = $asig->servicio;
                $cliente = $servicio?->cliente;
                $perfilNum = (int) ($asig->perfil_numero ?? 0);

                fputcsv($output, [
                    $cliente?->cliente_id ?? '',
                    trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? '')),
                    $cliente?->cedula ?? '',
                    $cliente?->telefono ?? '',
                    $cliente?->email ?? '',
                    $servicio?->servicio_id ?? '',
                    $servicio?->plan?->nombre ?? '',
                    $estadosServicio[$servicio?->estado ?? ''] ?? ($servicio?->estado ?? ''),
                    $aplicaciones[$cuenta?->aplicacion ?? ''] ?? ($cuenta?->aplicacion ?? ''),
                    $cuenta?->nombre ?? '',
                    $cuenta?->usuario_app ?? '',
                    $cuenta?->password ?? '',
                    $perfilNum > 0
                        ? ($cuenta?->esLumix() ? 'Pantalla '.$perfilNum : 'Perfil '.$perfilNum)
                        : '',
                    $perfilNum > 0 ? ($cuenta?->nombreSlot($perfilNum) ?? '') : '',
                    $asig->precio_aplicado !== null
                        ? number_format((float) $asig->precio_aplicado, 0, ',', '')
                        : '',
                    ($asig->es_promo ?? false) ? 'Sí' : 'No',
                    ($asig->tvbox_comodato ?? false) ? 'Sí' : 'No',
                    optional($asig->fecha_activacion)->format('d/m/Y') ?? '',
                    $cuenta ? $cuenta->fechaVencimientoReferencia()->format('d/m/Y') : '',
                    $cuenta ? match ($cuenta->estadoVencimiento()) {
                        'vencido' => 'Vencido',
                        'por_vencer' => 'Por vencer',
                        default => 'Al día',
                    } : '',
                    $cuenta ? $cuenta->diaVencimientoMensual() : '',
                ], ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /** Redirige al listado integrado (compatibilidad con enlaces antiguos). */
    public function dashboard(Request $request)
    {
        return redirect()->route('tv-cuentas.index', $request->query());
    }

    public function create()
    {
        return view('tv-cuentas.create', [
            'aplicaciones' => TvCuenta::aplicaciones(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglasCuentaTv($request));
        $validated['vencimiento_pago'] = $this->calcularFechaVencimiento((int) $validated['dia_aviso_vencimiento']);
        $validated = $this->normalizarCamposPorAplicacion($validated);

        $clienteIdPrefill = $validated['cliente_id_prefill'] ?? null;
        unset($validated['cliente_id_prefill']);

        $tvCuenta = TvCuenta::create($validated);

        if ($clienteIdPrefill) {
            return redirect()->route('tv-cuentas.edit', ['tv_cuenta' => $tvCuenta, 'cliente_id' => $clienteIdPrefill])
                ->with('success', 'Cuenta TV creada. Ahora asigná el cliente al perfil correspondiente.');
        }

        return redirect()->route('tv-cuentas.index')
            ->with('success', 'Cuenta TV creada.');
    }

    public function edit(TvCuenta $tv_cuenta)
    {
        $asignacionPerfilesV2 = $this->asignacionPerfilesV2Disponible();

        $tv_cuenta->load([
            'asignaciones' => fn ($query) => $asignacionPerfilesV2
                ? $query->orderBy('perfil_numero')
                : $query->orderBy('id'),
            'asignaciones.servicio.cliente',
            'asignaciones.servicio.plan',
        ]);

        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido', 'cedula']);
        $servicios = Servicio::query()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->whereIn('estado', [Servicio::ESTADO_ACTIVO, Servicio::ESTADO_SUSPENDIDO])
            ->orderBy('cliente_id')
            ->orderBy('servicio_id')
            ->get(['servicio_id', 'cliente_id', 'plan_id', 'estado', 'app_tv']);

        return view('tv-cuentas.edit', [
            'tv_cuenta' => $tv_cuenta,
            'clientes' => $clientes,
            'servicios' => $servicios,
            'asignacionPerfilesV2' => $asignacionPerfilesV2,
            'aplicaciones' => TvCuenta::aplicaciones(),
        ]);
    }

    public function update(Request $request, TvCuenta $tv_cuenta)
    {
        $validated = $request->validate($this->reglasCuentaTv($request, $tv_cuenta));
        $validated = $this->normalizarCamposPorAplicacion($validated);

        $maxNuevo = ($validated['aplicacion'] ?? TvCuenta::APP_NEBULA) === TvCuenta::APP_LUMIX
            ? TvCuenta::MAX_LUMIX
            : TvCuenta::MAX_NEBULA;
        if ($tv_cuenta->asignaciones()->count() > $maxNuevo) {
            return redirect()->route('tv-cuentas.edit', $tv_cuenta)
                ->withInput()
                ->with('error', 'No se puede cambiar la aplicación: hay más asignaciones activas que cupos permitidos.');
        }

        $diaNuevo = (int) $validated['dia_aviso_vencimiento'];
        $diaAnterior = (int) ($tv_cuenta->dia_aviso_vencimiento ?? $tv_cuenta->vencimiento_pago?->day ?? 0);
        if ($diaNuevo !== $diaAnterior || ! $tv_cuenta->vencimiento_pago) {
            $validated['vencimiento_pago'] = $this->calcularFechaVencimiento($diaNuevo);
        } else {
            unset($validated['vencimiento_pago']);
        }

        $tv_cuenta->update($validated);

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', 'Cuenta TV actualizada.');
    }

    public function renovar(Request $request, TvCuenta $tv_cuenta)
    {
        $nueva = $tv_cuenta->renovarUnMesAdelante();

        $mensaje = 'Cuenta renovada. Próximo vencimiento: ' . $nueva->format('d/m/Y') . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'vencimiento_pago' => $nueva->toDateString(),
                'estado' => $tv_cuenta->fresh()->estadoVencimiento(),
            ]);
        }

        return redirect()->back()->with('success', $mensaje);
    }

    public function destroy(TvCuenta $tv_cuenta)
    {
        $tv_cuenta->delete();

        return redirect()->route('tv-cuentas.index')
            ->with('success', 'Cuenta TV eliminada.');
    }

    public function storeAsignacion(Request $request, TvCuenta $tv_cuenta)
    {
        $maxSlots = $tv_cuenta->maxAsignaciones();
        if ($tv_cuenta->asignaciones()->count() >= $maxSlots) {
            return redirect()->route('tv-cuentas.edit', $tv_cuenta)
                ->with('error', 'Esta cuenta ya tiene '.$maxSlots.' '.$tv_cuenta->etiquetaTipoSlot().( $maxSlots === 1 ? '' : 's').' asignados (máximo).');
        }

        $rules = [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'cliente_id'),
            ],
            'servicio_id' => [
                'required',
                'integer',
                Rule::exists('servicios', 'servicio_id'),
            ],
            'es_promo' => ['nullable', 'boolean'],
            'tvbox_comodato' => ['nullable', 'boolean'],
        ];

        if ($this->asignacionPerfilesV2Disponible()) {
            $rules['perfil_numero'] = ['required', 'integer', 'between:1,'.$maxSlots];
            $rules['fecha_activacion'] = ['required', 'date'];
        }

        $validated = $request->validate($rules);

        $servicio = Servicio::query()
            ->where('servicio_id', $validated['servicio_id'])
            ->where('cliente_id', $validated['cliente_id'])
            ->first();

        if (! $servicio) {
            return redirect()->route('tv-cuentas.edit', $tv_cuenta)
                ->withInput()
                ->with('error', 'El servicio seleccionado no pertenece al cliente elegido.');
        }

        if ($this->asignacionPerfilesV2Disponible()) {
            $perfilEnUso = $tv_cuenta->asignaciones()
                ->where('perfil_numero', $validated['perfil_numero'])
                ->exists();
            if ($perfilEnUso) {
                return redirect()->route('tv-cuentas.edit', $tv_cuenta)
                    ->with('error', 'Ese '.$tv_cuenta->etiquetaTipoSlot().' ya está asignado en esta cuenta.');
            }
        }

        $payload = [
            'tv_cuenta_id' => $tv_cuenta->id,
            'servicio_id' => $validated['servicio_id'],
        ];

        if ($this->asignacionPerfilesV2Disponible()) {
            $payload['perfil_numero'] = $validated['perfil_numero'];
            $payload['fecha_activacion'] = $validated['fecha_activacion'];
        }

        $esPromo = (bool) ($validated['es_promo'] ?? false);
        if (Schema::hasColumn('tv_cuenta_asignaciones', 'es_promo')) {
            $payload['es_promo'] = $esPromo;
        }
        if (Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')) {
            $precioAplicado = 0.0;
            if (! $esPromo && $this->asignacionPerfilesV2Disponible() && isset($validated['perfil_numero'])) {
                $precioAplicado = (float) ($tv_cuenta->precioSlot((int) $validated['perfil_numero']) ?? 0);
            }
            $payload['precio_aplicado'] = $precioAplicado;
        }
        if (Schema::hasColumn('tv_cuenta_asignaciones', 'tvbox_comodato')) {
            $payload['tvbox_comodato'] = (bool) ($validated['tvbox_comodato'] ?? false);
        }

        TvCuentaAsignacion::create($payload);

        $this->sincronizarAppTvEnServicio((int) $servicio->servicio_id);

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', $this->asignacionPerfilesV2Disponible()
                ? 'Servicio asignado al '.$tv_cuenta->etiquetaTipoSlot().' correctamente.'
                : 'Servicio asignado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasCuentaTv(Request $request, ?TvCuenta $cuenta = null): array
    {
        $aplicacion = $request->input('aplicacion', $cuenta?->aplicacion ?? TvCuenta::APP_NEBULA);
        $esLumix = $aplicacion === TvCuenta::APP_LUMIX;

        $rules = [
            'nombre' => ['nullable', 'string', 'max:120'],
            'aplicacion' => ['required', 'string', Rule::in(array_keys(TvCuenta::aplicaciones()))],
            'usuario_app' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'dia_aviso_vencimiento' => ['required', 'integer', 'between:1,31'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'cliente_id_prefill' => ['nullable', 'integer', Rule::exists('clientes', 'cliente_id')],
            'precio_perfil_1' => ['nullable', 'numeric', 'min:0'],
            'precio_perfil_2' => ['nullable', 'numeric', 'min:0'],
            'precio_perfil_3' => ['nullable', 'numeric', 'min:0'],
            'precio_pantalla_1' => ['nullable', 'numeric', 'min:0'],
            'precio_pantalla_2' => ['nullable', 'numeric', 'min:0'],
            'precio_pantalla_3' => ['nullable', 'numeric', 'min:0'],
            'precio_pantalla_4' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->perfilesV2Disponible() && ! $esLumix) {
            $rules['perfil_1'] = ['required', 'string', 'max:120'];
            $rules['perfil_2'] = ['required', 'string', 'max:120'];
            $rules['perfil_3'] = ['required', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizarCamposPorAplicacion(array $validated): array
    {
        if (($validated['aplicacion'] ?? TvCuenta::APP_NEBULA) === TvCuenta::APP_LUMIX) {
            $validated['perfil_1'] = null;
            $validated['perfil_2'] = null;
            $validated['perfil_3'] = null;
            $validated['precio_perfil_1'] = null;
            $validated['precio_perfil_2'] = null;
            $validated['precio_perfil_3'] = null;
        } else {
            $validated['precio_pantalla_1'] = null;
            $validated['precio_pantalla_2'] = null;
            $validated['precio_pantalla_3'] = null;
            $validated['precio_pantalla_4'] = null;
        }

        return $validated;
    }

    public function destroyAsignacion(TvCuenta $tv_cuenta, TvCuentaAsignacion $asignacion)
    {
        if ((int) $asignacion->tv_cuenta_id !== (int) $tv_cuenta->id) {
            abort(404);
        }

        $servicioId = (int) $asignacion->servicio_id;
        $asignacion->delete();
        if ($servicioId > 0) {
            $this->sincronizarAppTvEnServicio($servicioId);
        }

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', 'Asignación quitada.');
    }
}
