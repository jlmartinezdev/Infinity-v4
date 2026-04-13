@extends('layouts.app')

@section('title', 'Acciones del cliente')

@section('content')
@php
    $estadosServicio = \App\Models\Servicio::estadosDisponibles();
    $u = auth()->user();
@endphp
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('clientes.index') }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">&larr; Volver a clientes</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Acciones del cliente</h1>
        <p class="text-gray-700 dark:text-gray-300 font-medium mt-1">{{ $cliente->nombre }} {{ $cliente->apellido }}</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">Cédula: {{ $cliente->cedula ?: '—' }} · #{{ $cliente->cliente_id }}</p>
    </div>

    {{-- Datos rápidos + enlaces principales --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8">
        @if($u?->tienePermiso('clientes.ver'))
            <a href="{{ route('clientes.detalle', $cliente) }}" class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-blue-400 dark:hover:border-blue-500 transition-colors">
                <span class="shrink-0 p-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-gray-100">Ver detalle general</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Pagos, tickets, historial resumido</span>
                </span>
            </a>
        @endif
        @if($u?->tienePermiso('clientes.editar'))
            <a href="{{ route('clientes.edit', $cliente) }}" class="flex items-start gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-purple-400 dark:hover:border-purple-500 transition-colors">
                <span class="shrink-0 p-2 rounded-lg bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <span>
                    <span class="block font-semibold text-gray-900 dark:text-gray-100">Editar datos del cliente</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Nombre, dirección, teléfono, etc.</span>
                </span>
            </a>
        @endif
    </div>

    {{-- Servicios --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Servicios</h2>
            @if($u?->tienePermiso('servicios.crear'))
                <a href="{{ route('servicios.create', ['cliente_id' => $cliente->cliente_id]) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar servicio
                </a>
            @endif
        </div>
        @if($cliente->servicios->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay servicios. Use &quot;Agregar servicio&quot; para crear uno.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($cliente->servicios as $s)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $s->servicio_id }}</td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $s->plan?->nombre ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $s->ip ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $estadosServicio[$s->estado] ?? $s->estado }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    @if($u?->tienePermiso('servicios.crear'))
                                        <a href="{{ route('servicios.edit', $s) }}" class="text-purple-600 dark:text-purple-400 hover:underline mr-3">Editar</a>
                                    @endif
                                    @if($u?->tienePermiso('facturas.crear'))
                                        <a href="{{ route('facturas.crear-interna-servicio', $s) }}" class="text-amber-700 dark:text-amber-400 hover:underline">Factura interna</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($u?->tienePermiso('servicios.ver'))
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                <a href="{{ route('servicios.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline">Abrir listado completo de servicios</a> (todos los clientes).
            </p>
        @endif
    </div>

    {{-- Facturación y cobros --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Facturación y cobros</h2>
        <ul class="space-y-2 text-sm">
            @if($u?->tienePermiso('facturas.crear'))
                <li>
                    <a href="{{ route('facturas.generar-interna', ['cliente_id' => $cliente->cliente_id]) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Generar factura interna</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Mensual desde servicios activos (elige período).</span>
                </li>
            @endif
            @if($u?->tienePermiso('factura-interna.ver'))
                <li>
                    <a href="{{ route('factura-internas.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Facturas internas</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Listado y detalle de facturas.</span>
                </li>
            @endif
            @if($u?->tienePermiso('pagos-pendientes.ver'))
                <li>
                    <a href="{{ route('factura-internas.pendientes', ['buscar' => $cliente->cedula ?: trim($cliente->nombre.' '.$cliente->apellido)]) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Pendientes de pago</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Búsqueda por cédula o nombre.</span>
                </li>
            @endif
            @if($u?->tienePermiso('cobros.crear'))
                <li>
                    <a href="{{ route('cobros.create', ['cliente_id' => $cliente->cliente_id]) }}" class="text-green-600 dark:text-green-400 hover:underline font-medium">Registrar cobro</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Recibo / pago aplicado a facturas.</span>
                </li>
            @endif
        </ul>
    </div>

    {{-- Tickets y pedidos --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Soporte y pedidos</h2>
        <ul class="space-y-2 text-sm">
            @if($u?->tienePermiso('tickets.crear'))
                <li>
                    <a href="{{ route('tickets.create', ['cliente_id' => $cliente->cliente_id]) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Nuevo ticket</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Cliente preseleccionado.</span>
                </li>
            @endif
            @if($u?->tienePermiso('tickets.ver'))
                <li>
                    <a href="{{ route('tickets.index', ['cliente_id' => $cliente->cliente_id]) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Tickets de este cliente</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Listado filtrado.</span>
                </li>
            @endif
            @if($u?->tienePermiso('pedidos.ver'))
                <li>
                    <a href="{{ route('pedidos.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Pedidos</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Gestionar instalaciones (filtrar por cliente en la lista).</span>
                </li>
            @endif
            @if($u?->tienePermiso('pedidos.crear'))
                <li>
                    <a href="{{ route('pedidos.create') }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Nuevo pedido</a>
                    <span class="text-gray-500 dark:text-gray-400"> — Alta de pedido / instalación.</span>
                </li>
            @endif
            @if($u?->tienePermiso('pagos-pendientes.ver'))
                <li>
                    <a href="{{ route('promesas-pago.index') }}" class="text-amber-700 dark:text-amber-400 hover:underline font-medium">Promesas de pago</a>
                </li>
            @endif
        </ul>
    </div>

    @if($u?->tienePermiso('clientes.eliminar'))
        <div class="border border-red-200 dark:border-red-900/50 rounded-xl p-4 bg-red-50/50 dark:bg-red-900/10">
            <p class="text-sm text-red-800 dark:text-red-200 mb-2">Zona destructiva</p>
            <form action="{{ route('clientes.destroy', $cliente) }}" method="POST" onsubmit="return confirm('¿Eliminar definitivamente este cliente?');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline font-medium">Eliminar cliente</button>
            </form>
        </div>
    @endif
</div>
@endsection
