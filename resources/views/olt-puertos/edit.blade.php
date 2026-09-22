@extends('layouts.app')

@section('title', 'Editar puerto PON')

@php
    $olt = $oltPuerto->olt;
    $fc = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $lb = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $modeloNombre = \App\Support\OltModelosCatalogo::nombre($olt->modelo) ?: ($olt->modelo ?? null);
    $modeloImagen = \App\Support\OltModelosCatalogo::imagenUrl($olt->modelo);
    $numeroSel = (int) old('numero', $oltPuerto->numero);
    $ocupados = $ocupados ?? [];
    $maxGrid = (int) ($maxGrid ?? 16);
    $salida = $oltPuerto->salidaPon;
@endphp

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.show', $olt) }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver al OLT</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Editar puerto {{ $oltPuerto->numero }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $olt->codigo ?? $olt->ip ?? 'OLT #'.$olt->olt_id }} — {{ $olt->marca ?? '—' }}</p>
    </div>

    <div class="mb-6 flex items-center gap-4 overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="shrink-0 rounded-lg border border-gray-100 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/40">
            <img src="{{ $modeloImagen }}" alt="{{ $modeloNombre ?? 'OLT' }}" class="h-14 w-28 object-contain">
        </div>
        <div class="min-w-0">
            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $olt->codigo ?? $olt->ip ?? 'OLT #'.$olt->olt_id }}</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $olt->marca ?? '—' }}{{ $modeloNombre ? ' · '.$modeloNombre : '' }}
                · {{ $olt->nodo?->descripcion ?? 'sin nodo' }}
            </p>
            @if($salida)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Salida de fibra:
                    <a href="{{ route('sistema.salida-pons.edit', ['salida_pon' => $salida, 'return_olt' => $olt->olt_id]) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">{{ $salida->codigo }}</a>
                    @if($salida->tipo_modulo)
                        · {{ $salida->tipo_modulo }}
                    @endif
                </p>
            @endif
        </div>
    </div>

    <form action="{{ route('sistema.olt-puertos.update', $oltPuerto) }}" method="POST" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        @method('PUT')
        <input type="hidden" name="numero" id="numero" value="{{ $numeroSel }}">

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Puerto del equipo</h2>
        </div>
        <div class="space-y-5 p-6">
            <div>
                <span class="{{ $lb }}">Número de puerto <span class="text-red-500">*</span></span>
                <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-8" id="pon-numeros">
                    @for($i = 1; $i <= $maxGrid; $i++)
                        @php $usado = in_array($i, $ocupados, true) && $i !== (int) $oltPuerto->numero; @endphp
                        <button type="button"
                            data-numero="{{ $i }}"
                            @disabled($usado)
                            class="pon-num-btn h-11 rounded-lg border text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/30
                                {{ $usado
                                    ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-600'
                                    : ($numeroSel === $i
                                        ? 'border-purple-600 bg-purple-600 text-white shadow-sm'
                                        : 'border-gray-300 bg-white text-gray-800 hover:border-purple-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100') }}">
                            {{ $i }}
                        </button>
                    @endfor
                </div>
                @error('numero')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="tipo_pon" class="{{ $lb }}">Tipo PON <span class="text-red-500">*</span></label>
                    <select name="tipo_pon" id="tipo_pon" required class="{{ $fc }}">
                        @foreach(['GPON', 'EPON', 'XG-PON'] as $tipo)
                            <option value="{{ $tipo }}" @selected(old('tipo_pon', $oltPuerto->tipo_pon) === $tipo)>{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="estado" class="{{ $lb }}">Estado</label>
                    <select name="estado" id="estado" class="{{ $fc }}">
                        <option value="activo" @selected(old('estado', $oltPuerto->estado) === 'activo')>Activo</option>
                        <option value="inactivo" @selected(old('estado', $oltPuerto->estado) === 'inactivo')>Inactivo</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="notas" class="{{ $lb }}">Notas</label>
                    <textarea name="notas" id="notas" rows="2" class="{{ $fc }}">{{ old('notas', $oltPuerto->notas) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/30">
            <button type="submit" class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Guardar cambios
            </button>
            <a href="{{ route('sistema.olts.show', $olt) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Cancelar
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    var hidden = document.getElementById('numero');
    function clasesLibres(activo) {
        return activo
            ? 'pon-num-btn h-11 rounded-lg border text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/30 border-purple-600 bg-purple-600 text-white shadow-sm'
            : 'pon-num-btn h-11 rounded-lg border text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/30 border-gray-300 bg-white text-gray-800 hover:border-purple-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100';
    }
    document.querySelectorAll('.pon-num-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            var n = btn.getAttribute('data-numero');
            hidden.value = n;
            document.querySelectorAll('.pon-num-btn:not([disabled])').forEach(function (b) {
                b.className = clasesLibres(b.getAttribute('data-numero') === n);
            });
        });
    });
})();
</script>
@endpush
@endsection
