<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\OltModelo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OltModeloController extends Controller
{
    /** @var array<int, string> */
    private const MARCAS = ['VSOL', 'Huawei', 'ZTE', 'Fiberhome', 'Nokia', 'Calix', 'Otro'];

    public function index(Request $request)
    {
        $query = OltModelo::query()
            ->withCount('olts')
            ->orderBy('orden')
            ->orderBy('marca')
            ->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('marca', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        if ($request->filled('marca') && $request->marca !== 'todas') {
            $query->where('marca', $request->marca);
        }

        if ($request->filled('activo') && $request->activo !== 'todos') {
            $query->where('activo', $request->activo === '1');
        }

        $modelos = $query->paginate(24)->withQueryString();
        $marcas = OltModelo::query()->distinct()->orderBy('marca')->pluck('marca');

        return view('sistema.olt-modelos.index', compact('modelos', 'marcas'));
    }

    public function create()
    {
        return view('sistema.olt-modelos.create', ['marcas' => self::MARCAS]);
    }

    public function store(Request $request)
    {
        $validated = $this->validar($request);
        $validated['slug'] = $this->resolverSlug($validated['slug'] ?? null, $validated['nombre']);
        $validated['activo'] = $request->boolean('activo', true);
        $validated['orden'] = (int) ($validated['orden'] ?? 0);
        $validated['imagen'] = $this->guardarImagen($request) ?? 'images/olts/olt-generic.svg';

        OltModelo::create($validated);

        return redirect()->route('sistema.olt-modelos.index')->with('success', 'Modelo agregado al catálogo.');
    }

    public function edit(OltModelo $oltModelo)
    {
        return view('sistema.olt-modelos.edit', [
            'oltModelo' => $oltModelo,
            'marcas' => self::MARCAS,
        ]);
    }

    public function update(Request $request, OltModelo $oltModelo)
    {
        $validated = $this->validar($request, $oltModelo);
        $slugNuevo = $this->resolverSlug($validated['slug'] ?? $oltModelo->slug, $validated['nombre'], $oltModelo->olt_modelo_id);
        $slugAnterior = $oltModelo->slug;

        $validated['slug'] = $slugNuevo;
        $validated['activo'] = $request->boolean('activo', true);
        $validated['orden'] = (int) ($validated['orden'] ?? 0);
        $validated['imagen'] = $this->guardarImagen($request, $oltModelo);

        if ($slugNuevo !== $slugAnterior) {
            Olt::where('modelo', $slugAnterior)->update(['modelo' => $slugNuevo]);
        }

        $oltModelo->update($validated);

        return redirect()->route('sistema.olt-modelos.index')->with('success', 'Modelo actualizado.');
    }

    public function destroy(OltModelo $oltModelo)
    {
        if ($oltModelo->olts()->exists()) {
            return redirect()
                ->route('sistema.olt-modelos.index')
                ->with('error', 'No se puede eliminar: hay OLTs usando este modelo.');
        }

        $oltModelo->eliminarImagenSubida();
        $oltModelo->delete();

        return redirect()->route('sistema.olt-modelos.index')->with('success', 'Modelo eliminado del catálogo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?OltModelo $oltModelo = null): array
    {
        return $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('olt_modelos', 'slug')->ignore($oltModelo?->olt_modelo_id, 'olt_modelo_id'),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'marca' => ['required', 'string', 'max:64'],
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
        $base = Str::limit($base, 50, '');

        $candidate = $base;
        $i = 2;
        while (OltModelo::query()
            ->where('slug', $candidate)
            ->when($exceptoId, fn ($q) => $q->where('olt_modelo_id', '!=', $exceptoId))
            ->exists()) {
            $candidate = Str::limit($base.'-'.$i, 50, '');
            $i++;
        }

        return $candidate;
    }

    private function guardarImagen(Request $request, ?OltModelo $oltModelo = null): ?string
    {
        if ($oltModelo && $request->boolean('eliminar_imagen')) {
            $oltModelo->eliminarImagenSubida();

            return 'images/olts/olt-generic.svg';
        }

        if ($request->hasFile('imagen')) {
            $oltModelo?->eliminarImagenSubida();

            return $request->file('imagen')->store('olt-modelos', 'public');
        }

        return $oltModelo?->imagen;
    }
}
