<?php

namespace App\Http\Controllers;

use App\Models\PushAviso;
use App\Services\ClienteAvisoPushService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvisoPushWebController extends Controller
{
    public function __construct(
        private readonly ClienteAvisoPushService $avisos
    ) {}

    public function index(): View
    {
        $historial = PushAviso::query()
            ->with('creador:usuario_id,name')
            ->latest('id')
            ->paginate(15);

        return view('avisos-push.index', [
            'historial' => $historial,
            'conPush' => $this->avisos->contarConPush(),
            'tipos' => PushAviso::tipos(),
            'puedeEditar' => auth()->user()?->tienePermiso('avisos-push.editar') ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'cuerpo' => ['required', 'string', 'max:500'],
            'tipo' => ['required', 'in:aviso,promocion'],
            'destino' => ['required', 'in:todos,seleccionados'],
            'cliente_ids' => ['nullable', 'array'],
            'cliente_ids.*' => ['integer', 'min:1'],
            'confirmar_todos' => ['nullable', 'accepted'],
        ], [
            'titulo.required' => 'Indicá un título.',
            'cuerpo.required' => 'Indicá el mensaje.',
            'confirmar_todos.accepted' => 'Confirmá el envío a todos los clientes con app.',
        ]);

        if ($validated['destino'] === 'todos' && ! $request->boolean('confirmar_todos')) {
            return back()
                ->withInput()
                ->with('error', 'Para enviar a todos, marcá la casilla de confirmación.');
        }

        $clienteIds = $validated['cliente_ids'] ?? [];
        if ($validated['destino'] === 'seleccionados' && $clienteIds === []) {
            return back()
                ->withInput()
                ->with('error', 'Seleccioná al menos un cliente con app instalada.');
        }

        $aviso = $this->avisos->enviar(
            $validated['titulo'],
            $validated['cuerpo'],
            $validated['tipo'],
            $validated['destino'],
            $clienteIds,
            auth()->user()?->usuario_id
        );

        if ($aviso->total_destinatarios === 0) {
            return redirect()
                ->route('avisos-push.index')
                ->with('warning', 'No hay clientes con push activo para ese destino.');
        }

        return redirect()
            ->route('avisos-push.index')
            ->with('success', sprintf(
                'Aviso #%d enviado: %d OK, %d fallidos (de %d).',
                $aviso->id,
                $aviso->enviados,
                $aviso->fallidos,
                $aviso->total_destinatarios
            ));
    }

    public function reenviar(PushAviso $aviso)
    {
        $nuevo = $this->avisos->reenviar($aviso, auth()->user()?->usuario_id);

        if ($nuevo->total_destinatarios === 0) {
            return redirect()
                ->route('avisos-push.index')
                ->with('warning', 'No hay destinatarios con push activo para reenviar.');
        }

        return redirect()
            ->route('avisos-push.index')
            ->with('success', sprintf(
                'Reenviado (#%d → #%d): %d OK, %d fallidos.',
                $aviso->id,
                $nuevo->id,
                $nuevo->enviados,
                $nuevo->fallidos
            ));
    }

    public function destroy(PushAviso $aviso)
    {
        $id = $aviso->id;
        $aviso->delete();

        return redirect()
            ->route('avisos-push.index')
            ->with('success', 'Aviso #'.$id.' eliminado del historial.');
    }

    public function buscar(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        return response()->json($this->avisos->buscarConPush($q));
    }
}
