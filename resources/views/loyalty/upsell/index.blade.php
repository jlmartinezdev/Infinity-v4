@extends('layouts.app')
@section('title', 'Planes upsell')
@section('content')
<div class="max-w-7xl mx-auto space-y-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Planes upsell (App)</h1>
    @if(session('success'))<div class="rounded-lg bg-green-50 text-green-800 px-4 py-3">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border p-5">
            <h2 class="font-semibold mb-3">Publicar plan</h2>
            <form method="POST" action="{{ route('loyalty.upsell.store') }}" class="space-y-3">
                @csrf
                <select name="plan_id" required class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                    <option value="">Seleccionar plan…</option>
                    @foreach($planes as $plan)
                        <option value="{{ $plan->plan_id }}">{{ $plan->nombre }} — {{ $plan->velocidad }} — Gs. {{ number_format($plan->precio, 0, ',', '.') }}</option>
                    @endforeach
                </select>
                <textarea name="beneficios" rows="3" placeholder="Beneficios (texto libre)" class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700"></textarea>
                <input type="number" name="orden" value="0" min="0" class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700" placeholder="Orden">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" checked> Activo</label>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="es_superior" value="1"> Marcar como superior (marketing)</label>
                <button class="px-4 py-2 bg-purple-600 text-white rounded-lg">Publicar</button>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border p-5">
            <h2 class="font-semibold mb-3">Staff a avisar (downgrade)</h2>
            <form method="POST" action="{{ route('loyalty.upsell.staff') }}">
                @csrf
                <div class="max-h-64 overflow-y-auto space-y-2 mb-3">
                    @foreach($staff as $u)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="staff_ids[]" value="{{ $u->usuario_id }}" @checked(in_array($u->usuario_id, $staffSeleccionados, true))>
                            {{ $u->name }} <span class="text-gray-500">({{ $u->email }})</span>
                        </label>
                    @endforeach
                </div>
                <button class="px-4 py-2 bg-purple-600 text-white rounded-lg">Guardar staff</button>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border overflow-x-auto">
        <table class="min-w-full divide-y">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr class="text-xs uppercase text-gray-500">
                    <th class="px-3 py-3 text-left">Plan</th>
                    <th class="px-3 py-3 text-left">Velocidad</th>
                    <th class="px-3 py-3 text-left">Precio</th>
                    <th class="px-3 py-3 text-left">Superior</th>
                    <th class="px-3 py-3 text-left">Activo</th>
                    <th class="px-3 py-3 text-left">Orden</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($items as $item)
                    <tr>
                        <td class="px-3 py-3 text-sm">
                            {{ $item->plan?->nombre }}
                            <div class="text-xs text-gray-500 whitespace-pre-line">{{ $item->beneficios }}</div>
                        </td>
                        <td class="px-3 py-3 text-sm">{{ $item->plan?->velocidad }}</td>
                        <td class="px-3 py-3 text-sm">{{ number_format((float) $item->plan?->precio, 0, ',', '.') }}</td>
                        <td class="px-3 py-3 text-sm">{{ $item->es_superior ? 'Sí' : 'No' }}</td>
                        <td class="px-3 py-3 text-sm">{{ $item->activo ? 'Sí' : 'No' }}</td>
                        <td class="px-3 py-3 text-sm">{{ $item->orden }}</td>
                        <td class="px-3 py-3 text-right">
                            <form method="POST" action="{{ route('loyalty.upsell.destroy', $item) }}" onsubmit="return confirm('¿Quitar del catálogo?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 text-sm">Quitar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Ningún plan publicado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
