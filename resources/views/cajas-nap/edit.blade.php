@extends('layouts.app')

@section('title', 'Editar caja NAP')

@php
    $inputClass = 'w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500';
    $mapLat = is_numeric(old('lat')) ? (float) old('lat') : (is_numeric($cajaNap->lat) ? (float) $cajaNap->lat : null);
    $mapLon = is_numeric(old('lon')) ? (float) old('lon') : (is_numeric($cajaNap->lon) ? (float) $cajaNap->lon : null);
    $cajaNapFormMapaConfig = [
        'apiKey' => $apiKey ?? '',
        'initialLat' => $mapLat,
        'initialLon' => $mapLon,
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.cajas-nap.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm font-medium">&larr; Volver a cajas NAP</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar caja NAP: {{ $cajaNap->codigo }}</h1>
    </div>

    <form action="{{ route('sistema.cajas-nap.update', $cajaNap) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nodo *</label>
                <select name="nodo_id" id="nodo_id" required class="mt-1 {{ $inputClass }}">
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ old('nodo_id', $cajaNap->nodo_id) == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                @error('nodo_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="salida_pon_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Salida PON (origen fibra)</label>
                <select name="salida_pon_id" id="salida_pon_id" class="mt-1 {{ $inputClass }}">
                    <option value="">— Sin enlazar —</option>
                    @foreach($salidas as $sp)
                        <option value="{{ $sp->salida_pon_id }}" data-nodo="{{ $sp->nodo_id }}" {{ (string) old('salida_pon_id', $cajaNap->salida_pon_id) === (string) $sp->salida_pon_id ? 'selected' : '' }}>
                            {{ $sp->codigo }} ({{ $sp->nodo?->descripcion }}{{ $sp->olt ? ' · '.($sp->olt->codigo ?? $sp->olt->ip) : '' }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Debe ser del mismo nodo que la caja.</p>
                @error('salida_pon_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="splitter_primer_nivel" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Splitter primer nivel</label>
                    <input type="text" name="splitter_primer_nivel" id="splitter_primer_nivel" value="{{ old('splitter_primer_nivel', $cajaNap->splitter_primer_nivel) }}" maxlength="10" class="mt-1 {{ $inputClass }}" placeholder="ej: 1x4">
                </div>
                <div>
                    <label for="potencia_salida" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Potencia salida (dBm)</label>
                    <input type="text" name="potencia_salida" id="potencia_salida" value="{{ old('potencia_salida', $cajaNap->potencia_salida) }}" class="mt-1 {{ $inputClass }}" inputmode="decimal">
                    @error('potencia_salida')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="nota" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nota</label>
                <textarea name="nota" id="nota" rows="2" maxlength="2000" class="mt-1 {{ $inputClass }}">{{ old('nota', $cajaNap->nota) }}</textarea>
                @error('nota')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código *</label>
                    <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $cajaNap->codigo) }}" required maxlength="50"
                        class="mt-1 {{ $inputClass }}" autocomplete="off">
                    @error('codigo')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo *</label>
                    <select name="tipo" id="tipo" required class="mt-1 {{ $inputClass }}">
                        <option value="primaria" {{ old('tipo', $cajaNap->tipo) === 'primaria' ? 'selected' : '' }}>Primaria</option>
                        <option value="secundaria" {{ old('tipo', $cajaNap->tipo) === 'secundaria' ? 'selected' : '' }}>Secundaria</option>
                    </select>
                </div>
                <div>
                    <label for="splitter_segundo_nivel" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Splitter cliente (FTTH)</label>
                    <select name="splitter_segundo_nivel" id="splitter_segundo_nivel" class="mt-1 {{ $inputClass }}">
                        <option value="">Sin definir</option>
                        <option value="8" {{ (string) old('splitter_segundo_nivel', $cajaNap->splitter_segundo_nivel) === '8' ? 'selected' : '' }}>1×8 (8 puertos)</option>
                        <option value="16" {{ (string) old('splitter_segundo_nivel', $cajaNap->splitter_segundo_nivel) === '16' ? 'selected' : '' }}>1×16 (16 puertos)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Define cuántos puertos de cliente tendrá la caja en fibra.</p>
                    @error('splitter_segundo_nivel')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                <input type="text" name="descripcion" id="descripcion" value="{{ old('descripcion', $cajaNap->descripcion) }}" maxlength="255"
                    class="mt-1 {{ $inputClass }}">
            </div>

            <div>
                <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                <input type="text" name="direccion" id="direccion" value="{{ old('direccion', $cajaNap->direccion) }}" maxlength="255"
                    class="mt-1 {{ $inputClass }}" placeholder="Calle, barrio, referencia…">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="lat" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Latitud</label>
                    <input type="number" name="lat" id="lat" value="{{ old('lat', $cajaNap->lat) }}" step="any" min="-90" max="90"
                        class="mt-1 {{ $inputClass }}">
                    @error('lat')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="lon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Longitud</label>
                    <input type="number" name="lon" id="lon" value="{{ old('lon', $cajaNap->lon) }}" step="any" min="-180" max="180"
                        class="mt-1 {{ $inputClass }}">
                    @error('lon')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="pt-1">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ubicación en mapa</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Hacé clic en el mapa para colocar el punto o arrastrá el marcador para afinar. Las coordenadas se actualizan arriba.</p>
                <div id="caja-nap-form-mapa-app"></div>
                @if(!$apiKey)
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Sin clave de Google Maps podés cargar latitud y longitud manualmente.</p>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">Guardar cambios</button>
            <a href="{{ route('sistema.cajas-nap.index') }}" class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    window.__CAJA_NAP_FORM_MAPA_CONFIG__ = @json($cajaNapFormMapaConfig);
</script>
<script src="{{ asset(mix('js/caja-nap-form-mapa.js')) }}" defer></script>
@endpush
