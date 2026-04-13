@extends('layouts.app')

@section('title', 'Detalle del cliente')

@section('content')
@php
    $formasPago = \App\Models\Cobro::formasPago();
    $estadosTicket = \App\Models\Ticket::estados();
    $estadosServicio = \App\Models\Servicio::estadosDisponibles();
    $mapsUrl = null;
    if ($cliente->url_ubicacion) {
        $raw = trim((string) $cliente->url_ubicacion);
        if ($raw !== '') {
            if (preg_match('/^https?:\/\//i', $raw)) {
                $mapsUrl = $raw;
            } elseif (str_starts_with($raw, '//')) {
                $mapsUrl = 'https:'.$raw;
            } elseif (preg_match('/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/', $raw, $m)) {
                $mapsUrl = 'https://www.google.com/maps?q='.$m[1].','.$m[2];
            } else {
                $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($raw);
            }
        }
    }
@endphp
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('clientes.index') }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline mb-1 inline-block">&larr; Volver a clientes</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $cliente->nombre }} {{ $cliente->apellido }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Cliente #{{ $cliente->cliente_id }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('clientes.acciones', $cliente) }}"
                class="inline-flex items-center px-4 py-2 border border-indigo-300 dark:border-indigo-600 text-indigo-700 dark:text-indigo-300 rounded-lg font-medium hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                Acciones
            </a>
            @if(auth()->user()?->tienePermiso('clientes.editar'))
                <a href="{{ route('clientes.edit', $cliente) }}"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    Editar cliente
                </a>
            @endif
        </div>
    </div>

    {{-- Datos generales --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos del cliente</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Cédula / documento</dt>
                <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $cliente->cedula ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Teléfono</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $cliente->telefono ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                <dd class="text-gray-900 dark:text-gray-100 break-all">{{ $cliente->email ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-gray-500 dark:text-gray-400">Dirección</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $cliente->direccion ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Estado</dt>
                <dd class="text-gray-900 dark:text-gray-100 capitalize">{{ $cliente->estado ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Calificación de pago</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $cliente->calificacion_pago_label ?? '—' }}</dd>
            </div>
            @if($mapsUrl)
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Ubicación</dt>
                    <dd><a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:underline">Ver en mapa</a></dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Servicios --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Servicios asociados</h2>
        @if($cliente->servicios->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay servicios registrados para este cliente.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-600">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Instalación</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">PPPoE</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                            @if(auth()->user()?->tienePermiso('servicios.crear'))
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($cliente->servicios as $s)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-gray-900 dark:text-gray-100">{{ $s->servicio_id }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $s->plan?->nombre ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $s->fecha_instalacion?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300 font-mono text-xs">{{ $s->ip ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $s->usuario_pppoe ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $estadosServicio[$s->estado] ?? $s->estado }}</td>
                                @if(auth()->user()?->tienePermiso('servicios.crear'))
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('servicios.edit', $s) }}" class="text-purple-600 dark:text-purple-400 hover:underline">Editar</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Dos columnas: cobros | tickets --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 min-w-0">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Historial de pagos</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Últimos {{ $cobros->count() }} cobros registrados</p>
            @if($cobros->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay cobros registrados.</p>
            @else
                <div class="overflow-x-auto max-h-[32rem] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Fecha</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Monto</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Forma</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Recibo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($cobros as $cobro)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $cobro->fecha_pago?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format((float) $cobro->monto, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $formasPago[$cobro->forma_pago] ?? $cobro->forma_pago }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                        @if(auth()->user()?->tienePermiso('cobros.ver'))
                                            <a href="{{ route('cobros.show', $cobro) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-mono text-xs">{{ $cobro->numero_recibo }}</a>
                                        @else
                                            <span class="font-mono text-xs">{{ $cobro->numero_recibo }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 min-w-0">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Historial de tickets</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Últimos {{ $tickets->count() }} tickets</p>
            @if($tickets->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay tickets para este cliente.</p>
            @else
                <div class="overflow-x-auto max-h-[32rem] overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">#</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Fecha</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Asunto</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($tickets as $t)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100 font-medium">
                                        @if(auth()->user()?->tienePermiso('tickets.crear'))
                                            <a href="{{ route('tickets.edit', $t) }}" class="text-purple-600 dark:text-purple-400 hover:underline">#{{ $t->id }}</a>
                                        @else
                                            #{{ $t->id }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $t->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300 max-w-[12rem] truncate" title="{{ $t->ticketAsunto?->nombre ?? '' }}">{{ $t->ticketAsunto?->nombre ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $estadosTicket[$t->estado] ?? $t->estado }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
