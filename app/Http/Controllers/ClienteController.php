<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CedulaPadron;
use App\Models\PoolIpAsignada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Listar clientes.
     */
    public function index(Request $request)
    {
        // Solo clientes registrados desde la sección Clientes (excluir solo_pedido)
        $query = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido'])
            ->orderBy('cliente_id', 'desc');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('cedula', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        $clientes = $query->with(['servicios.plan', 'servicios.pool'])->paginate(15)->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    /**
     * Verificar si una cédula ya está registrada como cliente (API para el formulario).
     */
    public function verificarCedula(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string'],
        ]);

        $cliente = Cliente::where('cedula', $request->cedula)->first();

        if (!$cliente) {
            return response()->json(['existe' => false]);
        }

        $activado = false;
        if ($cliente->estado === 'solo_pedido') {
            $cliente->update(['estado' => 'activo']);
            $activado = true;
        }

        return response()->json([
            'existe' => true,
            'activado' => $activado,
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
            ],
        ]);
    }

    /**
     * Buscar en tabla temp por nombre del cliente (para actualizar datos desde temp).
     */
    public function buscarTemp(Request $request)
    {
        $request->validate(['nombre' => ['required', 'string', 'max:200']]);
        $nombre = trim($request->nombre);
        if (strlen($nombre) < 2) {
            return response()->json(['encontrados' => []]);
        }
        try {
            $registros = DB::table('temp')
                ->where('nombre', 'like', '%' . $nombre . '%')
                ->limit(10)
                ->get(['celular', 'cedula', 'direccion', 'nombre', 'latitud', 'longitud']);
        } catch (\Throwable $e) {
            return response()->json(['encontrados' => [], 'error' => 'Tabla temp no disponible']);
        }
        return response()->json(['encontrados' => $registros]);
    }

    /**
     * Actualizar cliente con datos de temp (cedula, celular→telefono, direccion, url_ubicacion desde lat/lon).
     */
    public function actualizarDesdeTemp(Request $request, Cliente $cliente)
    {
        $lat = $this->normalizarCoordenada($request->input('latitud'));
        $lon = $this->normalizarCoordenada($request->input('longitud'));
        $request->merge([
            'latitud' => ($lat === '' || $lat === null) ? null : (is_numeric($lat) ? (float) $lat : null),
            'longitud' => ($lon === '' || $lon === null) ? null : (is_numeric($lon) ? (float) $lon : null),
        ]);

        $validated = $request->validate([
            'cedula' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'latitud' => ['nullable', 'numeric'],
            'longitud' => ['nullable', 'numeric'],
        ]);

        $updates = [];
        if (! empty(trim((string) ($validated['cedula'] ?? '')))) {
            $updates['cedula'] = trim($validated['cedula']);
        }
        if (! empty(trim((string) ($validated['celular'] ?? '')))) {
            $updates['telefono'] = trim($validated['celular']);
        }
        if (! empty(trim((string) ($validated['direccion'] ?? '')))) {
            $updates['direccion'] = trim($validated['direccion']);
        }
        $lat = $validated['latitud'] ?? null;
        $lon = $validated['longitud'] ?? null;
        if (is_numeric($lat) && is_numeric($lon) && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            $updates['url_ubicacion'] = 'https://www.google.com/maps?q=' . $lat . ',' . $lon;
        }

        if (! empty($updates)) {
            $cliente->update($updates);
        }

        return response()->json(['success' => true, 'cliente' => $cliente->fresh()]);
    }

    /**
     * Normaliza coordenada (ej: -26.531.725 → -26.531725).
     * Elimina el punto en posición 4 desde el final (separador de miles incorrecto).
     */
    private function normalizarCoordenada(mixed $valor): ?string
    {
        if ($valor === '' || $valor === null) {
            return null;
        }
        $s = trim((string) $valor);
        if ($s === '') {
            return null;
        }
        $len = strlen($s);
        if ($len >= 4 && $s[$len - 4] === '.') {
            $s = substr($s, 0, $len - 4) . substr($s, $len - 3);
        }
        return $s;
    }

    /**
     * Buscar clientes por nombre, apellido o cédula (JSON para autocompletado).
     */
    public function buscar(Request $request)
    {
        $q = $request->get('q', '');
        $q = trim($q);
        if (strlen($q) < 2) {
            return response()->json([]);
        }
        $clientes = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido', 'solo_pedido'])
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula']);
        return response()->json($clientes);
    }

    /**
     * Crear cliente por cédula buscando en padrón (API para editar pedido).
     * Si la cédula ya está cargada como cliente, devuelve ese cliente.
     * Si no existe, consulta padrón y crea el cliente (estado solo_pedido).
     */
    public function crearDesdePadron(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string', 'max:20'],
        ]);

        $cedulaNum = $request->cedula;

        $cliente = Cliente::where('cedula', $cedulaNum)->first();
        if ($cliente) {
            return response()->json([
                'existe' => true,
                'cliente' => [
                    'cliente_id' => $cliente->cliente_id,
                    'cedula' => $cliente->cedula,
                    'nombre' => $cliente->nombre,
                    'apellido' => $cliente->apellido,
                    'telefono' => $cliente->telefono,
                ],
            ]);
        }

        try {
            $padron = CedulaPadron::buscarPorCedula($cedulaNum);
        } catch (\Exception $e) {
            return response()->json([
                'encontrado' => false,
                'error' => 'Error al consultar el padrón: ' . $e->getMessage(),
            ], 500);
        }

        if (!$padron) {
            return response()->json([
                'encontrado' => false,
                'mensaje' => 'No se encontró en el padrón',
            ], 404);
        }

        $nombre = trim($padron->NOMBRE ?? '');
        $apellido = trim($padron->APELLIDO ?? '');
        $direccion = trim(implode(' ', array_filter([$padron->DIREC ?? '', $padron->DOMIC ?? ''])));

        $cliente = Cliente::create([
            'cedula' => $padron->NRODOC ?? $cedulaNum,
            'nombre' => $nombre ?: 'Sin nombre',
            'apellido' => $apellido ?: null,
            'direccion' => $direccion ?: null,
            'estado' => 'solo_pedido',
        ]);

        return response()->json([
            'creado' => true,
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'telefono' => $cliente->telefono,
            ],
        ]);
    }

    /**
     * Formulario crear cliente.
     */
    public function create()
    {
        return view('clientes.create');
    }

    /**
     * Guardar nuevo cliente.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20', Rule::unique('clientes', 'cedula')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'url_ubicacion' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'string', 'in:activo,inactivo,suspendido'],
        ]);

        Cliente::create($validated);

        return redirect()->route('clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    /**
     * Formulario editar cliente.
     */
    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    /**
     * Actualizar cliente.
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20', Rule::unique('clientes', 'cedula')->ignore($cliente->cliente_id, 'cliente_id')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'url_ubicacion' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'string', 'in:activo,inactivo,suspendido'],
        ]);

        $cliente->update($validated);

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    /**
     * Eliminar cliente y todos los registros relacionados en cascada.
     */
    public function destroy(Cliente $cliente)
    {
        DB::transaction(function () use ($cliente) {
            $clienteId = $cliente->cliente_id;

            // 1. Cobros
            $cliente->cobros()->delete();

            // 2. Facturas internas (los detalles se eliminan por cascade en la BD)
            $cliente->facturaInternas()->delete();

            // 3. Servicios (liberar IPs del pool primero)
            foreach ($cliente->servicios as $servicio) {
                if ($servicio->ip && $servicio->pool_id) {
                    PoolIpAsignada::where('pool_id', $servicio->pool_id)
                        ->where('ip', $servicio->ip)
                        ->update(['estado' => 'disponible']);
                }
            }
            $cliente->servicios()->delete();

            // 4. Pedidos (eliminar estado_pedido_detalles primero)
            foreach ($cliente->pedidos as $pedido) {
                $pedido->estadoPedidoDetalles()->delete();
            }
            $cliente->pedidos()->delete();

            // 5. Agenda
            $cliente->agendas()->delete();

            // 6. Tickets
            $cliente->tickets()->delete();

            // 7. Facturas electrónicas (y sus detalles)
            foreach ($cliente->facturas as $factura) {
                $factura->detalles()->delete();
            }
            $cliente->facturas()->delete();

            // 8. Cliente
            $cliente->delete();
        });

        return redirect()->route('clientes.index')->with('success', 'Cliente y registros relacionados eliminados correctamente.');
    }
}
