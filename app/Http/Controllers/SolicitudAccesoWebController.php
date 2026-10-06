<?php

namespace App\Http\Controllers;

use App\Models\SolicitudAcceso;
use App\Models\WhatsappMensaje;
use App\Services\ClientePortalUserService;
use App\Services\SolicitudAccesoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SolicitudAccesoWebController extends Controller
{
    public function __construct(
        protected SolicitudAccesoService $service,
        protected ClientePortalUserService $portal
    ) {}

    public function index(Request $request)
    {
        $query = SolicitudAcceso::with(['cliente.usuarioPortal', 'aprobador'])
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'pendiente_verificacion' THEN 1 WHEN 'aprobada' THEN 2 ELSE 3 END")
            ->orderByDesc('id');

        if ($request->has('estado')) {
            if ($request->estado !== '' && $request->estado !== null) {
                $query->where('estado', $request->estado);
            }
        } else {
            $query->where('estado', SolicitudAcceso::ESTADO_PENDIENTE);
        }

        if ($request->filled('buscar')) {
            $q = trim((string) $request->buscar);
            $query->where(function ($builder) use ($q) {
                $builder->where('nombre', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%");
            });
        }

        $solicitudes = $query->paginate(20)->withQueryString();
        $pendientesCount = SolicitudAcceso::pendientes()->count();
        $verificacionCount = SolicitudAcceso::query()
            ->where('estado', SolicitudAcceso::ESTADO_PENDIENTE_VERIFICACION)
            ->count();
        $aprobadasCount = SolicitudAcceso::query()->where('estado', SolicitudAcceso::ESTADO_APROBADA)->count();
        $rechazadasCount = SolicitudAcceso::query()->where('estado', SolicitudAcceso::ESTADO_RECHAZADA)->count();

        $waPorSolicitud = WhatsappMensaje::query()
            ->whereIn('contexto_tipo', ['acceso_aprobado', 'acceso_rechazado', 'solicitud_verificacion_ok'])
            ->whereIn('contexto_id', $solicitudes->getCollection()->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('contexto_id');

        return view('solicitudes-acceso.index', compact(
            'solicitudes',
            'pendientesCount',
            'verificacionCount',
            'aprobadasCount',
            'rechazadasCount',
            'waPorSolicitud'
        ));
    }

    public function show(SolicitudAcceso $solicitud)
    {
        $solicitud->load(['cliente.usuarioPortal', 'aprobador']);
        $clienteExistente = $solicitud->cliente
            ?: $this->service->clienteCoincidePorDocumento($solicitud->cedula);
        if ($clienteExistente && ! $clienteExistente->relationLoaded('usuarioPortal')) {
            $clienteExistente->load('usuarioPortal');
        }
        $coincideBd = $clienteExistente !== null;
        $usuarioPortal = $clienteExistente?->usuarioPortal;

        $avisosWhatsapp = WhatsappMensaje::query()
            ->whereIn('contexto_tipo', ['acceso_aprobado', 'acceso_rechazado'])
            ->where('contexto_id', $solicitud->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $esAdmin = auth()->user()?->rol
            && strtolower((string) auth()->user()->rol->descripcion) === 'administrador';

        return view('solicitudes-acceso.show', compact(
            'solicitud',
            'coincideBd',
            'clienteExistente',
            'usuarioPortal',
            'avisosWhatsapp',
            'esAdmin'
        ));
    }

    public function reenviarClave(Request $request, SolicitudAcceso $solicitud)
    {
        try {
            $result = $this->service->regenerarYReenviarClave($solicitud);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('solicitudes-acceso.show', $solicitud)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('solicitudes-acceso.index', ['estado' => 'aprobada'])
            ->with('success', 'Nueva clave generada y enviada por WhatsApp (si hay número en la solicitud).')
            ->with('clave_portal', $result['clave']);
    }

    public function aprobar(Request $request, SolicitudAcceso $solicitud)
    {
        $validated = $request->validate([
            'cliente_id_vinculacion' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'documento_corregido' => ['nullable', 'string', 'max:20'],
            'nombre_corregido' => ['nullable', 'string', 'max:200'],
            'whatsapp_corregido' => ['nullable', 'string', 'max:30'],
            'direccion_corregida' => ['nullable', 'string', 'max:500'],
            'actualizar_telefono' => ['nullable', 'boolean'],
            'actualizar_ubicacion' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->service->aprobar($solicitud, $request->user(), [
                'cliente_id_vinculacion' => $validated['cliente_id_vinculacion'] ?? null,
                'documento_corregido' => $validated['documento_corregido'] ?? null,
                'nombre_corregido' => $validated['nombre_corregido'] ?? null,
                'whatsapp_corregido' => array_key_exists('whatsapp_corregido', $validated)
                    ? $validated['whatsapp_corregido']
                    : null,
                'direccion_corregida' => array_key_exists('direccion_corregida', $validated)
                    ? $validated['direccion_corregida']
                    : null,
                'actualizar_telefono' => $request->boolean('actualizar_telefono'),
                'actualizar_ubicacion' => $request->boolean('actualizar_ubicacion'),
            ]);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('solicitudes-acceso.show', $solicitud)
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('solicitudes-acceso.index', ['estado' => 'aprobada'])
            ->with('success', 'Solicitud aprobada y vinculada correctamente.')
            ->with('clave_portal', $result['clave']);
    }

    /**
     * Guardar correcciones de datos (sin aprobar todavía).
     */
    public function actualizar(Request $request, SolicitudAcceso $solicitud)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'cedula' => ['required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->actualizarDatosPendiente($solicitud, $validated);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('solicitudes-acceso.show', $solicitud)
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('solicitudes-acceso.show', $solicitud)
            ->with('success', 'Datos de la solicitud corregidos. Revisá el cruce y aprobá cuando esté listo.');
    }

    /**
     * Autocomplete para vincular manualmente (mismo permiso que editar solicitud).
     */
    public function buscarClientes(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = \App\Models\Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido', 'solo_pedido'])
            ->buscarTexto($q)
            ->orderBy('nombre')
            ->limit(20)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula', 'telefono', 'estado']);

        return response()->json($clientes->map(static function ($c) {
            return [
                'cliente_id' => $c->cliente_id,
                'nombre' => $c->nombre,
                'apellido' => $c->apellido,
                'cedula' => $c->cedula,
                'telefono' => $c->telefono,
                'estado' => $c->estado,
                'label' => trim(($c->nombre ?? '').' '.($c->apellido ?? ''))
                    .' · CI '.($c->cedula ?: '—')
                    .' · #'.$c->cliente_id,
            ];
        })->values());
    }

    public function rechazar(Request $request, SolicitudAcceso $solicitud)
    {
        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->rechazar($solicitud, $request->user(), $validated['motivo'] ?? null);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('solicitudes-acceso.show', $solicitud)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('solicitudes-acceso.index', ['estado' => 'rechazada'])
            ->with('success', 'Solicitud rechazada.');
    }

    /**
     * Admin: editar nombre/estado del usuario portal vinculado a la solicitud aprobada.
     */
    public function actualizarAcceso(Request $request, SolicitudAcceso $solicitud)
    {
        $user = $this->usuarioPortalDeSolicitud($solicitud);
        if (! $user) {
            return back()->with('error', 'No hay usuario portal vinculado a esta solicitud.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'in:activo,suspendido,pendiente_aprobacion'],
        ]);

        $user->name = $validated['name'];

        if ($validated['estado'] === 'suspendido') {
            $user->save();
            $this->portal->suspenderAcceso($user->fresh());
        } elseif ($validated['estado'] === 'activo' && $user->estado !== 'activo') {
            $user->save();
            $this->portal->reactivarAcceso($user->fresh());
        } else {
            $user->estado = $validated['estado'];
            $user->save();
        }

        return redirect()
            ->route('solicitudes-acceso.show', $solicitud)
            ->with('success', 'Acceso app actualizado.');
    }

    public function suspenderAcceso(SolicitudAcceso $solicitud)
    {
        $user = $this->usuarioPortalDeSolicitud($solicitud);
        if (! $user) {
            return back()->with('error', 'No hay usuario portal vinculado.');
        }

        $this->portal->suspenderAcceso($user);

        return redirect()
            ->route('solicitudes-acceso.show', $solicitud)
            ->with('success', 'Acceso app dado de baja. Sesiones y push cerrados.');
    }

    public function reactivarAcceso(SolicitudAcceso $solicitud)
    {
        $user = $this->usuarioPortalDeSolicitud($solicitud);
        if (! $user) {
            return back()->with('error', 'No hay usuario portal vinculado.');
        }

        $this->portal->reactivarAcceso($user);

        return redirect()
            ->route('solicitudes-acceso.show', $solicitud)
            ->with('success', 'Acceso app reactivado.');
    }

    public function eliminarAcceso(SolicitudAcceso $solicitud)
    {
        $user = $this->usuarioPortalDeSolicitud($solicitud);
        if (! $user) {
            return back()->with('error', 'No hay usuario portal vinculado.');
        }

        $this->portal->eliminarAcceso($user);

        return redirect()
            ->route('solicitudes-acceso.show', $solicitud)
            ->with('success', 'Usuario portal eliminado. La solicitud se mantiene; el cliente ISP no se borró.');
    }

    /**
     * Admin: borrar el registro de la solicitud (pendiente/aprobada/rechazada).
     * No borra el cliente ISP; si había usuario portal, también lo elimina.
     */
    public function destroy(SolicitudAcceso $solicitud)
    {
        $user = $this->usuarioPortalDeSolicitud($solicitud);
        if ($user) {
            $this->portal->eliminarAcceso($user);
        }

        if ($solicitud->frente_path) {
            Storage::disk('public')->delete($solicitud->frente_path);
        }

        $id = $solicitud->id;
        $solicitud->delete();

        return redirect()
            ->route('solicitudes-acceso.index', ['estado' => ''])
            ->with('success', "Solicitud #{$id} eliminada.");
    }

    private function usuarioPortalDeSolicitud(SolicitudAcceso $solicitud)
    {
        $solicitud->loadMissing('cliente.usuarioPortal');
        if ($solicitud->cliente?->usuarioPortal) {
            return $solicitud->cliente->usuarioPortal;
        }

        $cliente = $this->service->clienteCoincidePorDocumento($solicitud->cedula);
        if (! $cliente) {
            return null;
        }
        $cliente->load('usuarioPortal');

        return $cliente->usuarioPortal;
    }
}
