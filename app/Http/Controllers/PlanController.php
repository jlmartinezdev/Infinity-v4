<?php

namespace App\Http\Controllers;

use App\Models\PerfilPppoe;
use App\Models\Plan;
use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    /**
     * Listar planes.
     */
    public function index(Request $request)
    {
        $query = Plan::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('velocidad', 'like', "%{$q}%")
                    ->orWhere('descripcion', 'like', "%{$q}%");
            });
        }

        $planes = $query->withCount([
            'servicios as servicios_activos_count' => fn ($q) => $q->where('estado', Servicio::ESTADO_ACTIVO),
        ])->paginate(15)->withQueryString();

        return view('planes.index', compact('planes'));
    }

    /**
     * Formulario crear plan.
     */
    public function create()
    {
        $tecnologias = DB::table('tipos_tecnologias')->orderBy('descripcion')->get();
        $perfilesPppoe = PerfilPppoe::orderBy('nombre')->get();

        return view('planes.create', compact('tecnologias', 'perfilesPppoe'));
    }

    /**
     * Guardar nuevo plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tecnologia_id' => ['required', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'perfil_pppoe_id' => ['nullable', 'integer', 'exists:perfiles_pppoe,perfil_pppoe_id'],
            'nombre' => ['required', 'string', 'max:100'],
            'velocidad' => ['required', 'string', 'max:50'],
            'precio' => ['required', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:activo,inactivo'],
            'prioridad' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $validated['prioridad'] = $validated['prioridad'] ?? 2;
        Plan::create($validated);

        return redirect()->route('planes.index')->with('success', 'Plan creado correctamente.');
    }

    /**
     * Formulario editar plan.
     */
    public function edit($plane)
    {
        $plan = Plan::where('plan_id', $plane)->firstOrFail();
        $tecnologias = DB::table('tipos_tecnologias')->orderBy('descripcion')->get();
        $perfilesPppoe = PerfilPppoe::orderBy('nombre')->get();

        return view('planes.edit', compact('plan', 'tecnologias', 'perfilesPppoe'));
    }

    /**
     * Actualizar plan.
     */
    public function update(Request $request, $plane)
    {
        $plan = Plan::where('plan_id', $plane)->firstOrFail();
        
        $validated = $request->validate([
            'tecnologia_id' => ['required', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'perfil_pppoe_id' => ['nullable', 'integer', 'exists:perfiles_pppoe,perfil_pppoe_id'],
            'nombre' => ['required', 'string', 'max:100'],
            'velocidad' => ['required', 'string', 'max:50'],
            'precio' => ['required', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:activo,inactivo'],
            'prioridad' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $validated['prioridad'] = $validated['prioridad'] ?? 2;
        $plan->update($validated);

        return redirect()->route('planes.index')->with('success', 'Plan actualizado correctamente.');
    }

    /**
     * Eliminar plan.
     */
    public function destroy($plane)
    {
        $plan = Plan::where('plan_id', $plane)->firstOrFail();
        $plan->delete();

        return redirect()->route('planes.index')->with('success', 'Plan eliminado correctamente.');
    }
}
