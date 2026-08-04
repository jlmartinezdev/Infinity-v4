<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanUpsell;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanUpsellController extends Controller
{
    public function index()
    {
        $items = PlanUpsell::with('plan')->orderBy('orden')->orderBy('id')->get();
        $planes = Plan::query()->where('estado', 'activo')->orderBy('nombre')->get();
        $staff = User::query()
            ->whereNull('cliente_id')
            ->where('estado', 'activo')
            ->orderBy('name')
            ->get(['usuario_id', 'name', 'email']);
        $staffSeleccionados = DB::table('upsell_staff_aviso')->pluck('usuario_id')->map(fn ($id) => (int) $id)->all();

        return view('loyalty.upsell.index', compact('items', 'planes', 'staff', 'staffSeleccionados'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id', 'unique:planes_upsell,plan_id'],
            'beneficios' => ['nullable', 'string'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
            'es_superior' => ['nullable', 'boolean'],
        ]);

        PlanUpsell::create([
            'plan_id' => $data['plan_id'],
            'beneficios' => $data['beneficios'] ?? null,
            'orden' => (int) ($data['orden'] ?? 0),
            'activo' => $request->boolean('activo', true),
            'es_superior' => $request->boolean('es_superior', false),
        ]);

        return back()->with('success', 'Plan publicado en upsell.');
    }

    public function update(Request $request, PlanUpsell $upsell)
    {
        $data = $request->validate([
            'beneficios' => ['nullable', 'string'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
            'es_superior' => ['nullable', 'boolean'],
            'plan_id' => [
                'nullable',
                'integer',
                'exists:planes,plan_id',
                Rule::unique('planes_upsell', 'plan_id')->ignore($upsell->id),
            ],
        ]);

        $upsell->update([
            'beneficios' => $data['beneficios'] ?? null,
            'orden' => (int) ($data['orden'] ?? 0),
            'activo' => $request->boolean('activo', true),
            'es_superior' => $request->boolean('es_superior', false),
            'plan_id' => $data['plan_id'] ?? $upsell->plan_id,
        ]);

        return back()->with('success', 'Plan upsell actualizado.');
    }

    public function destroy(PlanUpsell $upsell)
    {
        $upsell->delete();

        return back()->with('success', 'Plan quitado del catálogo upsell.');
    }

    public function guardarStaff(Request $request)
    {
        $data = $request->validate([
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer', 'exists:users,usuario_id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['staff_ids'] ?? [])));

        DB::table('upsell_staff_aviso')->delete();
        $now = now();
        foreach ($ids as $id) {
            DB::table('upsell_staff_aviso')->insert([
                'usuario_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return back()->with('success', 'Staff de aviso actualizado ('.count($ids).').');
    }
}
