@extends('layouts.app')

@section('title', 'WhatsApp · Asuntos')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Asuntos para clasificar conversaciones</p>
        </div>
        @if(auth()->user()?->tienePermiso('whatsapp.editar'))
            <a href="{{ route('whatsapp.asuntos.create') }}"
               class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                Nuevo asunto
            </a>
        @endif
    </div>

    @include('whatsapp._tabs', ['waTab' => 'asuntos'])

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" class="mb-4">
        <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar asunto…"
               class="w-full max-w-sm rounded-xl border-0 bg-white px-3 py-2 text-sm ring-1 ring-gray-200 focus:ring-2 focus:ring-emerald-500 dark:bg-gray-800 dark:text-gray-100 dark:ring-gray-600">
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700/80">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Asunto</th>
                    <th class="px-4 py-3">Orden</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                @forelse($asuntos as $a)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-full" style="background: {{ $a->color }}"></span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $a->nombre }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $a->orden }}</td>
                        <td class="px-4 py-3">
                            @if($a->activo)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">Activo</span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            @if(auth()->user()?->tienePermiso('whatsapp.editar'))
                                <a href="{{ route('whatsapp.asuntos.edit', $a) }}" class="text-emerald-600 hover:underline dark:text-emerald-400">Editar</a>
                                <form action="{{ route('whatsapp.asuntos.destroy', $a) }}" method="POST" class="ml-3 inline"
                                      onsubmit="return confirm('¿Eliminar «{{ $a->nombre }}»?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:underline dark:text-rose-400">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">Sin asuntos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $asuntos->links() }}</div>
</div>
@endsection
