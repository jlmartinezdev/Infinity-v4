<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\TvCuenta;
use App\Models\TvCuentaAsignacion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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

    public function index()
    {
        $cuentas = TvCuenta::query()
            ->withCount('asignaciones')
            ->orderByDesc('id')
            ->paginate(20);

        return view('tv-cuentas.index', compact('cuentas'));
    }

    public function create()
    {
        return view('tv-cuentas.create');
    }

    public function store(Request $request)
    {
        $rules = [
            'nombre' => ['nullable', 'string', 'max:120'],
            'usuario_app' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'dia_aviso_vencimiento' => ['required', 'integer', 'between:1,31'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'cliente_id_prefill' => ['nullable', 'integer', Rule::exists('clientes', 'cliente_id')],
            'precio_perfil_1' => ['nullable', 'numeric', 'min:0'],
            'precio_perfil_2' => ['nullable', 'numeric', 'min:0'],
            'precio_perfil_3' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->perfilesV2Disponible()) {
            $rules['perfil_1'] = ['required', 'string', 'max:120'];
            $rules['perfil_2'] = ['required', 'string', 'max:120'];
            $rules['perfil_3'] = ['required', 'string', 'max:120'];
        }

        $validated = $request->validate($rules);
        $validated['vencimiento_pago'] = $this->calcularFechaVencimiento((int) $validated['dia_aviso_vencimiento']);

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
        ]);

        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido', 'cedula']);
        $servicios = Servicio::query()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->whereIn('estado', [Servicio::ESTADO_ACTIVO, Servicio::ESTADO_SUSPENDIDO])
            ->orderBy('cliente_id')
            ->orderBy('servicio_id')
            ->get(['servicio_id', 'cliente_id', 'plan_id', 'estado', 'app_tv']);

        return view('tv-cuentas.edit', compact('tv_cuenta', 'clientes', 'servicios', 'asignacionPerfilesV2'));
    }

    public function update(Request $request, TvCuenta $tv_cuenta)
    {
        $rules = [
            'nombre' => ['nullable', 'string', 'max:120'],
            'usuario_app' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'dia_aviso_vencimiento' => ['required', 'integer', 'between:1,31'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'precio_perfil_1' => ['nullable', 'numeric', 'min:0'],
            'precio_perfil_2' => ['nullable', 'numeric', 'min:0'],
            'precio_perfil_3' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($this->perfilesV2Disponible()) {
            $rules['perfil_1'] = ['required', 'string', 'max:120'];
            $rules['perfil_2'] = ['required', 'string', 'max:120'];
            $rules['perfil_3'] = ['required', 'string', 'max:120'];
        }

        $validated = $request->validate($rules);
        $validated['vencimiento_pago'] = $this->calcularFechaVencimiento((int) $validated['dia_aviso_vencimiento']);

        $tv_cuenta->update($validated);

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', 'Cuenta TV actualizada.');
    }

    public function destroy(TvCuenta $tv_cuenta)
    {
        $tv_cuenta->delete();

        return redirect()->route('tv-cuentas.index')
            ->with('success', 'Cuenta TV eliminada.');
    }

    public function storeAsignacion(Request $request, TvCuenta $tv_cuenta)
    {
        if ($tv_cuenta->asignaciones()->count() >= TvCuenta::MAX_ASIGNACIONES) {
            return redirect()->route('tv-cuentas.edit', $tv_cuenta)
                ->with('error', 'Esta cuenta ya tiene 3 usuarios asignados (máximo de dispositivos).');
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
        ];

        if ($this->asignacionPerfilesV2Disponible()) {
            $rules['perfil_numero'] = ['required', 'integer', 'between:1,3'];
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
                    ->with('error', 'Ese perfil ya está asignado en esta cuenta.');
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
                $raw = match ((int) $validated['perfil_numero']) {
                    1 => $tv_cuenta->precio_perfil_1,
                    2 => $tv_cuenta->precio_perfil_2,
                    3 => $tv_cuenta->precio_perfil_3,
                    default => null,
                };
                $precioAplicado = $raw !== null ? (float) $raw : 0.0;
            }
            $payload['precio_aplicado'] = $precioAplicado;
        }

        TvCuentaAsignacion::create($payload);

        $this->sincronizarAppTvEnServicio((int) $servicio->servicio_id);

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', $this->asignacionPerfilesV2Disponible()
                ? 'Servicio asignado al perfil correctamente.'
                : 'Servicio asignado correctamente.');
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
