<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Olt;
use App\Services\Olt\OltOnuSyncService;
use Illuminate\Http\Request;
use Throwable;

class OltController extends Controller
{
    private function reglasGestion(): array
    {
        return [
            'gestion_usuario' => ['nullable', 'string', 'max:64'],
            'gestion_password' => ['nullable', 'string', 'max:255'],
            'gestion_protocolo' => ['nullable', 'in:telnet,ssh'],
            'gestion_puerto' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'gestion_enable_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function normalizarGestion(array $validated, ?Olt $olt = null): array
    {
        $validated['gestion_protocolo'] = $validated['gestion_protocolo'] ?? 'telnet';
        if (empty($validated['gestion_usuario'])) {
            $validated['gestion_usuario'] = config('olt.vsol.default_user', 'admin');
        }
        if ($olt && empty($validated['gestion_password'])) {
            unset($validated['gestion_password']);
        }
        if ($olt && empty($validated['gestion_enable_password'])) {
            unset($validated['gestion_enable_password']);
        }

        return $validated;
    }
    public function index(Request $request)
    {
        $query = Olt::with(['nodo', 'oltPuertos'])->orderBy('codigo')->orderBy('ip');

        if ($request->filled('nodo_id')) {
            $query->where('nodo_id', $request->nodo_id);
        }
        if ($request->filled('marca')) {
            $query->where('marca', 'like', '%'.$request->marca.'%');
        }

        $olts = $query->paginate(15)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.index', compact('olts', 'nodos'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.create', compact('nodos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'marca' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puerto' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ], $this->reglasGestion()));

        $validated = $this->normalizarGestion($validated);

        $validated['cantidad_puerto'] = $validated['cantidad_puerto'] ?? 8;
        $validated['estado'] = $validated['estado'] ?? 'activo';
        if (empty($validated['codigo'])) {
            unset($validated['codigo']);
        }

        $olt = Olt::create($validated);
        if (empty($olt->codigo)) {
            $olt->update(['codigo' => 'OLT-'.$olt->olt_id]);
        }

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT creado correctamente.');
    }

    public function show(Olt $olt, OltOnuSyncService $syncService)
    {
        $onuSyncNotice = $syncService->sincronizarAlVisualizar($olt);

        $olt->refresh();
        $olt->load(['nodo', 'oltPuertos', 'salidaPons.oltPuerto']);
        $onus = $olt->onus()
            ->orderBy('pon_key')
            ->orderBy('onu_index')
            ->get();
        $onusOnline = $onus->filter->estadoEsOnline()->count();
        $onusOffline = $onus->filter->estadoEsOffline()->count();
        $onusDesconocido = $onus->count() - $onusOnline - $onusOffline;
        $onuCountPorPuerto = $olt->onus()->registradas()->get()->groupBy('pon_port')->map->count();

        return view('olts.show', compact('olt', 'onus', 'onusOnline', 'onusOffline', 'onusDesconocido', 'onuSyncNotice', 'onuCountPorPuerto'));
    }

    public function showPonOnus(Olt $olt, int $ponPort)
    {
        $olt->load(['nodo', 'oltPuertos']);
        $ponPuerto = $olt->oltPuertos->firstWhere('numero', $ponPort);

        $onus = $olt->onus()
            ->registradas()
            ->where('pon_port', $ponPort)
            ->orderBy('onu_index')
            ->get();

        $onusOnline = $onus->filter->estadoEsOnline()->count();
        $onusOffline = $onus->filter->estadoEsOffline()->count();

        return view('olts.pon-onus', compact('olt', 'ponPort', 'ponPuerto', 'onus', 'onusOnline', 'onusOffline'));
    }

    public function edit(Olt $olt)
    {
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.edit', compact('olt', 'nodos'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate(array_merge([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'marca' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puerto' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ], $this->reglasGestion()));

        if (empty($validated['codigo'])) {
            unset($validated['codigo']);
        }

        $validated = $this->normalizarGestion($validated, $olt);

        $olt->update($validated);

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT actualizado correctamente.');
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('sistema.olts.index')->with('success', 'OLT eliminado correctamente.');
    }

    public function testGestion(Olt $olt, OltOnuSyncService $syncService)
    {
        $result = $syncService->probarConexion($olt);

        if (request()->expectsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        return redirect()
            ->route('sistema.olts.show', $olt)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function importOnus(Olt $olt, OltOnuSyncService $syncService)
    {
        try {
            $result = $syncService->importarDesdeOlt($olt);
        } catch (Throwable $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('sistema.olts.show', $olt)
                ->with('error', $e->getMessage());
        }

        if (request()->expectsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('sistema.olts.show', $olt)
            ->with('success', $result['message']);
    }

    public function refreshOnuDetalles(Olt $olt, OltOnuSyncService $syncService)
    {
        try {
            @set_time_limit(600);
            $result = $syncService->refrescarDetalleTodasLasOnus($olt);
        } catch (Throwable $e) {
            return redirect()
                ->route('sistema.olts.show', $olt)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sistema.olts.show', $olt)
            ->with('success', $result['message']);
    }

    public function refreshOnuDetallesPon(Olt $olt, int $ponPort, OltOnuSyncService $syncService)
    {
        try {
            $result = $syncService->refrescarDetalleOnusPorPon($olt, $ponPort);
        } catch (Throwable $e) {
            return redirect()
                ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('sistema.olts.pon-onus', ['olt' => $olt, 'ponPort' => $ponPort])
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }
}
