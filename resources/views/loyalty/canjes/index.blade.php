@extends('layouts.app')
@section('title', 'Cola de canjes')
@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">Cola de canjes</h1>
    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-3">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 text-red-800 px-4 py-3">{{ session('error') }}</div>@endif

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end bg-white dark:bg-gray-800 p-4 rounded-xl border">
        <div>
            <label class="block text-xs mb-1">Estado</label>
            <select name="estado" class="px-3 py-2 rounded-lg border dark:bg-gray-700">
                <option value="todos">Todos</option>
                @foreach($estados as $k => $label)
                    <option value="{{ $k }}" @selected(request('estado') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs mb-1">Cliente / CI</label>
            <input type="text" name="q" value="{{ request('q') }}" class="px-3 py-2 rounded-lg border dark:bg-gray-700">
        </div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="hoy" value="1" @checked(request('hoy'))> Solo hoy</label>
        <button class="px-4 py-2 bg-purple-600 text-white rounded-lg">Filtrar</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border overflow-x-auto">
        <table class="min-w-full divide-y">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs uppercase">#</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Cliente</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Premio</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Pts</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Modalidad</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Estado</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Fecha</th>
                    <th class="px-3 py-3 text-left text-xs uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($canjes as $c)
                    <tr>
                        <td class="px-3 py-3 text-sm">{{ $c->id }}</td>
                        <td class="px-3 py-3 text-sm">
                            {{ $c->cliente?->nombre }} {{ $c->cliente?->apellido }}
                            <div class="text-xs text-gray-500">{{ $c->cliente?->cedula }}</div>
                        </td>
                        <td class="px-3 py-3 text-sm">{{ $c->premio?->nombre }}</td>
                        <td class="px-3 py-3 text-sm">{{ $c->puntos_usados }}</td>
                        <td class="px-3 py-3 text-sm">{{ \App\Models\Canje::modalidades()[$c->modalidad] ?? $c->modalidad }}</td>
                        <td class="px-3 py-3 text-sm font-medium">{{ $estados[$c->estado] ?? $c->estado }}</td>
                        <td class="px-3 py-3 text-sm">{{ $c->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-3 text-sm">
                            <div class="flex flex-wrap gap-1">
                                @if($c->estaAbierto())
                                    @if($c->estado === 'PENDIENTE')
                                        <form method="POST" action="{{ route('loyalty.canjes.preparar', $c) }}">@csrf<button class="px-2 py-1 bg-blue-600 text-white rounded text-xs">Preparar</button></form>
                                    @endif
                                    @if(in_array($c->estado, ['PENDIENTE','EN_PREPARACION']))
                                        <form method="POST" action="{{ route('loyalty.canjes.listo', $c) }}">@csrf<button class="px-2 py-1 bg-indigo-600 text-white rounded text-xs">Listo</button></form>
                                    @endif
                                    @if($c->modalidad === 'retiro_oficina' && $c->estado !== 'ENTREGADO')
                                        <form method="POST" action="{{ route('loyalty.canjes.entregar', $c) }}">@csrf<button class="px-2 py-1 bg-green-600 text-white rounded text-xs">Entregar</button></form>
                                    @endif
                                    @if($c->modalidad === 'descuento_factura' && $c->estado !== 'APLICADO')
                                        <form method="POST" action="{{ route('loyalty.canjes.aplicar', $c) }}">@csrf<button class="px-2 py-1 bg-green-600 text-white rounded text-xs">Aplicar dto.</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('loyalty.canjes.cancelar', $c) }}" onsubmit="return confirm('¿Cancelar y devolver puntos?')">
                                        @csrf
                                        <button class="px-2 py-1 bg-red-600 text-white rounded text-xs">Cancelar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Sin canjes.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $canjes->links() }}</div>
    </div>
</div>
@endsection
