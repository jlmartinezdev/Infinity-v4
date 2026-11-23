<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Novedad;
use App\Support\LoyaltyImageUploader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NovedadController extends Controller
{
    public function index(Request $request)
    {
        $query = Novedad::query()->orderBy('orden')->orderByDesc('id');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('titulo', 'like', "%{$q}%")
                    ->orWhere('subtitulo', 'like', "%{$q}%");
            });
        }

        $novedades = $query->paginate(20)->withQueryString();

        return view('loyalty.novedades.index', compact('novedades'));
    }

    public function create()
    {
        return view('loyalty.novedades.create');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data = $this->normalizar($request, $data);
        $data['imagen'] = LoyaltyImageUploader::guardar($request, 'novedades');

        Novedad::create($data);

        return redirect()->route('loyalty.novedades.index')->with('success', 'Novedad creada.');
    }

    public function edit(Novedad $novedad)
    {
        return view('loyalty.novedades.edit', compact('novedad'));
    }

    public function update(Request $request, Novedad $novedad)
    {
        $data = $this->validar($request);
        $data = $this->normalizar($request, $data);
        $data['imagen'] = LoyaltyImageUploader::guardar($request, 'novedades', $novedad->imagen);

        $novedad->update($data);

        return redirect()->route('loyalty.novedades.index')->with('success', 'Novedad actualizada.');
    }

    public function destroy(Novedad $novedad)
    {
        $novedad->eliminarImagen();
        $novedad->delete();

        return redirect()->route('loyalty.novedades.index')->with('success', 'Novedad eliminada.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(Request $request, array $data): array
    {
        $data['activa'] = $request->boolean('activa');
        $data['mostrar_al_abrir'] = $request->boolean('mostrar_al_abrir');
        $data['orden'] = (int) ($data['orden'] ?? 0);
        $data['segundos_al_abrir'] = max(1, min(60, (int) $request->input('segundos_al_abrir', 5)));

        return $data;
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'subtitulo' => ['nullable', 'string', 'max:300'],
            'accion_url' => ['nullable', 'string', 'max:500'],
            'tipo' => ['required', Rule::in(Novedad::TIPOS)],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activa' => ['nullable', 'boolean'],
            'mostrar_al_abrir' => ['nullable', 'boolean'],
            'segundos_al_abrir' => ['nullable', 'integer', 'min:1', 'max:60'],
            'vigente_desde' => ['nullable', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'eliminar_imagen' => ['nullable', 'boolean'],
        ]);
    }
}
