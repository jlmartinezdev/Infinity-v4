@extends('layouts.app')

@section('title', 'Mapa de cajas NAP')

@section('content')
<div class="max-w-full mx-auto flex flex-col h-[calc(100vh-8rem)] min-h-[400px]">
    <div class="mb-4 flex-shrink-0 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Mapa de infraestructura óptica</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cajas NAP, nodos, salidas PON y líneas de cable</p>
        </div>
        <div class="flex items-center gap-3">
            <select id="filtro-nodo" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                <option value="">Todos los nodos</option>
                @foreach($nodos as $n)
                    <option value="{{ $n->nodo_id }}" {{ (request('nodo_id') ?: $nodoId ?? '') == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                @endforeach
            </select>
            <a href="{{ route('sistema.cajas-nap.index') }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">Gestionar cajas NAP</a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex-1 min-h-[300px]">
        <div id="mapa-nap-app" class="w-full h-full min-h-[400px]"></div>
    </div>

    @if(!$apiKey)
        <div class="mt-4 px-4 py-6 text-center text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            Configurá GOOGLE_MAPS_API_KEY en .env para ver el mapa.
        </div>
    @endif
</div>

@push('scripts')
<script>
    window.__MAPA_NAP_CONFIG__ = {
        apiKey: @json($apiKey),
        mapaDataUrl: @json(route('sistema.cajas-nap.mapa.data')),
        nodoId: @json(request('nodo_id', $nodoId ?? '')),
    };
</script>
<script src="{{ asset(mix('js/mapa-nap.js')) }}"></script>
@endpush
@endsection
