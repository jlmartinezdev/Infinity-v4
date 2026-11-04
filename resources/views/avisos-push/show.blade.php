@extends('layouts.app')

@section('title', 'Entregas del aviso #'.$aviso->id)

@section('content')
@php
    $chips = [
        '' => ['label' => 'Todos', 'n' => $conteos['enviado'] + $conteos['fallido'] + $conteos['omitido'], 'on' => 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'],
        'enviado' => ['label' => 'Entregados', 'n' => $conteos['enviado'], 'on' => 'bg-emerald-600 text-white'],
        'fallido' => ['label' => 'No entregados', 'n' => $conteos['fallido'], 'on' => 'bg-rose-600 text-white'],
        'omitido' => ['label' => 'Sin app', 'n' => $conteos['omitido'], 'on' => 'bg-amber-500 text-white'],
    ];
@endphp
<div class="max-w-4xl mx-auto">
    <a href="{{ route('avisos-push.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-300">← Avisos push</a>

    <div class="mt-3 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Aviso #{{ $aviso->id }}</h1>
        <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $aviso->titulo }}</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 whitespace-pre-line">{{ $aviso->cuerpo }}</p>
        <p class="mt-2 text-xs uppercase tracking-wide text-gray-400">
            {{ $tipos[$aviso->tipo] ?? $aviso->tipo }}
            · {{ $aviso->etiquetaDestino() }}
            · {{ $aviso->created_at?->format('d/m/Y H:i') }}
            @if($aviso->creador)
                · {{ $aviso->creador->name }}
            @endif
        </p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <a href="{{ route('avisos-push.show', ['aviso' => $aviso, 'estado' => 'enviado']) }}"
           class="rounded-2xl border px-4 py-3 {{ $estado === 'enviado' ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
            <div class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Entregados</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $conteos['enviado'] }}</div>
        </a>
        <a href="{{ route('avisos-push.show', ['aviso' => $aviso, 'estado' => 'fallido']) }}"
           class="rounded-2xl border px-4 py-3 {{ $estado === 'fallido' ? 'border-rose-400 bg-rose-50 dark:border-rose-700 dark:bg-rose-950/40' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
            <div class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">No entregados</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $conteos['fallido'] }}</div>
        </a>
        <a href="{{ route('avisos-push.show', ['aviso' => $aviso, 'estado' => 'omitido']) }}"
           class="rounded-2xl border px-4 py-3 {{ $estado === 'omitido' ? 'border-amber-400 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
            <div class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Sin app</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $conteos['omitido'] }}</div>
        </a>
    </div>

    @if(! $tieneDetalle)
        <div class="rounded-2xl border border-gray-200 bg-white px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            Este aviso se envió antes de guardar el detalle por cliente. Quedó el total:
            {{ $aviso->enviados }} entregados
            @if($aviso->fallidos)
                · {{ $aviso->fallidos }} no entregados
            @endif
            @if($aviso->omitidos)
                · {{ $aviso->omitidos }} sin app
            @endif
            , de {{ $aviso->total_destinatarios }}.
            Los próximos avisos van a listar a cada persona.
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700/80 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach($chips as $valor => $chip)
                        <a href="{{ route('avisos-push.show', array_filter(['aviso' => $aviso, 'estado' => $valor, 'q' => $q], fn ($v) => $v !== null && $v !== '')) }}"
                           class="rounded-full px-3 py-1 text-xs font-medium ring-1 ring-gray-200 dark:ring-gray-600 {{ $estado === $valor ? $chip['on'] : 'bg-gray-50 text-gray-600 dark:bg-gray-900 dark:text-gray-300' }}">
                            {{ $chip['label'] }} ({{ $chip['n'] }})
                        </a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('avisos-push.show', $aviso) }}" class="flex gap-2">
                    @if($estado !== '')
                        <input type="hidden" name="estado" value="{{ $estado }}">
                    @endif
                    <input type="search" name="q" value="{{ $q }}" placeholder="Nombre, CI o ID"
                           class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600 sm:w-52">
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                        @forelse ($lista as $entrega)
                            @php
                                $cliente = $entrega->cliente;
                                $nombre = trim(((string) ($cliente->nombre ?? '')).' '.((string) ($cliente->apellido ?? '')));
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    @if($puedeVerCliente && $cliente)
                                        <a href="{{ route('clientes.detalle', $cliente) }}" class="text-sm font-medium text-gray-900 hover:text-blue-700 dark:text-gray-100 dark:hover:text-blue-300">
                                            {{ $nombre !== '' ? $nombre : 'Cliente #'.$entrega->cliente_id }}
                                        </a>
                                    @else
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $nombre !== '' ? $nombre : 'Cliente #'.$entrega->cliente_id }}</div>
                                    @endif
                                    <div class="text-xs text-gray-400">
                                        #{{ $entrega->cliente_id }}
                                        @if($cliente?->cedula)
                                            · {{ $cliente->cedula }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($entrega->estado === 'enviado')
                                        <span class="text-emerald-700 dark:text-emerald-300">Entregado</span>
                                    @elseif($entrega->estado === 'fallido')
                                        <span class="text-rose-600 dark:text-rose-300">No entregado</span>
                                    @else
                                        <span class="text-amber-700 dark:text-amber-300">Sin app</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $entrega->motivoVisible() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No hay clientes en este filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($lista->hasPages())
                <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-700/80">
                    {{ $lista->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
