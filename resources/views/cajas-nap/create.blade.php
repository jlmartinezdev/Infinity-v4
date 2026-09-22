@extends('layouts.app')

@section('title', 'Nueva caja NAP')

@php
    $inputClass = 'w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500';
    $mapLat = is_numeric(old('lat')) ? (float) old('lat') : null;
    $mapLon = is_numeric(old('lon')) ? (float) old('lon') : null;
    $nodosCoords = [];
    foreach ($nodos ?? [] as $nodoMapa) {
        $coordsNodo = $nodoMapa->getCoordenadasParaMapa();
        if ($coordsNodo) {
            $nodosCoords[(string) $nodoMapa->nodo_id] = $coordsNodo;
        }
    }
    $cajaNapFormMapaConfig = [
        'apiKey' => $apiKey ?? '',
        'initialLat' => $mapLat,
        'initialLon' => $mapLon,
        'nodosCoords' => $nodosCoords,
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ request('return_olt') ? route('sistema.olts.show', request('return_olt')) : route('sistema.cajas-nap.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm font-medium">&larr; {{ request('return_olt') ? 'Volver al OLT' : 'Volver a cajas NAP' }}</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Nueva caja NAP</h1>
    </div>

    <form action="{{ route('sistema.cajas-nap.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @csrf
        @if(request('return_olt') || old('return_olt'))
            <input type="hidden" name="return_olt" value="{{ old('return_olt', request('return_olt')) }}">
        @endif

        <div class="space-y-4">
            <div>
                <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nodo *</label>
                <select name="nodo_id" id="nodo_id" required class="mt-1 {{ $inputClass }}">
                    <option value="">Seleccione nodo</option>
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ (string) old('nodo_id', request('nodo_id')) === (string) $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                @error('nodo_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="salida_pon_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Salida PON (origen fibra)</label>
                <select name="salida_pon_id" id="salida_pon_id" class="mt-1 {{ $inputClass }}">
                    <option value="">— Sin enlazar —</option>
                    @foreach($salidas as $sp)
                        <option value="{{ $sp->salida_pon_id }}" data-nodo="{{ $sp->nodo_id }}" {{ (string) old('salida_pon_id', request('salida_pon_id')) === (string) $sp->salida_pon_id ? 'selected' : '' }}>
                            {{ $sp->codigo }} ({{ $sp->nodo?->descripcion }}{{ $sp->olt ? ' · '.($sp->olt->codigo ?? $sp->olt->ip) : '' }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se listan solo las salidas PON del <span class="font-medium">nodo elegido arriba</span>. Deben coincidir con el nodo de la caja.</p>
                @error('salida_pon_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="splitter_primer_nivel" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Splitter primer nivel</label>
                    <select name="splitter_primer_nivel" id="splitter_primer_nivel" class="mt-1 {{ $inputClass }}">
                        <option value="" {{ old('splitter_primer_nivel') === null || old('splitter_primer_nivel') === '' ? 'selected' : '' }}>Sin splitter</option>
                        @foreach(\App\Models\CajaNap::SPLITTERS_PRIMER_NIVEL as $ratio)
                            <option value="{{ $ratio }}" @selected(old('splitter_primer_nivel') === $ratio)>{{ $ratio }}</option>
                        @endforeach
                    </select>
                    @error('splitter_primer_nivel')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="potencia_salida" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Potencia salida (dBm)</label>
                    <input type="text" name="potencia_salida" id="potencia_salida" value="{{ old('potencia_salida') }}" class="mt-1 {{ $inputClass }}" inputmode="decimal">
                    @error('potencia_salida')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="nota" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nota</label>
                <textarea name="nota" id="nota" rows="2" maxlength="2000" class="mt-1 {{ $inputClass }}">{{ old('nota') }}</textarea>
                @error('nota')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código</label>
                    <p class="mt-1 px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50 font-mono text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">{{ $codigoSugerido }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se genera automáticamente al guardar (NAP-001, NAP-002…).</p>
                </div>
                <div>
                    <label for="splitter_segundo_nivel" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Splitter secundario</label>
                    <select name="splitter_segundo_nivel" id="splitter_segundo_nivel" class="mt-1 {{ $inputClass }}">
                        <option value="">Sin definir</option>
                        <option value="8" {{ (string) old('splitter_segundo_nivel') === '8' ? 'selected' : '' }}>1×8 (8 puertos)</option>
                        <option value="16" {{ (string) old('splitter_segundo_nivel') === '16' ? 'selected' : '' }}>1×16 (16 puertos)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Define cuántos puertos de cliente tendrá la caja en fibra.</p>
                    @error('splitter_segundo_nivel')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion') }}" maxlength="255"
                    class="mt-1 {{ $inputClass }}">
                @error('descripcion')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                <input type="text" name="direccion" id="direccion" value="{{ old('direccion') }}" maxlength="255"
                    class="mt-1 {{ $inputClass }}" placeholder="Calle, barrio, referencia…">
                @error('direccion')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="lat" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitud</label>
                    <input type="number" name="lat" id="lat" value="{{ old('lat') }}" step="any" min="-90" max="90"
                        class="mt-1 {{ $inputClass }}" placeholder="-25.2637">
                    @error('lat')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="lon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitud</label>
                    <input type="number" name="lon" id="lon" value="{{ old('lon') }}" step="any" min="-180" max="180"
                        class="mt-1 {{ $inputClass }}" placeholder="-57.5759">
                    @error('lon')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="pt-1">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ubicación en mapa</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">El mapa se abre en el nodo elegido. Hacé clic para colocar el punto o arrastrá el marcador para afinar. Las coordenadas se actualizan arriba.</p>
                <div id="caja-nap-form-mapa-app"></div>
                @if(!$apiKey)
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Sin clave de Google Maps podés cargar latitud y longitud manualmente.</p>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Crear caja NAP</button>
            <a href="{{ request('return_olt') ? route('sistema.olts.show', request('return_olt')) : route('sistema.cajas-nap.index') }}" class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@include('cajas-nap.partials.filtrar-salidas-pon-por-nodo')
<script>
    window.__CAJA_NAP_FORM_MAPA_CONFIG__ = @json($cajaNapFormMapaConfig);
</script>
<script src="{{ asset(mix('js/caja-nap-form-mapa.js')) }}" defer></script>
@endpush
