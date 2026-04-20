@extends('layouts.app')

@section('title', 'Editar salida PON')

@php
    $fc = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $lb = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $oltIdOld = old('olt_id', $salidaPon->olt_id);
    $oltActual = isset($olts) ? $olts->firstWhere('olt_id', (int) $oltIdOld) : null;
    if ($oltActual) {
        $cPuerto = (int) ($oltActual->cantidad_puerto ?? 0);
        $maxIni = $cPuerto > 0 ? $cPuerto : \App\Models\SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT;
    } else {
        $maxIni = \App\Models\SalidaPon::PUERTOS_MAX_SIN_DECLARAR_EN_OLT;
    }
    $puertoVal = (int) old('puerto_olt', $salidaPon->puerto_olt ?? 1);
    if ($puertoVal > $maxIni) {
        $puertoVal = 1;
    }
@endphp

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.salida-pons.index') }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver al listado</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Editar salida PON</h1>
        <p class="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400">{{ $salidaPon->codigo }}</p>
    </div>

    <form action="{{ route('sistema.salida-pons.update', $salidaPon) }}" method="POST" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        @method('PUT')
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Datos generales</h2>
        </div>
        <div class="space-y-5 p-6">
            <div>
                <label for="nodo_id" class="{{ $lb }}">Nodo <span class="text-red-500">*</span></label>
                <select name="nodo_id" id="nodo_id" required class="{{ $fc }}">
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ (string) old('nodo_id', $salidaPon->nodo_id) === (string) $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                @error('nodo_id')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="olt_id" class="{{ $lb }}">OLT <span class="font-normal text-gray-500 dark:text-gray-400">(opcional)</span></label>
                <select name="olt_id" id="olt_id" class="{{ $fc }}">
                    <option value="">— Sin OLT —</option>
                    @foreach($olts ?? [] as $o)
                        @php
                            $ports = (int) ($o->cantidad_puerto ?? 0);
                        @endphp
                        <option value="{{ $o->olt_id }}"
                            data-nodo="{{ $o->nodo_id }}"
                            data-ports="{{ $ports }}"
                            {{ (string) old('olt_id', $salidaPon->olt_id) === (string) $o->olt_id ? 'selected' : '' }}>
                            {{ $o->codigo ?? $o->ip ?? 'OLT #' . $o->olt_id }}
                            @if($ports > 0)
                                — {{ $ports }} puerto{{ $ports === 1 ? '' : 's' }}
                            @endif
                            ({{ $o->nodo?->descripcion ?? '—' }})
                        </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Solo OLT del nodo elegido. Puertos según cantidad declarada en el OLT.</p>
                @error('olt_id')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Módulo y puerto</h2>
        </div>
        <div class="grid gap-5 p-6 sm:grid-cols-2">
            <div>
                <label for="tipo_modulo" class="{{ $lb }}">Tipo de módulo</label>
                <select name="tipo_modulo" id="tipo_modulo" class="{{ $fc }}">
                    <option value="">— Sin especificar —</option>
                    @php $tipoActual = old('tipo_modulo', $salidaPon->tipo_modulo); @endphp
                    @if($tipoActual && ! in_array($tipoActual, \App\Models\SalidaPon::TIPOS_MODULO, true))
                        <option value="{{ $tipoActual }}" selected>{{ $tipoActual }} (valor guardado)</option>
                    @endif
                    @foreach(\App\Models\SalidaPon::TIPOS_MODULO as $tipo)
                        <option value="{{ $tipo }}" {{ $tipoActual === $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                    @endforeach
                </select>
                @error('tipo_modulo')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="potencia_salida" class="{{ $lb }}">Potencia salida <span class="font-normal text-gray-500">(dBm)</span></label>
                <input type="text" name="potencia_salida" id="potencia_salida" value="{{ old('potencia_salida', $salidaPon->potencia_salida) }}" class="{{ $fc }}" inputmode="decimal">
            </div>
            <div>
                <label for="codigo" class="{{ $lb }}">Código <span class="text-red-500">*</span></label>
                <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $salidaPon->codigo) }}" required maxlength="50" class="{{ $fc }}">
                @error('codigo')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="puerto_olt" class="{{ $lb }}">Puerto OLT</label>
                <select name="puerto_olt" id="puerto_olt" class="{{ $fc }}">
                    @for($i = 1; $i <= $maxIni; $i++)
                        <option value="{{ $i }}" {{ $puertoVal === $i ? 'selected' : '' }}>Puerto {{ $i }}</option>
                    @endfor
                </select>
                @error('puerto_olt')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Estado y notas</h2>
        </div>
        <div class="space-y-5 p-6">
            <div>
                <label for="estado" class="{{ $lb }}">Estado</label>
                <select name="estado" id="estado" class="{{ $fc }}">
                    @foreach(['activo' => 'Activo', 'inactivo' => 'Inactivo'] as $val => $lab)
                        <option value="{{ $val }}" {{ old('estado', $salidaPon->estado ?? 'activo') === $val ? 'selected' : '' }}>{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="nota" class="{{ $lb }}">Nota</label>
                <textarea name="nota" id="nota" rows="3" class="{{ $fc }}">{{ old('nota', $salidaPon->nota) }}</textarea>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/30">
            <button type="submit" class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Guardar cambios
            </button>
            <a href="{{ route('sistema.salida-pons.index') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Cancelar
            </a>
        </div>
    </form>
</div>

@include('salida-pons.partials.form-scripts')
@endsection
