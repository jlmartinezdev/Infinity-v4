@extends('layouts.app')

@section('title', 'Corte de servicio')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ url('/') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Inicio</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Corte de servicio</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Ejecuta el mismo proceso que el schedule <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded">servicios:corte-automatico</code>: suspende por falta de pago y deshabilita PPPoE en MikroTik cuando aplica. Se usa <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 rounded">--force</code> para no depender del día de corte configurado.
    </p>

    @if(session('corte_output'))
        <div class="mb-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-800/80 text-gray-800 dark:text-gray-200 text-xs font-mono whitespace-pre-wrap border border-gray-200 dark:border-gray-700">{{ session('corte_output') }}</div>
    @endif

    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Todos los nodos</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Equivalente a ejecutar el corte sin filtrar por nodo.</p>
            </div>
            <div class="p-6">
                <form action="{{ route('admin.corte-servicio.todos') }}" method="POST" onsubmit="return confirm('¿Ejecutar corte automático para todos los nodos?');">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                        Ejecutar corte (todos los nodos)
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Un nodo específico</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Solo se suspenden servicios cuyo router pertenece al nodo elegido (según pool → router → nodo).</p>
            </div>
            <div class="p-6">
                <form action="{{ route('admin.corte-servicio.nodo') }}" method="POST" class="space-y-4" onsubmit="return confirm('¿Ejecutar corte automático solo para este nodo?');">
                    @csrf
                    <div>
                        <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nodo</label>
                        <select name="nodo_id" id="nodo_id" required
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm px-3 py-2 focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            <option value="">— Seleccionar —</option>
                            @foreach($nodos as $nodo)
                                <option value="{{ $nodo->nodo_id }}">{{ $nodo->descripcion }}</option>
                            @endforeach
                        </select>
                        @error('nodo_id')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-amber-700 hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                        Ejecutar corte (nodo seleccionado)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
