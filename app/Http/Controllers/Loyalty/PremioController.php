<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Premio;
use App\Support\LoyaltyImageUploader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PremioController extends Controller
{
    public function index(Request $request)
    {
        $query = Premio::query()->orderBy('orden')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qry) use ($q) {
                $qry->where('nombre', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        $premios = $query->paginate(20)->withQueryString();

        return view('loyalty.premios.index', [
            'premios' => $premios,
            'tipos' => Premio::tipos(),
            'etiquetas' => Premio::etiquetas(),
        ]);
    }

    public function create()
    {
        return view('loyalty.premios.create', [
            'tipos' => Premio::tipos(),
            'etiquetas' => Premio::etiquetas(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data = $this->normalizar($request, $data);
        $data['imagen'] = LoyaltyImageUploader::guardar($request, 'premios');

        $premio = Premio::create($data);
        $this->asegurarUnicoDestacado($premio);

        return redirect()->route('loyalty.premios.index')->with('success', 'Premio creado.');
    }

    public function edit(Premio $premio)
    {
        return view('loyalty.premios.edit', [
            'premio' => $premio,
            'tipos' => Premio::tipos(),
            'etiquetas' => Premio::etiquetas(),
        ]);
    }

    public function update(Request $request, Premio $premio)
    {
        $data = $this->validar($request);
        $data = $this->normalizar($request, $data);
        $data['imagen'] = LoyaltyImageUploader::guardar($request, 'premios', $premio->imagen);

        $premio->update($data);
        $this->asegurarUnicoDestacado($premio->fresh());

        return redirect()->route('loyalty.premios.index')->with('success', 'Premio actualizado.');
    }

    public function toggle(Premio $premio)
    {
        $premio->activo = ! $premio->activo;
        $premio->save();

        return redirect()->route('loyalty.premios.index')->with('success', $premio->activo
            ? 'Premio habilitado: '.$premio->nombre
            : 'Premio deshabilitado: '.$premio->nombre);
    }

    public function destroy(Premio $premio)
    {
        if ($premio->canjes()->exists()) {
            return redirect()->route('loyalty.premios.index')
                ->with('error', 'No se puede eliminar: hay canjes asociados. Desactivá el premio.');
        }

        $premio->eliminarImagen();
        $premio->delete();

        return redirect()->route('loyalty.premios.index')->with('success', 'Premio eliminado.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => ['required', Rule::in(array_keys(Premio::tipos()))],
            'puntos_requeridos' => ['required', 'integer', 'min:1'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'descuento_monto' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'stock_ilimitado' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
            'destacado' => ['nullable', 'boolean'],
            'etiqueta' => ['nullable', Rule::in(Premio::ETIQUETAS)],
            'tier' => ['nullable', 'integer', 'min:1', 'max:5'],
            'requiere_aprobacion' => ['nullable', 'boolean'],
            'tope_anual_por_cliente' => ['nullable', 'integer', 'min:1'],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'eliminar_imagen' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(Request $request, array $data): array
    {
        $data['activo'] = $request->boolean('activo', true);
        $data['destacado'] = $request->boolean('destacado', false);
        $data['orden'] = (int) ($data['orden'] ?? 0);
        $data['puntos_requeridos'] = (int) $data['puntos_requeridos'];
        $data['tipo'] = $data['tipo'] ?? Premio::TIPO_FISICO;
        $data['etiqueta'] = filled($data['etiqueta'] ?? null) ? $data['etiqueta'] : null;
        $data['tier'] = filled($data['tier'] ?? null) ? (int) $data['tier'] : null;
        $data['tope_anual_por_cliente'] = filled($data['tope_anual_por_cliente'] ?? null)
            ? (int) $data['tope_anual_por_cliente']
            : null;

        $data['requiere_aprobacion'] = $request->boolean('requiere_aprobacion')
            || $data['tipo'] === Premio::TIPO_APROBACION;

        // stock_ilimitado o vacío → null (sin límite); 0 = agotado (oculto en app).
        if ($request->boolean('stock_ilimitado') || ! $request->filled('stock')) {
            $data['stock'] = null;
        } else {
            $data['stock'] = (int) $request->input('stock');
        }

        if ($data['tipo'] === Premio::TIPO_DESCUENTO) {
            $pct = isset($data['descuento_porcentaje']) && $data['descuento_porcentaje'] !== ''
                ? (float) $data['descuento_porcentaje']
                : null;
            $monto = isset($data['descuento_monto']) && $data['descuento_monto'] !== ''
                ? (float) $data['descuento_monto']
                : null;
            $data['descuento_porcentaje'] = ($pct !== null && $pct > 0) ? $pct : null;
            $data['descuento_monto'] = ($monto !== null && $monto > 0) ? $monto : null;

            if ($data['descuento_porcentaje'] === null && $data['descuento_monto'] === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'descuento_porcentaje' => 'Indicá porcentaje o monto de descuento.',
                ]);
            }
        } else {
            $data['descuento_porcentaje'] = null;
            $data['descuento_monto'] = null;
        }

        unset($data['stock_ilimitado'], $data['eliminar_imagen']);

        return $data;
    }

    /**
     * Ideal: un solo premio destacado a la vez.
     * Si este queda marcado, se quita el flag del resto.
     */
    private function asegurarUnicoDestacado(?Premio $premio): void
    {
        if (! $premio || ! $premio->destacado) {
            return;
        }

        Premio::query()
            ->where('id', '!=', $premio->id)
            ->where('destacado', true)
            ->update(['destacado' => false]);
    }
}
