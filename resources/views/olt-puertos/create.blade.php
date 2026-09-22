@extends('layouts.app')

@section('title', 'Nuevo puerto PON')

@php
    $fc = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $lb = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $modeloNombre = \App\Support\OltModelosCatalogo::nombre($olt->modelo) ?: ($olt->modelo ?? null);
    $modeloImagen = \App\Support\OltModelosCatalogo::imagenUrl($olt->modelo);
    $numeroSel = (int) old('numero', $siguiente);
    $ocupados = $ocupados ?? [];
    $maxGrid = (int) ($maxGrid ?? 16);
    $registrarSalida = (string) old('registrar_salida', '1') === '1';
    $libres = max(0, $maxGrid - count($ocupados));
@endphp

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.show', $olt) }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver al OLT</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Nuevo puerto PON</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">El puerto del equipo y la salida de fibra son el mismo PON.</p>
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
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ count($ocupados) }} ocupado{{ count($ocupados) === 1 ? '' : 's' }}
                · {{ $libres }} libre{{ $libres === 1 ? '' : 's' }}
                @if($olt->cantidad_puerto)
                    · {{ $olt->cantidad_puerto }} declarados
                @endif
            </p>
        </div>
    </div>

    <form action="{{ route('sistema.olt-puertos.store', $olt) }}" method="POST" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800" id="form-nuevo-puerto-pon">
        @csrf
        <input type="hidden" name="numero" id="numero" value="{{ $numeroSel }}">

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Puerto del equipo</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Elegí un número libre. Los ocupados ya están cargados en este OLT.</p>
        </div>
        <div class="space-y-5 p-6">
            <div>
                <span class="{{ $lb }}">Número de puerto <span class="text-red-500">*</span></span>
                <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-8" id="pon-numeros">
                    @for($i = 1; $i <= $maxGrid; $i++)
                        @php $usado = in_array($i, $ocupados, true); @endphp
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
                            @if($usado)
                                <span class="sr-only">ocupado</span>
                            @endif
                        </button>
                    @endfor
                </div>
                @error('numero')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                @if($libres === 0)
                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">Todos los puertos declarados ya están cargados. Podés indicar otro número abajo.</p>
                    <input type="number" min="1" max="128" value="{{ $numeroSel }}" id="numero-extra"
                        class="{{ $fc }} max-w-[8rem]" placeholder="N.º">
                @endif
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="tipo_pon" class="{{ $lb }}">Tipo PON <span class="text-red-500">*</span></label>
                    <select name="tipo_pon" id="tipo_pon" required class="{{ $fc }}">
                        @foreach(['GPON', 'EPON', 'XG-PON'] as $tipo)
                            <option value="{{ $tipo }}" @selected(old('tipo_pon', $olt->tipo_pon) === $tipo)>{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="estado" class="{{ $lb }}">Estado</label>
                    <select name="estado" id="estado" class="{{ $fc }}">
                        <option value="activo" @selected(old('estado', 'activo') === 'activo')>Activo</option>
                        <option value="inactivo" @selected(old('estado') === 'inactivo')>Inactivo</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="notas" class="{{ $lb }}">Notas</label>
                    <textarea name="notas" id="notas" rows="2" class="{{ $fc }}" placeholder="Uso interno, splitter, observaciones…">{{ old('notas') }}</textarea>
                </div>
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <div class="flex items-start gap-3">
                <input type="hidden" name="registrar_salida" value="0">
                <input type="checkbox" name="registrar_salida" id="registrar_salida" value="1"
                    class="mt-1 h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                    @checked($registrarSalida)>
                <div>
                    <label for="registrar_salida" class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">También registrar salida de fibra</label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Crea la salida PON asociada a este puerto (código, módulo) para enlazar cajas NAP.</p>
                </div>
            </div>
        </div>
        <div class="grid gap-5 p-6 sm:grid-cols-2" id="bloque-salida">
            <div>
                <label for="codigo" class="{{ $lb }}">Código <span class="text-red-500">*</span></label>
                <input type="text" name="codigo" id="codigo" value="{{ old('codigo', 'PON-'.$numeroSel) }}" maxlength="50" class="{{ $fc }}" placeholder="PON-1">
                @error('codigo')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tipo_modulo" class="{{ $lb }}">Tipo de módulo</label>
                <select name="tipo_modulo" id="tipo_modulo" class="{{ $fc }}">
                    <option value="">— Sin especificar —</option>
                    @foreach(\App\Models\SalidaPon::TIPOS_MODULO as $tipo)
                        <option value="{{ $tipo }}" @selected(old('tipo_modulo') === $tipo)>{{ $tipo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="potencia_salida" class="{{ $lb }}">Potencia salida <span class="font-normal text-gray-500">(dBm)</span></label>
                <input type="text" name="potencia_salida" id="potencia_salida" value="{{ old('potencia_salida') }}" class="{{ $fc }}" inputmode="decimal" placeholder="Ej. 5.0">
                @error('potencia_salida')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/30">
            <button type="submit" class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Crear puerto
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
    var codigo = document.getElementById('codigo');
    var check = document.getElementById('registrar_salida');
    var bloque = document.getElementById('bloque-salida');
    var extra = document.getElementById('numero-extra');
    var codigoTouched = {{ old('codigo') ? 'true' : 'false' }};

    function clasesLibres(activo) {
        return activo
            ? 'pon-num-btn h-11 rounded-lg border text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/30 border-purple-600 bg-purple-600 text-white shadow-sm'
            : 'pon-num-btn h-11 rounded-lg border text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500/30 border-gray-300 bg-white text-gray-800 hover:border-purple-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100';
    }

    function syncCodigo(n) {
        if (!codigo || codigoTouched) return;
        codigo.value = 'PON-' + n;
    }

    function seleccionar(n) {
        if (!hidden) return;
        hidden.value = String(n);
        document.querySelectorAll('.pon-num-btn:not([disabled])').forEach(function (btn) {
            btn.className = clasesLibres(String(btn.getAttribute('data-numero')) === String(n));
        });
        syncCodigo(n);
        if (extra) extra.value = n;
    }

    document.querySelectorAll('.pon-num-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            seleccionar(btn.getAttribute('data-numero'));
        });
    });

    if (codigo) {
        codigo.addEventListener('input', function () { codigoTouched = true; });
    }
    if (extra) {
        extra.addEventListener('input', function () {
            var n = parseInt(extra.value, 10);
            if (!isNaN(n) && n >= 1) {
                hidden.value = String(n);
                syncCodigo(n);
            }
        });
    }

    function toggleSalida() {
        if (!bloque || !check) return;
        var on = check.checked;
        bloque.classList.toggle('hidden', !on);
        bloque.querySelectorAll('input, select').forEach(function (el) {
            el.disabled = !on;
            if (el.id === 'codigo') el.required = on;
        });
    }
    if (check) {
        check.addEventListener('change', toggleSalida);
        toggleSalida();
    }
})();
</script>
@endpush
@endsection
