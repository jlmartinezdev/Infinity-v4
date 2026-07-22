<?php

namespace App\Http\Controllers;

use App\Models\SolicitudAcceso;
use App\Services\SolicitudAccesoService;
use Illuminate\Http\Request;

class SolicitudAccesoWebController extends Controller
{
    public function __construct(
        protected SolicitudAccesoService $service
    ) {}

    public function index(Request $request)
    {
        $query = SolicitudAcceso::with(['cliente', 'aprobador'])
            ->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobada' THEN 1 ELSE 2 END")
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

        return view('solicitudes-acceso.index', compact('solicitudes', 'pendientesCount'));
    }

    public function show(SolicitudAcceso $solicitud)
    {
        $solicitud->load(['cliente', 'aprobador']);
        $clienteExistente = $this->service->clienteCoincidePorDocumento($solicitud->cedula);
        $coincideBd = $clienteExistente !== null;

        return view('solicitudes-acceso.show', compact('solicitud', 'coincideBd', 'clienteExistente'));
    }

    public function aprobar(Request $request, SolicitudAcceso $solicitud)
    {
        try {
            $result = $this->service->aprobar($solicitud, $request->user());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('solicitudes-acceso.show', $solicitud)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('solicitudes-acceso.show', $result['solicitud'])
            ->with('success', 'Solicitud aprobada y vinculada correctamente.')
            ->with('clave_portal', $result['clave']);
    }

    public function rechazar(Request $request, SolicitudAcceso $solicitud)
    {
        if ($solicitud->estado !== SolicitudAcceso::ESTADO_PENDIENTE) {
            return redirect()
                ->route('solicitudes-acceso.show', $solicitud)
                ->with('error', 'La solicitud ya fue procesada.');
        }

        $solicitud->update([
            'estado' => SolicitudAcceso::ESTADO_RECHAZADA,
            'aprobado_por' => $request->user()->usuario_id,
            'aprobado_at' => now(),
        ]);

        return redirect()
            ->route('solicitudes-acceso.index')
            ->with('success', 'Solicitud rechazada.');
    }
}
