@extends('layouts.app')

@section('title', 'Equipos sin acceso')

@section('content')
<div class="max-w-5xl">
    @include('whatsapp._tabs')
    <h1 class="text-xl font-semibold mb-1">Equipos sin SSH o ACS</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Cuando un reclamo no se puede medir, el caso pasa a técnico y queda anotado acá. Se borra solo cuando ese acceso vuelve a responder.</p>

    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                <tr>
                    <th class="px-3 py-2">Servicio</th>
                    <th class="px-3 py-2">Vía</th>
                    <th class="px-3 py-2">IP</th>
                    <th class="px-3 py-2">Error</th>
                    <th class="px-3 py-2">Última vez</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fallos as $fila)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2">#{{ $fila->servicio_id }}</td>
                        <td class="px-3 py-2">{{ strtoupper($fila->via) }}</td>
                        <td class="px-3 py-2">{{ $fila->ip ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $fila->error }}</td>
                        <td class="px-3 py-2">{{ optional($fila->ultimo_at)->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-3" colspan="5">Ningún equipo con SSH o ACS caído.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
