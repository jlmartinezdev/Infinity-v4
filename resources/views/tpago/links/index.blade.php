@extends('layouts.app')

@section('title', 'Links TPago')

@section('content')
@php
    $badge = [
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    ];
@endphp
<div class="max-w-7xl mx-auto pb-10">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Links de pago TPago</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Links generados desde la app o el panel, con estado y cobro asociado.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <p class="text-xs text-gray-500 uppercase">Total</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <p class="text-xs text-amber-600 uppercase">Pendientes</p>
            <p class="text-2xl font-semibold text-amber-700 dark:text-amber-300">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <p class="text-xs text-green-600 uppercase">Confirmados</p>
            <p class="text-2xl font-semibold text-green-700 dark:text-green-300">{{ number_format($stats['confirmed']) }}</p>
        </div>
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <p class="text-xs text-red-600 uppercase">Error / rechazado</p>
            <p class="text-2xl font-semibold text-red-700 dark:text-red-300">{{ number_format($stats['error']) }}</p>
        </div>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end bg-white dark:bg-gray-800 p-4 rounded-xl border dark:border-gray-700">
        <div>
            <label class="block text-xs mb-1 text-gray-600 dark:text-gray-300">Estado</label>
            <select name="estado" class="px-3 py-2 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 text-sm">
                <option value="todos">Todos</option>
                @foreach($estados as $k => $label)
                    <option value="{{ $k }}" @selected(request('estado') === $k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[220px] flex-1">
            <label class="block text-xs mb-1 text-gray-600 dark:text-gray-300">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Cliente, CI, alias, factura, ticket…"
                   class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 text-sm">
        </div>
        <div>
            <label class="block text-xs mb-1 text-gray-600 dark:text-gray-300">Factura ID</label>
            <input type="number" name="factura_interna_id" value="{{ request('factura_interna_id') }}"
                   class="w-28 px-3 py-2 rounded-lg border dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 text-sm">
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 pb-2">
            <input type="checkbox" name="hoy" value="1" @checked(request('hoy')) class="rounded border-gray-300"> Solo hoy
        </label>
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">Filtrar</button>
        @if(request()->hasAny(['estado','q','factura_interna_id','hoy','cliente_id']))
            <a href="{{ route('tpago.links.index') }}" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 underline">Limpiar</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700 overflow-x-auto">
        <table class="min-w-full divide-y dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">#</th>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Cliente</th>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Factura</th>
                    <th class="px-3 py-3 text-right text-xs uppercase text-gray-500">Monto</th>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Estado</th>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Alias / ticket</th>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Creado</th>
                    <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @forelse($links as $link)
                    @php $color = $badge[$link->colorEstado()] ?? $badge['gray']; @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="px-3 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $link->id }}</td>
                        <td class="px-3 py-3 text-sm">
                            <div class="text-gray-900 dark:text-gray-100">{{ $link->cliente?->nombre }} {{ $link->cliente?->apellido }}</div>
                            <div class="text-xs text-gray-500">{{ $link->cliente?->cedula }} · #{{ $link->cliente_id }}</div>
                        </td>
                        <td class="px-3 py-3 text-sm">
                            @if($link->factura_interna_id)
                                <a href="{{ route('factura-internas.show', $link->factura_interna_id) }}"
                                   class="text-indigo-600 dark:text-indigo-400 hover:underline">#{{ $link->factura_interna_id }}</a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                            {{ number_format($link->amount, 0, ',', '.') }}
                        </td>
                        <td class="px-3 py-3 text-sm">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                {{ $link->etiquetaEstado() }}
                            </span>
                            @if($link->paid_at)
                                <div class="text-xs text-gray-500 mt-1">{{ $link->paid_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm">
                            <div class="font-mono text-xs text-gray-800 dark:text-gray-200">{{ $link->link_alias ?: '—' }}</div>
                            @if($link->ticket_number)
                                <div class="text-xs text-gray-500">Ticket {{ $link->ticket_number }}</div>
                            @endif
                            @if($link->cobro)
                                <a href="{{ route('cobros.show', $link->cobro) }}" class="text-xs text-green-600 hover:underline">
                                    Recibo {{ $link->cobro->numero_recibo }}
                                </a>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ $link->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            @if($link->expires_at)
                                <div class="text-xs text-gray-400">Vence {{ $link->expires_at->timezone(config('app.timezone'))->format('d/m H:i') }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('tpago.links.show', $link) }}"
                                   class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded text-xs hover:bg-gray-200 dark:hover:bg-gray-600">Ver</a>
                                @if($link->link_url)
                                    <a href="{{ $link->link_url }}" target="_blank" rel="noopener"
                                       class="px-2 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">Abrir</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">No hay links TPago con esos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $links->links() }}</div>
    </div>
</div>
@endsection
