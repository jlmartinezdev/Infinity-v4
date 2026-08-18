<?php

namespace App\Http\Controllers;

use App\Models\PushAviso;
use App\Models\User;
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
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'usuario_ids' => ['nullable', 'array'],
            'usuario_ids.*' => ['integer', 'min:1'],
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

        $clienteIds = $this->resolverClienteIds($validated);
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

        $detalleOmitidos = $aviso->omitidos
            ? sprintf(' · %d sin token', $aviso->omitidos)
            : '';

        if ((int) $aviso->enviados === 0) {
            return redirect()
                ->route('avisos-push.index')
                ->with('warning', sprintf(
                    'Aviso #%d: %d OK de %d%s. Destino: %s.',
                    $aviso->id,
                    $aviso->enviados,
                    $aviso->total_destinatarios,
                    $detalleOmitidos,
                    $aviso->etiquetaDestino()
                ));
        }

        return redirect()
            ->route('avisos-push.index')
            ->with('success', sprintf(
                'Aviso #%d enviado: %d OK, %d fallidos (de %d)%s.',
                $aviso->id,
                $aviso->enviados,
                $aviso->fallidos,
                $aviso->total_destinatarios,
                $detalleOmitidos
            ));
    }

    public function reenviar(PushAviso $aviso)
    {
        $nuevo = $this->avisos->reenviar($aviso, auth()->user()?->usuario_id);

        if ((int) $nuevo->enviados === 0) {
            return redirect()
                ->route('avisos-push.index')
                ->with('warning', sprintf(
                    'Reenviado (#%d → #%d): %d OK de %d. Destino: %s.',
                    $aviso->id,
                    $nuevo->id,
                    $nuevo->enviados,
                    $nuevo->total_destinatarios,
                    $nuevo->etiquetaDestino()
                ));
        }

        return redirect()
            ->route('avisos-push.index')
            ->with('success', sprintf(
                'Reenviado (#%d → #%d): %d OK, %d fallidos (de %d).',
                $aviso->id,
                $nuevo->id,
                $nuevo->enviados,
                $nuevo->fallidos,
                $nuevo->total_destinatarios
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

    /**
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function resolverClienteIds(array $validated): array
    {
        $ids = collect($validated['cliente_ids'] ?? [])
            ->when(! empty($validated['cliente_id']), fn ($c) => $c->push($validated['cliente_id']))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            return $ids->all();
        }

        $usuarioIds = collect($validated['usuario_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($usuarioIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('usuario_id', $usuarioIds)
            ->whereNotNull('cliente_id')
            ->pluck('cliente_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
