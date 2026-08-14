<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\RouterScheduler;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Throwable;

class RouterSchedulerController extends Controller
{
    public function index(Request $request)
    {
        $query = RouterScheduler::with('routerOrigen')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('notas', 'like', "%{$q}%")
                    ->orWhere('on_event', 'like', "%{$q}%")
                    ->orWhere('comment', 'like', "%{$q}%");
            });
        }

        if ($request->filled('router_origen_id') && $request->router_origen_id !== 'todos') {
            $query->where('router_origen_id', $request->router_origen_id);
        }

        $schedulers = $query->paginate(20)->withQueryString();
        $routers = Router::orderBy('nombre')->get(['router_id', 'nombre', 'ip']);

        return view('sistema.router-schedulers.index', compact('schedulers', 'routers'));
    }

    public function edit(RouterScheduler $router_scheduler)
    {
        $scheduler = $router_scheduler->load('routerOrigen');

        return view('sistema.router-schedulers.edit', compact('scheduler'));
    }

    public function update(Request $request, RouterScheduler $router_scheduler)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:128', 'unique:router_schedulers,nombre,'.$router_scheduler->router_scheduler_id.',router_scheduler_id'],
            'on_event' => ['nullable', 'string'],
            'start_date' => ['nullable', 'string', 'max:32'],
            'start_time' => ['nullable', 'string', 'max:32'],
            'interval' => ['nullable', 'string', 'max:64'],
            'owner' => ['nullable', 'string', 'max:64'],
            'policy' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:255'],
            'disabled' => ['nullable', 'boolean'],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['disabled'] = $request->boolean('disabled');
        $router_scheduler->update($validated);

        return redirect()
            ->route('sistema.router-schedulers.index')
            ->with('success', 'Scheduler actualizado en la base de datos.');
    }

    public function destroy(RouterScheduler $router_scheduler)
    {
        $nombre = $router_scheduler->nombre;
        $router_scheduler->delete();

        return redirect()
            ->route('sistema.router-schedulers.index')
            ->with('success', "Scheduler «{$nombre}» eliminado de la base de datos (no se borró del router).");
    }

    public function listRemote(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $router = Router::where('router_id', $validated['router_id'])->firstOrFail();

        try {
            $remotos = $mikrotik->getSystemSchedulers($router);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'schedulers' => [],
            ], 422);
        }

        $schedulers = collect($remotos)
            ->map(function (array $s) {
                $onEvent = (string) ($s['on-event'] ?? '');

                return [
                    'name' => (string) ($s['name'] ?? ''),
                    'interval' => (string) ($s['interval'] ?? ''),
                    'start_time' => (string) ($s['start-time'] ?? ''),
                    'start_date' => (string) ($s['start-date'] ?? ''),
                    'disabled' => (string) ($s['disabled'] ?? 'no'),
                    'owner' => (string) ($s['owner'] ?? ''),
                    'policy' => (string) ($s['policy'] ?? ''),
                    'comment' => (string) ($s['comment'] ?? ''),
                    'on_event_preview' => mb_substr(preg_replace('/\s+/', ' ', $onEvent) ?? '', 0, 120),
                ];
            })
            ->filter(fn ($s) => $s['name'] !== '')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'router' => [
                'id' => $router->router_id,
                'nombre' => $router->nombre,
                'ip' => $router->ip,
            ],
            'schedulers' => $schedulers,
        ]);
    }

    public function importFromRouter(Request $request, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
            'nombres' => ['nullable', 'array'],
            'nombres.*' => ['string', 'max:128'],
        ]);

        $router = Router::where('router_id', $validated['router_id'])->firstOrFail();
        $nombres = $validated['nombres'] ?? null;
        if (is_array($nombres) && $nombres === []) {
            $nombres = null;
        }

        $result = $mikrotik->importSystemSchedulersToDatabase($router, $nombres);

        return response()->json([
            'success' => $result['success'],
            'message' => sprintf(
                'Importados: %d nuevos, %d actualizados.',
                $result['imported'],
                $result['updated']
            ),
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'errors' => $result['errors'],
        ], ($result['success'] || ($result['imported'] + $result['updated']) > 0) ? 200 : 422);
    }

    public function syncToRouter(Request $request, RouterScheduler $router_scheduler, MikroTikService $mikrotik)
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $destino = Router::where('router_id', $validated['router_id'])->firstOrFail();

        try {
            $result = $mikrotik->syncSchedulerToRouter($router_scheduler, $destino);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'No se pudo sincronizar el scheduler.',
            ], 422);
        }

        $accion = ($result['action'] ?? '') === 'added' ? 'creado' : 'actualizado';

        return response()->json([
            'success' => true,
            'message' => "Scheduler «{$router_scheduler->nombre}» {$accion} en {$destino->nombre} ({$destino->ip}).",
            'action' => $result['action'] ?? null,
        ]);
    }
}
