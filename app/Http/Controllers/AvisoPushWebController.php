<?php

namespace App\Http\Controllers;

use App\Models\PushAviso;
use App\Models\PushAvisoEntrega;
use App\Models\User;
use App\Services\ClienteAvisoPushService;
use App\Services\ClientePushNotifier;
use App\Support\PushAvisoPlantilla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
            ->withCount('entregas')
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
        // «accepted» es una regla implícita: nullable no la saltea si la casilla
        // no viaja en el POST, y el mensaje quedaba oculto. La confirmación se
        // controla más abajo, con un aviso visible.
        if ($request->input('destino') !== 'por_vencer') {
            $request->merge(['dias' => null]);
        }

        $validator = Validator::make($request->all(), [
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
        ], [
            'titulo.required' => 'Indicá un título.',
            'cuerpo.required' => 'Indicá el mensaje.',
            'tipo.in' => 'Elegí un tipo de aviso.',
            'destino.in' => 'Elegí un destino válido.',
            'dias.integer' => 'Los días tienen que ser un número entero.',
            'dias.min' => 'Los días tienen que ser entre 1 y 30.',
            'dias.max' => 'Los días tienen que ser entre 1 y 30.',
        ]);

        if ($validator->fails()) {
            Log::warning('Aviso push rechazado por validación', [
                'destino' => $request->input('destino'),
                'campos' => $validator->errors()->keys(),
            ]);

            return back()
                ->withInput()
                ->withErrors($validator)
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();

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

    public function show(Request $request, PushAviso $aviso): View
    {
        $estado = (string) $request->query('estado', '');
        if (! array_key_exists($estado, PushAvisoEntrega::estados())) {
            $estado = '';
        }
        $q = trim((string) $request->query('q', ''));

        $conteos = $aviso->entregas()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $lista = $aviso->entregas()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->whereHas('cliente', fn ($cliente) => $cliente->buscarTexto($q));
                    if (ctype_digit($q)) {
                        $inner->orWhere('cliente_id', (int) $q);
                    }
                });
            })
            ->orderByRaw("CASE estado WHEN 'fallido' THEN 0 WHEN 'omitido' THEN 1 ELSE 2 END")
            ->orderBy('cliente_id')
            ->paginate(40)
            ->withQueryString();

        return view('avisos-push.show', [
            'aviso' => $aviso->load('creador:usuario_id,name'),
            'lista' => $lista,
            'conteos' => [
                'enviado' => (int) ($conteos[PushAvisoEntrega::ENVIADO] ?? 0),
                'fallido' => (int) ($conteos[PushAvisoEntrega::FALLIDO] ?? 0),
                'omitido' => (int) ($conteos[PushAvisoEntrega::OMITIDO] ?? 0),
            ],
            'estado' => $estado,
            'q' => $q,
            'estados' => PushAvisoEntrega::estados(),
            'tipos' => PushAviso::tipos(),
            'puedeVerCliente' => auth()->user()?->tienePermiso('clientes.ver') ?? false,
            'tieneDetalle' => ((int) $conteos->sum()) > 0,
        ]);
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
