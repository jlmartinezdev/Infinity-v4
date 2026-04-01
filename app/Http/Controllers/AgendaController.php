<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\Pedido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    /**
     * Listar agenda (citas de instalación). Vista día con mini calendario.
     */
    public function index(Request $request)
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->fecha)->startOfDay()
            : today();

        $query = Agenda::with(['cliente', 'pedido.cliente', 'pedido.plan', 'usuario']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }

        $citasDia = (clone $query)
            ->whereDate('fecha', $fecha)
            ->orderBy('hora_inicio')
            ->get();

        $mesInicio = $fecha->copy()->startOfMonth();
        $mesFin = $fecha->copy()->endOfMonth();
        $fechasConCitas = (clone $query)
            ->whereBetween('fecha', [$mesInicio, $mesFin])
            ->selectRaw('DATE(fecha) as dia')
            ->distinct()
            ->pluck('dia')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip()
            ->toArray();

        $tecnicos = User::where('estado', 'activo')->orderBy('name')->get();
        $hoy = today()->format('Y-m-d');
        $fechaStr = $fecha->format('Y-m-d');

        return view('agenda.index', [
            'fecha' => $fecha,
            'fechaStr' => $fechaStr,
            'citasDia' => $citasDia,
            'tecnicos' => $tecnicos,
            'mesInicio' => $mesInicio,
            'mesFin' => $mesFin,
            'hoy' => $hoy,
            'fechasConCitas' => $fechasConCitas,
        ]);
    }

    /**
     * Redirige al formulario de nueva cita con el pedido preseleccionado.
     */
    public function createFromPedido(Pedido $pedido)
    {
        return redirect()->route('agenda.create', ['pedido_id' => $pedido->pedido_id]);
    }

    /**
     * Formulario crear cita.
     */
    public function create(Request $request)
    {
        $pedidos = Pedido::with('cliente')->orderBy('fecha_pedido', 'desc')->get();
        $tecnicos = User::where('estado', 'activo')->orderBy('name')->get();
        $pedidoId = $request->query('pedido_id');
        $fechaPreseleccionada = $request->query('fecha');
        $clienteId = $request->query('cliente_id');

        return view('agenda.create', compact('pedidos', 'tecnicos', 'pedidoId', 'fechaPreseleccionada', 'clienteId'));
    }

    /**
     * Guardar nueva cita.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:pedido,general'],
            'titulo' => ['nullable', 'string', 'max:120'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,pedido_id'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'usuario_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
            'estado' => ['nullable', 'string', 'in:programado,en_progreso,completado,cancelado,no_asistio'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if ($validated['tipo'] === 'pedido') {
            $request->validate(['pedido_id' => ['required', 'integer', 'exists:pedidos,pedido_id']]);
            $validated['pedido_id'] = $request->pedido_id;
        } else {
            $validated['pedido_id'] = null;
            $request->validate(['titulo' => ['required', 'string', 'max:120']]);
            $validated['titulo'] = $request->titulo;
        }

        Agenda::create([
            'tipo' => $validated['tipo'],
            'titulo' => $validated['titulo'] ?? null,
            'cliente_id' => $validated['cliente_id'] ?? null,
            'pedido_id' => $validated['pedido_id'] ?? null,
            'fecha' => $validated['fecha'],
            'hora_inicio' => $validated['hora_inicio'],
            'hora_fin' => $validated['hora_fin'] ?? null,
            'usuario_id' => $validated['usuario_id'] ?? null,
            'estado' => $validated['estado'] ?? 'programado',
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        return redirect()->route('agenda.index')->with('success', 'Cita agregada a la agenda correctamente.');
    }

    /**
     * Formulario editar cita.
     */
    public function edit(Agenda $agenda)
    {
        $pedidos = Pedido::with('cliente')->orderBy('fecha_pedido', 'desc')->get();
        $tecnicos = User::where('estado', 'activo')->orderBy('name')->get();

        return view('agenda.edit', compact('agenda', 'pedidos', 'tecnicos'));
    }

    /**
     * Actualizar cita.
     */
    public function update(Request $request, Agenda $agenda)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:pedido,general'],
            'titulo' => ['nullable', 'string', 'max:120'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,pedido_id'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'usuario_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
            'estado' => ['required', 'string', 'in:programado,en_progreso,completado,cancelado,no_asistio'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if ($validated['tipo'] === 'pedido') {
            $request->validate(['pedido_id' => ['required', 'integer', 'exists:pedidos,pedido_id']]);
            $validated['pedido_id'] = $request->pedido_id;
        } else {
            $validated['pedido_id'] = null;
            $request->validate(['titulo' => ['required', 'string', 'max:120']]);
            $validated['titulo'] = $request->titulo;
        }
        $validated['cliente_id'] = $request->input('cliente_id') ?: null;

        $agenda->update($validated);

        if ($validated['estado'] === 'completado' && $agenda->tipo === 'pedido' && $agenda->pedido_id) {
            $agenda->pedido->update(['estado_instalado' => true]);
        }

        return redirect()->route('agenda.index')->with('success', 'Cita actualizada correctamente.');
    }

    /**
     * Eliminar cita.
     */
    public function destroy(Agenda $agenda)
    {
        $agenda->delete();

        return redirect()->route('agenda.index')->with('success', 'Cita eliminada de la agenda.');
    }
}
