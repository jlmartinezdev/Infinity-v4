<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Olt;
use App\Models\RouterIpPool;
use App\Services\Olt\OltOnuSyncService;
use App\Support\OltModelosCatalogo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'mac_cmds_address' => ['nullable', 'string', 'max:4000'],
            'mac_cmds_tabla' => ['nullable', 'string', 'max:4000'],
            'mac_cmds_pon' => ['nullable', 'string', 'max:4000'],
            'mac_cmds_interface' => ['nullable', 'string', 'max:4000'],
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

        $macCli = [];
        foreach (['address' => 'mac_cmds_address', 'tabla' => 'mac_cmds_tabla', 'pon' => 'mac_cmds_pon', 'interface' => 'mac_cmds_interface'] as $key => $field) {
            $texto = $validated[$field] ?? '';
            unset($validated[$field]);
            $macCli[$key] = $this->parsearLineasComandoCli(is_string($texto) ? $texto : '');
        }
        $validated['mac_cli_comandos'] = $macCli;

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function parsearLineasComandoCli(string $texto): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $texto) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Evitar inyección de comandos encadenados
            if (preg_match('/[;\n\r]|&&|\|\|/', $line)) {
                continue;
            }
            $out[] = mb_substr($line, 0, 200);
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<int, mixed>
     */
    private function reglaModelo(?string $actual = null): array
    {
        $slugs = OltModelosCatalogo::slugsValidos();
        if ($actual && ! in_array($actual, $slugs, true)) {
            $slugs[] = $actual;
        }

        if ($slugs === []) {
            return ['nullable', 'string', 'max:50'];
        }

        return ['nullable', 'string', 'max:50', Rule::in($slugs)];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function aplicarMarcaDesdeModelo(array $validated): array
    {
        $info = OltModelosCatalogo::find($validated['modelo'] ?? null);
        if ($info && filled($info['marca'] ?? null) && ($info['marca'] ?? '') !== 'Otro') {
            if (empty($validated['marca']) || $validated['marca'] === 'Otro') {
                $validated['marca'] = $info['marca'];
            }
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

        $olts = $query->paginate(24)->withQueryString();
        $nodos = Nodo::orderBy('descripcion')->get();

        return view('olts.index', compact('olts', 'nodos'));
    }

    public function create()
    {
        $nodos = Nodo::orderBy('descripcion')->get();
        $modelosPorMarca = OltModelosCatalogo::porMarca();

        return view('olts.create', compact('nodos', 'modelosPorMarca'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'marca' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'modelo' => $this->reglaModelo(),
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puerto' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
        ], $this->reglasGestion()));

        $validated = $this->normalizarGestion($validated);
        $validated = $this->aplicarMarcaDesdeModelo($validated);

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

    public function show(Olt $olt)
    {
        $olt->load(['nodo', 'oltPuertos', 'salidaPons.oltPuerto', 'pools.router']);
        $onus = $olt->onus()
            ->orderBy('pon_key')
            ->orderBy('onu_index')
            ->get();
        $onusOnline = $onus->filter->estadoEsOnline()->count();
        $onusOffline = $onus->filter->estadoEsOffline()->count();
        $onusDesconocido = $onus->count() - $onusOnline - $onusOffline;
        $onuCountPorPuerto = $olt->onus()->registradas()->get()->groupBy('pon_port')->map->count();
        $onuSyncNotice = null;
        $autoConsultar = $olt->tieneCredencialesGestion() && ! request()->boolean('sin_sync');

        return view('olts.show', compact(
            'olt',
            'onus',
            'onusOnline',
            'onusOffline',
            'onusDesconocido',
            'onuSyncNotice',
            'onuCountPorPuerto',
            'autoConsultar'
        ));
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
        $modelosPorMarca = OltModelosCatalogo::porMarca();
        $pools = RouterIpPool::with('router.nodo')
            ->orderBy('descripcion')
            ->orderBy('ip_range')
            ->get();
        $poolIdsSeleccionados = old('pool_ids', $olt->pools()->pluck('pool_id')->all());

        return view('olts.edit', compact('olt', 'nodos', 'modelosPorMarca', 'pools', 'poolIdsSeleccionados'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate(array_merge([
            'nodo_id' => ['required', 'exists:nodos,nodo_id'],
            'marca' => ['required', 'string', 'max:100'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'modelo' => $this->reglaModelo($olt->modelo),
            'ip' => ['nullable', 'string', 'max:45'],
            'cantidad_puerto' => ['nullable', 'integer', 'min:1', 'max:128'],
            'tipo_pon' => ['required', 'in:GPON,EPON,XG-PON'],
            'estado' => ['nullable', 'string', 'max:20'],
            'notas' => ['nullable', 'string'],
            'pool_ids' => ['nullable', 'array'],
            'pool_ids.*' => ['integer', 'exists:router_ip_pools,pool_id'],
        ], $this->reglasGestion()));

        if (empty($validated['codigo'])) {
            unset($validated['codigo']);
        }

        $validated = $this->normalizarGestion($validated, $olt);
        $validated = $this->aplicarMarcaDesdeModelo($validated);

        $poolIds = collect($validated['pool_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        unset($validated['pool_ids']);

        $olt->update($validated);
        $this->sincronizarPoolsOlt($olt, $poolIds);

        return redirect()->route('sistema.olts.show', $olt)->with('success', 'OLT actualizado correctamente.');
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('sistema.olts.index')->with('success', 'OLT eliminado correctamente.');
    }

    public function testGestion(Olt $olt, OltOnuSyncService $syncService)
    {
        @set_time_limit(120);
        $result = $syncService->probarConexion($olt);

        if (request()->expectsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        return redirect()
            ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function syncVista(Olt $olt, OltOnuSyncService $syncService)
    {
        @set_time_limit(600);

        try {
            $result = $syncService->sincronizarAlVisualizar($olt, forzar: true);
        } catch (Throwable $e) {
            return $this->jsonErrorConsulta($e, ['skipped' => false]);
        }

        if ($result === null) {
            return response()->json([
                'success' => true,
                'skipped' => true,
                'message' => 'Consulta omitida (sin credenciales o intervalo mínimo).',
            ]);
        }

        return response()->json(array_merge($result, ['skipped' => false]));
    }

    public function importOnus(Olt $olt, OltOnuSyncService $syncService)
    {
        @set_time_limit(600);

        try {
            $result = $syncService->importarDesdeOlt($olt);
        } catch (Throwable $e) {
            if (request()->expectsJson()) {
                return $this->jsonErrorConsulta($e);
            }

            return redirect()
                ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
                ->with('error', $e->getMessage());
        }

        if (request()->expectsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
            ->with('success', $result['message']);
    }

    public function refreshOnuDetalles(Olt $olt, OltOnuSyncService $syncService)
    {
        try {
            @set_time_limit(600);
            $result = $syncService->refrescarDetalleTodasLasOnus($olt);
        } catch (Throwable $e) {
            if (request()->expectsJson()) {
                return $this->jsonErrorConsulta($e);
            }

            return redirect()
                ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
                ->with('error', $e->getMessage());
        }

        if (request()->expectsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
            ->with('success', $result['message']);
    }

    public function refreshOnuDetallesPon(Olt $olt, int $ponPort, OltOnuSyncService $syncService)
    {
        try {
            @set_time_limit(600);
            $result = $syncService->refrescarDetalleOnusPorPon($olt, $ponPort);
        } catch (Throwable $e) {
            if (request()->expectsJson()) {
                return $this->jsonErrorConsulta($e);
            }

            return redirect()
                ->route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1])
                ->with('error', $e->getMessage());
        }

        if (request()->expectsJson()) {
            return response()->json(array_merge($result, [
                'redirect' => route('sistema.olts.pon-onus', ['olt' => $olt, 'ponPort' => $ponPort]),
            ]));
        }

        return redirect()
            ->route('sistema.olts.pon-onus', ['olt' => $olt, 'ponPort' => $ponPort])
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function jsonErrorConsulta(Throwable $e, array $extra = [])
    {
        $message = $e->getMessage();
        $preview = $this->previewDesdeMensaje($message);

        return response()->json(array_merge([
            'success' => false,
            'message' => $preview ? $this->mensajeSinDump($message) : $message,
            'preview' => $preview,
        ], $extra), 422);
    }

    private function previewDesdeMensaje(string $message): ?string
    {
        foreach (['Respuesta del equipo:', 'Salida CLI:', 'Vista previa:'] as $marker) {
            $pos = mb_stripos($message, $marker);
            if ($pos !== false) {
                $preview = trim(mb_substr($message, $pos + mb_strlen($marker)));

                return $preview !== '' ? $preview : null;
            }
        }

        return null;
    }

    private function mensajeSinDump(string $message): string
    {
        foreach (['Respuesta del equipo:', 'Salida CLI:', 'Vista previa:'] as $marker) {
            $pos = mb_stripos($message, $marker);
            if ($pos !== false) {
                $corto = trim(mb_substr($message, 0, $pos));

                return $corto !== '' ? $corto : $message;
            }
        }

        return $message;
    }

    /**
     * Asocia los pools elegidos a esta OLT y desasocia los que ya no estén marcados.
     *
     * @param  list<int>  $poolIds
     */
    private function sincronizarPoolsOlt(Olt $olt, array $poolIds): void
    {
        RouterIpPool::query()
            ->where('olt_id', $olt->olt_id)
            ->whereNotIn('pool_id', $poolIds ?: [0])
            ->update(['olt_id' => null]);

        if ($poolIds !== []) {
            RouterIpPool::query()
                ->whereIn('pool_id', $poolIds)
                ->update(['olt_id' => $olt->olt_id]);
        }
    }
}
