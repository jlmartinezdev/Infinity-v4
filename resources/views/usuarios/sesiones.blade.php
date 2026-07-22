@extends('layouts.app')

@section('title', 'Sesiones activas')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sesiones del sistema</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Personal conectado ahora (inactividad máx. {{ $lifetimeMinutos }} min) y últimos accesos
            </p>
        </div>
        <a href="{{ route('usuarios.index') }}"
           class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
            Volver a usuarios
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Conectados ahora</h2>
            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300">
                {{ $sesiones->count() }} sesión{{ $sesiones->count() === 1 ? '' : 'es' }}
            </span>
        </div>

        @if($sesiones->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                No hay sesiones activas de personal del sistema.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Rol</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">IP</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Dispositivo</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Última actividad</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($sesiones as $s)
                            <tr class="{{ $s->es_sesion_actual ? 'bg-blue-50/60 dark:bg-blue-900/20' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $s->name }}
                                        @if($s->es_sesion_actual)
                                            <span class="ml-1 text-xs font-normal text-blue-600 dark:text-blue-400">(vos)</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $s->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $s->rol ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $s->ip_address ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $s->navegador }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    <div>{{ $s->ultima_actividad->format('d/m/Y H:i:s') }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $s->ultima_actividad->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form action="{{ route('usuarios.sesiones.destroy', $s->session_id) }}" method="POST"
                                          onsubmit="return confirm('¿Cerrar esta sesión?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs font-medium">
                                            Cerrar sesión
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Últimos accesos (no conectados ahora)</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Se actualiza en cada inicio de sesión web o API</p>
        </div>

        @if($recientes->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                Aún no hay registros de acceso. Aparecerán al volver a iniciar sesión.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Usuario</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Rol</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">IP</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Último acceso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recientes as $u)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $u->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $u->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $u->rol?->descripcion ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $u->ultimo_acceso_ip ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    <div>{{ $u->ultimo_acceso_at->format('d/m/Y H:i:s') }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $u->ultimo_acceso_at->diffForHumans() }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
