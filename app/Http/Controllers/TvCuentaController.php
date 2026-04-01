<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\TvCuenta;
use App\Models\TvCuentaAsignacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TvCuentaController extends Controller
{
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
        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:120'],
            'usuario_app' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'vencimiento_pago' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        TvCuenta::create($validated);

        return redirect()->route('tv-cuentas.index')
            ->with('success', 'Cuenta TV creada.');
    }

    public function edit(TvCuenta $tv_cuenta)
    {
        $tv_cuenta->load(['asignaciones.cliente']);

        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido', 'cedula']);

        return view('tv-cuentas.edit', compact('tv_cuenta', 'clientes'));
    }

    public function update(Request $request, TvCuenta $tv_cuenta)
    {
        $validated = $request->validate([
            'nombre' => ['nullable', 'string', 'max:120'],
            'usuario_app' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'vencimiento_pago' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

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

        $validated = $request->validate([
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'cliente_id'),
            ],
        ]);

        $existe = $tv_cuenta->asignaciones()->where('cliente_id', $validated['cliente_id'])->exists();
        if ($existe) {
            return redirect()->route('tv-cuentas.edit', $tv_cuenta)
                ->with('error', 'Ese cliente ya está asignado a esta cuenta.');
        }

        TvCuentaAsignacion::create([
            'tv_cuenta_id' => $tv_cuenta->id,
            'cliente_id' => $validated['cliente_id'],
        ]);

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', 'Cliente asignado (1 dispositivo).');
    }

    public function destroyAsignacion(TvCuenta $tv_cuenta, TvCuentaAsignacion $asignacion)
    {
        if ((int) $asignacion->tv_cuenta_id !== (int) $tv_cuenta->id) {
            abort(404);
        }

        $asignacion->delete();

        return redirect()->route('tv-cuentas.edit', $tv_cuenta)
            ->with('success', 'Asignación quitada.');
    }
}
