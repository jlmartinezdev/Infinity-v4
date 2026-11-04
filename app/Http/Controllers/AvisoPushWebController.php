<?php

namespace App\Http\Controllers;

use App\Models\PushAviso;
use App\Models\User;
use App\Services\ClienteAvisoPushService;
use App\Services\ClientePushNotifier;
use App\Support\PushAvisoPlantilla;
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

        $dias = $this->normalizarDias(old('dias'));
        $conteos = $this->avisos->conteos($dias);

        return view('avisos-push.index', [
            'historial' => $historial,
            'conteos' => $conteos,
            'conPush' => $conteos['todos'] ?? 0,
            'tipos' => PushAviso::tipos(),
            'destinos' => PushAviso::destinos(),
            'descripciones' => PushAviso::descripcionesDestino(),
            'variables' => PushAvisoPlantilla::variables(),
            'diasDefault' => $dias,
            'puedeEditar' => auth()->user()?->tienePermiso('avisos-push.editar') ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'cuerpo' => ['required', 'string', 'max:500'],
            'tipo' => ['required', 'in:aviso,promocion'],
            'destino' => ['required', 'in:'.implode(',', array_keys(PushAviso::destinos()))],
            'dias' => ['nullable', 'integer', 'min:1', 'max:30'],
            'cliente_ids' => ['nullable', 'array'],
            'cliente_ids.*' => ['integer', 'min:1'],
            'cliente_id' => ['nullable', 'integer', 'min:1'],
            'usuario_ids' => ['nullable', 'array'],
            'usuario_ids.*' => ['integer', 'min:1'],
            'confirmar_masivo' => ['nullable', 'accepted'],
            'confirmar_todos' => ['nullable', 'accepted'],
        ], [
            'titulo.required' => 'Indicá un título.',
            'cuerpo.required' => 'Indicá el mensaje.',
            'confirmar_masivo.accepted' => 'Confirmá el envío al grupo.',
        ]);

        $destino = $validated['destino'];
        $masivo = $destino !== 'seleccionados';
        $confirmado = $request->boolean('confirmar_masivo') || $request->boolean('confirmar_todos');
        $dias = $destino === 'por_vencer'
            ? $this->normalizarDias($validated['dias'] ?? null)
            : null;

        if ($masivo && ! $confirmado) {
            return back()
                ->withInput()
                ->with('error', 'Para enviar a un grupo, marcá la casilla de confirmación.');
        }

        $clienteIds = $this->resolverClienteIds($validated);
        if ($destino === 'seleccionados' && $clienteIds === []) {
            return back()
                ->withInput()
                ->with('error', 'Seleccioná al menos un cliente con app instalada.');
        }

        if ($masivo && $this->avisos->contarDestino($destino, $dias) === 0) {
            $etiqueta = PushAviso::destinos()[$destino] ?? $destino;

            return back()
                ->withInput()
                ->with('error', 'No hay clientes con la app en «'.$etiqueta.'».');
        }

        $aviso = $this->avisos->enviar(
            $validated['titulo'],
            $validated['cuerpo'],
            $validated['tipo'],
            $destino,
            $clienteIds,
            auth()->user()?->usuario_id,
            $dias
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

    public function segmento(Request $request)
    {
        $destino = (string) $request->get('destino', '');
        if (! array_key_exists($destino, PushAviso::destinos()) || $destino === 'seleccionados') {
            return response()->json(['count' => 0]);
        }

        $dias = $this->normalizarDias($request->get('dias'));

        return response()->json([
            'count' => $this->avisos->contarDestino($destino, $dias),
            'destino' => $destino,
            'dias' => $dias,
        ]);
    }

    private function normalizarDias(mixed $dias): int
    {
        if ($dias === null || $dias === '') {
            $n = ClientePushNotifier::diasAntesVencimiento();
        } else {
            $n = (int) $dias;
        }

        return max(1, min(30, $n));
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
