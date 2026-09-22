@extends('layouts.app')

@section('title', 'Usuarios RADIUS')

@php
    $estados = \App\Models\Servicio::estadosDisponibles();
@endphp

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Usuarios en FreeRADIUS</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Lo que está escrito en <code class="text-xs">radcheck</code> / <code class="text-xs">radreply</code>, no solo en Infinity.</p>
        </div>
        <a href="{{ route('hotspot.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-50 dark:hover:bg-gray-700">
            Volver a usuarios
        </a>
    </div>

    @if(! $ok)
        <div class="mb-4 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 text-sm border border-amber-200 dark:border-amber-800">
            No se pudo leer RADIUS: {{ $error ?: 'sin detalle' }}.
            Revisá <code>RADIUS_ENABLED</code> y la conexión <code>radius</code> (puerto 3312).
        </div>
    @endif

    <form method="GET" action="{{ route('hotspot.radius') }}" class="mb-6 flex flex-wrap gap-3">
        <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Filtrar por usuario"
            class="flex-1 min-w-[16rem] px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm">
        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700">Buscar</button>
        @if($buscar !== '')
            <a href="{{ route('hotspot.radius') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium">Limpiar</a>
        @endif
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2">Usuario</th>
                    <th class="px-3 py-2">PIN</th>
                    <th class="px-3 py-2">Auth</th>
                    <th class="px-3 py-2">Rate</th>
                    <th class="px-3 py-2">Cuota</th>
                    <th class="px-3 py-2">Sesión</th>
                    <th class="px-3 py-2">En Infinity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($usuarios as $u)
                    <tr>
                        <td class="px-3 py-2 font-mono text-gray-900 dark:text-gray-100">{{ $u['username'] }}</td>
                        <td class="px-3 py-2 font-mono text-gray-600 dark:text-gray-300">{{ $u['password'] ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if($u['rechazado'])
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">Reject</span>
                            @else
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">OK</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $u['rate_limit'] ?: '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $u['cuota'] ?: '—' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                            @if(! empty($u['sesiones']))
                                @foreach($u['sesiones'] as $ses)
                                    <p>En línea {{ $ses['ip'] ?: $ses['mac'] ?: $ses['nas'] }}</p>
                                @endforeach
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if($u['en_infinity'])
                                <a href="{{ route('hotspot.clientes.edit', $u['cliente_id']) }}" class="text-purple-600 dark:text-purple-400 hover:underline">
                                    {{ $u['cliente'] ?: 'Ver' }}
                                </a>
                                @if($u['servicio_estado'])
                                    <span class="ml-1 text-xs text-gray-500">({{ $estados[$u['servicio_estado']] ?? $u['servicio_estado'] }})</span>
                                @endif
                            @else
                                <span class="text-amber-700 dark:text-amber-300 text-xs">Solo en RADIUS</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                            @if($ok)
                                No hay usuarios en radcheck{{ $buscar !== '' ? ' para esa búsqueda' : '' }}.
                            @else
                                Sin datos.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($soloInfinity->isNotEmpty())
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-amber-200 dark:border-amber-800 p-4">
            <h2 class="text-sm font-semibold text-amber-800 dark:text-amber-200 mb-2">En Infinity, todavía no en RADIUS</h2>
            <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                @foreach($soloInfinity as $h)
                    <li>
                        <span class="font-mono">{{ $h->username }}</span>
                        @if($h->cliente)
                            — {{ $h->cliente->nombre }} {{ $h->cliente->apellido }}
                        @endif
                        <form action="{{ route('hotspot.sync', $h) }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="from_cliente" value="1">
                            <button type="submit" class="text-purple-600 dark:text-purple-400 hover:underline">Sincronizar</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
