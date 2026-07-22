<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\RouterModelo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RouterModeloController extends Controller
{
    /** @var array<int, string> */
    private const SERIES = ['RB', 'CCR', 'hAP', 'CHR', 'MikroTik', 'Otro'];

    public function index(Request $request)
    {
        $query = RouterModelo::query()
            ->withCount('routers')
            ->orderBy('orden')
            ->orderBy('serie')
            ->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('serie', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        if ($request->filled('serie') && $request->serie !== 'todas') {
            $query->where('serie', $request->serie);
        }

        if ($request->filled('activo') && $request->activo !== 'todos') {
            $query->where('activo', $request->activo === '1');
        }

        $modelos = $query->paginate(24)->withQueryString();
        $series = RouterModelo::query()->distinct()->orderBy('serie')->pluck('serie');

        return view('sistema.router-modelos.index', compact('modelos', 'series'));
    }

    public function create()
    {
        return view('sistema.router-modelos.create', ['series' => self::SERIES]);
    }

    public function store(Request $request)
    {
        $validated = $this->validar($request);
        $validated['slug'] = $this->resolverSlug($validated['slug'] ?? null, $validated['nombre']);
        $validated['activo'] = $request->boolean('activo', true);
        $validated['orden'] = (int) ($validated['orden'] ?? 0);
        $validated['imagen'] = $this->guardarImagen($request);

        RouterModelo::create($validated);

        return redirect()->route('sistema.router-modelos.index')->with('success', 'Modelo agregado al catálogo.');
    }

    public function edit(RouterModelo $routerModelo)
    {
        return view('sistema.router-modelos.edit', [
            'routerModelo' => $routerModelo,
            'series' => self::SERIES,
        ]);
    }

    public function update(Request $request, RouterModelo $routerModelo)
    {
        $validated = $this->validar($request, $routerModelo);
        $slugNuevo = $this->resolverSlug($validated['slug'] ?? $routerModelo->slug, $validated['nombre'], $routerModelo->router_modelo_id);
        $slugAnterior = $routerModelo->slug;

        $validated['slug'] = $slugNuevo;
        $validated['activo'] = $request->boolean('activo', true);
        $validated['orden'] = (int) ($validated['orden'] ?? 0);
        $validated['imagen'] = $this->guardarImagen($request, $routerModelo);

        if ($slugNuevo !== $slugAnterior) {
            Router::where('modelo', $slugAnterior)->update(['modelo' => $slugNuevo]);
        }

        $routerModelo->update($validated);

        return redirect()->route('sistema.router-modelos.index')->with('success', 'Modelo actualizado.');
    }

    public function destroy(RouterModelo $routerModelo)
    {
        if ($routerModelo->routers()->exists()) {
            return redirect()
                ->route('sistema.router-modelos.index')
                ->with('error', 'No se puede eliminar: hay routers usando este modelo.');
        }

        $routerModelo->eliminarImagenSubida();
        $routerModelo->delete();

        return redirect()->route('sistema.router-modelos.index')->with('success', 'Modelo eliminado del catálogo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?RouterModelo $routerModelo = null): array
    {
        return $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('router_modelos', 'slug')->ignore($routerModelo?->router_modelo_id, 'router_modelo_id'),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'serie' => ['required', 'string', 'max:32'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'eliminar_imagen' => ['nullable', 'boolean'],
        ]);
    }

    private function resolverSlug(?string $slug, string $nombre, ?int $exceptoId = null): string
    {
        $base = Str::slug($slug ?: $nombre);
        if ($base === '') {
            $base = 'modelo';
        }

        $candidate = $base;
        $i = 2;
        while (RouterModelo::query()
            ->where('slug', $candidate)
            ->when($exceptoId, fn ($q) => $q->where('router_modelo_id', '!=', $exceptoId))
            ->exists()) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function guardarImagen(Request $request, ?RouterModelo $routerModelo = null): ?string
    {
        if ($routerModelo && $request->boolean('eliminar_imagen')) {
            $routerModelo->eliminarImagenSubida();

            return null;
        }

        if ($request->hasFile('imagen')) {
            $routerModelo?->eliminarImagenSubida();

            return $request->file('imagen')->store('router-modelos', 'public');
        }

        return $routerModelo?->imagen;
    }
}
