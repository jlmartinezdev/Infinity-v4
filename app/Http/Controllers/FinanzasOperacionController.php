<?php

namespace App\Http\Controllers;

use App\Models\FinCategoria;
use App\Models\FinCierre;
use App\Models\FinConciliacion;
use App\Models\FinCuenta;
use App\Models\FinEgreso;
use App\Models\FinEgresoPlantilla;
use App\Models\FinLiquidacion;
use App\Models\FinMovimiento;
use App\Models\FinRetiro;
use App\Models\FinTransferencia;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Finanzas\FinanzasLibroService;
use App\Services\Finanzas\PeriodoCerradoException;
use App\Support\FinanzasParametros;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use InvalidArgumentException;

class FinanzasOperacionController extends Controller
{
    public function transferencias(): View
    {
        return view('finanzas.transferencias', [
            'cuentas' => $this->cuentasActivas(),
            'filas' => FinTransferencia::query()->with(['origen', 'destino'])->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function guardarTransferencia(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $datos = $request->validate([
            'cuenta_origen_id' => ['required', 'integer', 'exists:fin_cuentas,id'],
            'cuenta_destino_id' => ['required', 'integer', 'exists:fin_cuentas,id', 'different:cuenta_origen_id'],
            'importe' => ['required', 'string', 'max:30'],
            'fecha' => ['required', 'date'],
            'numero_referencia' => ['nullable', 'string', 'max:80'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->ejecutar(function () use ($libro, $datos) {
            $libro->transferir(
                (int) $datos['cuenta_origen_id'],
                (int) $datos['cuenta_destino_id'],
                $this->importe($datos['importe']),
                Carbon::parse($datos['fecha']),
                $datos['numero_referencia'] ?? null,
                $datos['observaciones'] ?? null,
                auth()->id(),
            );
        }, 'finanzas.transferencias', 'Transferencia registrada. El saldo consolidado no cambia.');
    }

    public function egresos(): View
    {
        $inicioMes = now()->startOfMonth()->toDateString();
        $finMes = now()->endOfMonth()->toDateString();

        $totalesMes = FinEgreso::query()
            ->join('fin_categorias', 'fin_categorias.id', '=', 'fin_egresos.categoria_id')
            ->where('fin_egresos.estado', 'contabilizado')
            ->whereDate('fin_egresos.fecha', '>=', $inicioMes)
            ->whereDate('fin_egresos.fecha', '<=', $finMes)
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw('COALESCE(SUM(fin_egresos.importe), 0) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN fin_categorias.clase = 'opex' THEN fin_egresos.importe ELSE 0 END), 0) as opex")
            ->selectRaw("COALESCE(SUM(CASE WHEN fin_categorias.clase = 'capex' THEN fin_egresos.importe ELSE 0 END), 0) as capex")
            ->first();

        $plantillas = FinEgresoPlantilla::query()->with('staff')->where('activa', true)->orderBy('orden')->get();

        $resumenEgresos = [
            'cantidad_mes' => (int) ($totalesMes->cantidad ?? 0),
            'total_mes' => round((float) ($totalesMes->total ?? 0), 2),
            'opex_mes' => round((float) ($totalesMes->opex ?? 0), 2),
            'capex_mes' => round((float) ($totalesMes->capex ?? 0), 2),
            'plantillas_count' => $plantillas->count(),
            'plantillas_total' => round((float) $plantillas->sum('monto'), 2),
        ];

        return view('finanzas.egresos', [
            'cuentas' => $this->cuentasActivas(),
            'categorias' => FinCategoria::query()->whereIn('clase', ['opex', 'capex'])->where('activa', true)->orderBy('nombre')->get(),
            'proveedores' => Proveedor::query()->orderBy('nombre')->get(['id', 'nombre']),
            'plantillas' => $plantillas,
            'staff' => User::query()
                ->where('estado', 'activo')
                ->where('name', 'not like', 'N8N%')
                ->whereHas('rol', fn ($q) => $q->whereRaw('LOWER(descripcion) not in (?, ?)', ['cliente app', 'cliente']))
                ->orderBy('name')
                ->get(['usuario_id', 'name']),
            'filas' => FinEgreso::query()->with(['cuenta', 'categoria', 'proveedor', 'plantilla', 'staff', 'usuario', 'movimiento'])->where('estado', 'contabilizado')->orderByDesc('fecha')->orderByDesc('id')->limit(30)->get(),
            'resumenEgresos' => $resumenEgresos,
        ]);
    }

    public function guardarPlantilla(Request $request, FinEgresoPlantilla $plantilla): RedirectResponse
    {
        $request->merge(['nombre' => trim((string) $request->input('nombre'))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160', 'unique:fin_egreso_plantillas,nombre,'.$plantilla->id],
            'monto' => ['required', 'string', 'max:30'],
            'usuario_staff_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
        ], [
            'nombre.required' => 'La descripción es obligatoria.',
            'nombre.unique' => 'Ya hay un gasto habitual con esa descripción.',
        ]);
        $plantilla->update([
            'nombre' => $datos['nombre'],
            'monto' => $this->importe($datos['monto']),
            'usuario_staff_id' => $plantilla->es_sueldo ? ($datos['usuario_staff_id'] ?? null) : null,
        ]);

        return redirect()->route('finanzas.egresos')->with('success', '«'.$plantilla->nombre.'» actualizado. No se registró un pago.');
    }

    public function crearPlantilla(Request $request): RedirectResponse
    {
        $request->merge([
            'nombre' => trim((string) $request->input('nombre')),
            'es_sueldo' => $request->boolean('es_sueldo'),
        ]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160', 'unique:fin_egreso_plantillas,nombre'],
            'monto' => ['required', 'string', 'max:30'],
            'es_sueldo' => ['boolean'],
            'usuario_staff_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
        ], [
            'nombre.required' => 'La descripción es obligatoria.',
            'nombre.unique' => 'Ya hay un gasto habitual con esa descripción.',
        ]);
        if ($datos['es_sueldo'] && empty($datos['usuario_staff_id'])) {
            return back()->withInput()->withErrors(['usuario_staff_id' => 'El sueldo tiene que indicar la persona del staff.']);
        }
        try {
            $monto = $this->importe($datos['monto']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['monto' => $e->getMessage()]);
        }
        FinEgresoPlantilla::query()->create([
            'nombre' => $datos['nombre'],
            'monto' => $monto,
            'es_sueldo' => $datos['es_sueldo'],
            'usuario_staff_id' => $datos['es_sueldo'] ? ($datos['usuario_staff_id'] ?? null) : null,
            'orden' => ((int) FinEgresoPlantilla::query()->max('orden')) + 1,
            'activa' => true,
        ]);

        return redirect()->route('finanzas.egresos')->with('success', '«'.$datos['nombre'].'» agregado a los gastos habituales. No se registró un pago.');
    }

    public function archivo(Request $request, FinEgreso $egreso, string $tipo): StreamedResponse
    {
        $columna = $tipo === 'factura' ? 'factura_path' : ($tipo === 'documento' ? 'documento_path' : null);
        abort_unless($columna && $egreso->{$columna}, 404);
        $path = $egreso->{$columna};
        abort_unless(Storage::disk('local')->exists($path), 404);
        $mime = $this->mimeVisual($path);
        if ($request->boolean('ver') && $mime) {
            return Storage::disk('local')->response($path, null, [
                'Content-Type' => $mime,
            ]);
        }

        return Storage::disk('local')->download($path);
    }

    private function mimeVisual(string $path): ?string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
    }

    public function guardarEgreso(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $request->merge([
            'plantilla_id' => $request->filled('plantilla_id') ? $request->input('plantilla_id') : null,
            'descripcion' => trim((string) $request->input('descripcion')),
        ]);
        $datos = $request->validate([
            'cuenta_id' => ['required', 'integer', 'exists:fin_cuentas,id'],
            'categoria_id' => ['required', 'integer', 'exists:fin_categorias,id'],
            'descripcion' => ['nullable', 'string', 'max:160', 'required_without:plantilla_id'],
            'importe' => ['required', 'string', 'max:30'],
            'fecha' => ['required', 'date'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'comprobante' => ['nullable', 'string', 'max:80'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'nueva_categoria' => ['nullable', 'string', 'max:120'],
            'nueva_clase' => ['nullable', 'in:opex,capex'],
            'plantilla_id' => ['nullable', 'integer', 'exists:fin_egreso_plantillas,id'],
            'usuario_staff_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
            'documento' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'factura_archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'descripcion.required_without' => 'Escribí la descripción cuando el gasto no es habitual.',
        ]);
        $plantilla = ! empty($datos['plantilla_id']) ? FinEgresoPlantilla::query()->find($datos['plantilla_id']) : null;
        if ($plantilla && $plantilla->es_sueldo && empty($datos['usuario_staff_id'])) {
            return back()->withInput()->withErrors(['usuario_staff_id' => 'El sueldo tiene que indicar la persona del staff.']);
        }
        $categoriaId = (int) $datos['categoria_id'];
        if ($plantilla) {
            $categoriaId = (int) FinCategoria::query()->where('nombre', 'Gasto operativo')->value('id') ?: $categoriaId;
        }
        if (trim((string) ($datos['nueva_categoria'] ?? '')) !== '') {
            $categoria = FinCategoria::query()->create([
                'nombre' => trim($datos['nueva_categoria']),
                'clase' => $datos['nueva_clase'] ?? 'opex',
                'activa' => true,
            ]);
            $categoriaId = $categoria->id;
        }

        $documento = $request->file('documento');
        $factura = $request->file('factura_archivo');
        $documentoPath = $documento ? $documento->store('finanzas/egresos', 'local') : null;
        $facturaPath = $factura ? $factura->store('finanzas/egresos', 'local') : null;

        $respuesta = $this->ejecutar(function () use ($libro, $datos, $categoriaId, $plantilla, $documentoPath, $facturaPath) {
            $libro->registrarEgreso(
                (int) $datos['cuenta_id'],
                $categoriaId,
                Carbon::parse($datos['fecha']),
                $this->importe($datos['importe']),
                $datos['proveedor_id'] ?? null,
                $datos['comprobante'] ?? null,
                $datos['observaciones'] ?? null,
                auth()->id(),
                [
                    'plantilla_id' => $plantilla?->id,
                    'usuario_staff_id' => $datos['usuario_staff_id'] ?? null,
                    'documento_path' => $documentoPath,
                    'factura_path' => $facturaPath,
                    'concepto' => $plantilla?->nombre ?: ($datos['descripcion'] ?? null),
                ],
            );
        }, 'finanzas.egresos', 'Egreso registrado.');
        if (session()->has('errors')) {
            Storage::disk('local')->delete(array_values(array_filter([$documentoPath, $facturaPath])));
        }

        return $respuesta;
    }

    public function retiros(): View
    {
        return view('finanzas.retiros', [
            'cuentas' => $this->cuentasActivas(),
            'filas' => FinRetiro::query()->with('cuenta')->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function guardarRetiro(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $datos = $request->validate([
            'persona' => ['required', 'string', 'max:160'],
            'fecha' => ['required', 'date'],
            'importe' => ['required', 'string', 'max:30'],
            'cuenta_id' => ['required', 'integer', 'exists:fin_cuentas,id'],
            'motivo' => ['required', 'string', 'max:180'],
            'autorizacion' => ['nullable', 'string', 'max:160'],
            'comprobante' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->ejecutar(function () use ($libro, $datos) {
            $libro->registrarRetiro(
                $datos['persona'],
                Carbon::parse($datos['fecha']),
                $this->importe($datos['importe']),
                (int) $datos['cuenta_id'],
                $datos['motivo'],
                $datos['autorizacion'] ?? null,
                $datos['comprobante'] ?? null,
                auth()->id(),
            );
        }, 'finanzas.retiros', 'Retiro registrado. No queda como gasto operativo.');
    }

    public function liquidaciones(): View
    {
        $bancard = FinCuenta::query()->where('codigo', FinanzasParametros::CUENTA_BANCARD)->first();
        $pendientes = collect();
        if ($bancard) {
            $pendientes = FinMovimiento::query()
                ->where('cuenta_id', $bancard->id)
                ->where('tipo', 'ingreso')
                ->where('referencia_tipo', 'cobro')
                ->whereNull('revertido_por_id')
                ->whereNotIn('referencia_id', function ($q) {
                    $q->select('cobro_id')->from('fin_liquidacion_cobros')->whereNotNull('cobro_id');
                })
                ->orderByDesc('fecha_efectiva')
                ->limit(200)
                ->get();
        }

        return view('finanzas.liquidaciones', [
            'pendientes' => $pendientes,
            'comisionSugerida' => FinanzasParametros::comisionBancard(),
            'filas' => FinLiquidacion::query()->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function guardarLiquidacion(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $datos = $request->validate([
            'cobro_ids' => ['required', 'array', 'min:1'],
            'cobro_ids.*' => ['integer'],
            'comision' => ['nullable', 'string', 'max:30'],
            'fecha_liquidacion' => ['required', 'date'],
            'referencia' => ['required', 'string', 'max:80'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        $comision = trim((string) ($datos['comision'] ?? ''));

        return $this->ejecutar(function () use ($libro, $datos, $comision) {
            $libro->liquidar(
                $datos['cobro_ids'],
                $comision === '' ? 0 : $this->importe($comision),
                Carbon::parse($datos['fecha_liquidacion']),
                $datos['referencia'],
                $datos['observaciones'] ?? null,
                auth()->id(),
            );
        }, 'finanzas.liquidaciones', 'Liquidación registrada. El neto pasó de Bancard al banco y no se sumó otra vez como ingreso.');
    }

    public function conciliacion(Request $request, FinanzasLibroService $libro): View
    {
        $cuentas = $this->cuentasActivas();
        $cuentaId = (int) $request->query('cuenta_id', $cuentas->first()->id ?? 0);
        $fecha = $request->query('fecha', now()->toDateString());

        return view('finanzas.conciliacion', [
            'cuentas' => $cuentas,
            'cuentaId' => $cuentaId,
            'fecha' => $fecha,
            'saldoSistema' => $cuentaId ? $libro->saldoHasta($cuentaId, Carbon::parse($fecha)) : 0,
            'filas' => FinConciliacion::query()->with('cuenta')->orderByDesc('id')->limit(20)->get(),
            'efectivoSinRendir' => (float) \App\Models\Cobro::query()->where('forma_pago', 'efectivo')->whereNull('cobro_rendicion_id')->sum('monto'),
        ]);
    }

    public function guardarConciliacion(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $datos = $request->validate([
            'cuenta_id' => ['required', 'integer', 'exists:fin_cuentas,id'],
            'fecha' => ['required', 'date'],
            'saldo_real' => ['required', 'string', 'max:30'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'ajustar' => ['nullable', 'in:1'],
        ]);

        return $this->ejecutar(function () use ($libro, $datos) {
            $libro->conciliar(
                (int) $datos['cuenta_id'],
                Carbon::parse($datos['fecha']),
                $this->importe($datos['saldo_real'], true),
                $datos['observaciones'] ?? null,
                ($datos['ajustar'] ?? null) === '1',
                auth()->id(),
            );
        }, 'finanzas.conciliacion', 'Conciliación guardada.');
    }

    public function cierres(Request $request, FinanzasLibroService $libro): View
    {
        $tipo = $request->query('tipo', 'mensual') === 'diario' ? 'diario' : 'mensual';
        $fecha = Carbon::parse($request->query('fecha', now()->toDateString()));
        $desde = $tipo === 'mensual' ? $fecha->copy()->startOfMonth() : $fecha->copy()->startOfDay();
        $hasta = $tipo === 'mensual' ? $fecha->copy()->endOfMonth() : $fecha->copy()->startOfDay();
        $preview = $this->cuentasActivas()->map(function (FinCuenta $cuenta) use ($libro, $desde, $hasta) {
            $desglose = $libro->desglose($cuenta->id, $desde, $hasta);

            return [
                'cuenta' => $cuenta,
                'inicial' => $libro->saldoHasta($cuenta->id, $desde->copy()->subDay()),
                'desglose' => $desglose,
                'calculado' => $libro->saldoHasta($cuenta->id, $hasta),
            ];
        });

        return view('finanzas.cierres', [
            'tipo' => $tipo,
            'fecha' => $fecha->toDateString(),
            'preview' => $preview,
            'filas' => FinCierre::query()->with('cuenta')->orderByDesc('id')->limit(40)->get(),
        ]);
    }

    public function guardarCierre(Request $request, FinanzasLibroService $libro): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'in:diario,mensual'],
            'fecha' => ['required', 'date'],
            'saldo_real' => ['required', 'array'],
            'saldo_real.*' => ['nullable', 'string', 'max:30'],
        ]);
        return $this->ejecutar(function () use ($libro, $datos) {
            $reales = [];
            foreach ($datos['saldo_real'] as $cuentaId => $valor) {
                $reales[(int) $cuentaId] = $this->importe((string) $valor, true);
            }
            $libro->cerrar($datos['tipo'], Carbon::parse($datos['fecha']), $reales, auth()->id());
        }, 'finanzas.cierres', 'Cierre registrado. El período queda protegido.');
    }

    public function cuentas(): View
    {
        return view('finanzas.cuentas', [
            'cuentas' => FinCuenta::query()->orderBy('orden')->orderBy('nombre')->get(),
            'categorias' => FinCategoria::query()->orderBy('clase')->orderBy('nombre')->get(),
        ]);
    }

    public function guardarCuenta(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:efectivo,banco,pasarela,otra'],
        ]);
        $base = Str::slug($datos['nombre'], '_');
        $codigo = $base !== '' ? $base : 'cuenta';
        $i = 2;
        while (FinCuenta::query()->where('codigo', $codigo)->exists()) {
            $codigo = $base.'_'.$i;
            $i++;
        }
        FinCuenta::query()->create([
            'codigo' => $codigo,
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'activa' => true,
            'orden' => (int) FinCuenta::query()->max('orden') + 1,
        ]);

        return redirect()->route('finanzas.cuentas')->with('success', 'Cuenta agregada.');
    }

    private function cuentasActivas()
    {
        return FinCuenta::query()->where('activa', true)->orderBy('orden')->get();
    }

    private function importe(string $valor, bool $permiteCero = false): float
    {
        $normalizado = FinanzasParametros::normalizarMonto($valor);
        if ($normalizado === '' || ! is_numeric($normalizado)) {
            throw new InvalidArgumentException('El importe no es válido.');
        }
        $numero = round((float) $normalizado, 2);
        if ($numero < 0 || (! $permiteCero && $numero <= 0)) {
            throw new InvalidArgumentException('El importe debe ser mayor a cero.');
        }

        return $numero;
    }

    private function ejecutar(callable $accion, string $ruta, string $ok): RedirectResponse
    {
        try {
            $accion();
        } catch (PeriodoCerradoException|InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['form' => $e->getMessage()]);
        }

        return redirect()->route($ruta)->with('success', $ok);
    }
}
