@extends('layouts.app')

@section('title', 'Personalizar acceso rápido')

@section('content')
@php
    $disponibles = $disponibles ?? [];
    $seleccionados = $seleccionados ?? [];
    $usaDefault = $usaDefault ?? true;
@endphp

<div class="max-w-2xl mx-auto min-w-0">
    <div class="mb-4">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
           class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Volver</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Acceso rápido</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Elegí y ordená los atajos del menú lateral. Solo se muestran opciones según tus permisos.
            @if($usaDefault)
                <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">Usando valores por defecto</span>
            @else
                <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">Personalizado</span>
            @endif
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    @if(count($disponibles) === 0)
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            No hay accesos disponibles con tus permisos actuales. Pedile a un administrador que te asigne permisos.
        </div>
    @else
        <form method="post" action="{{ route('acceso-rapido.update') }}" id="form-acceso-rapido" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                    <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Tus atajos (orden del menú)</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Marcá los que querés ver y usá las flechas para ordenar.</p>
                </div>

                <ul id="lista-acceso-rapido" class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($disponibles as $item)
                        @php $checked = in_array($item['name'], $seleccionados, true); @endphp
                        <li class="js-acceso-item flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/40"
                            data-name="{{ $item['name'] }}"
                            @if(!$checked) style="order: 999;" @endif>
                            <input type="checkbox"
                                   name="items[]"
                                   value="{{ $item['name'] }}"
                                   class="js-acceso-check rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500"
                                   @checked($checked)>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $item['path'] }}</p>
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <button type="button" class="js-acceso-up p-1.5 rounded-lg text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-600" title="Subir" aria-label="Subir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <button type="button" class="js-acceso-down p-1.5 rounded-lg text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-600" title="Bajar" aria-label="Bajar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold shadow-sm">
                    Guardar acceso rápido
                </button>
                <a href="{{ url('/') }}"
                   class="inline-flex items-center px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancelar
                </a>
            </div>
        </form>

        <form method="post" action="{{ route('acceso-rapido.reset') }}" class="mt-4"
              onsubmit="return confirm('¿Restaurar el acceso rápido por defecto?');">
            @csrf
            <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 underline">
                Restaurar valores por defecto
            </button>
        </form>
    @endif
</div>

@if(count($disponibles) > 0)
@php
    // Orden inicial: seleccionados primero (en su orden), luego el resto
    $ordenInicial = array_values(array_unique(array_merge(
        $seleccionados,
        collect($disponibles)->pluck('name')->all()
    )));
@endphp
<script>
(function () {
    const lista = document.getElementById('lista-acceso-rapido');
    if (!lista) return;

    const ordenInicial = @json($ordenInicial);

    function items() {
        return Array.from(lista.querySelectorAll('.js-acceso-item'));
    }

    function reorderByNames(names) {
        const map = {};
        items().forEach((el) => { map[el.dataset.name] = el; });
        names.forEach((name) => {
            if (map[name]) lista.appendChild(map[name]);
        });
        items().forEach((el) => {
            if (!names.includes(el.dataset.name)) lista.appendChild(el);
        });
    }

    reorderByNames(ordenInicial);

    function currentOrder() {
        return items().map((el) => el.dataset.name);
    }

    function move(el, dir) {
        const all = items();
        const idx = all.indexOf(el);
        const swap = idx + dir;
        if (swap < 0 || swap >= all.length) return;
        if (dir < 0) {
            lista.insertBefore(el, all[swap]);
        } else {
            lista.insertBefore(all[swap], el);
        }
    }

    lista.addEventListener('click', (e) => {
        const up = e.target.closest('.js-acceso-up');
        const down = e.target.closest('.js-acceso-down');
        if (!up && !down) return;
        const row = e.target.closest('.js-acceso-item');
        if (!row) return;
        move(row, up ? -1 : 1);
    });

    // Al enviar, solo incluir marcados en el orden visual actual
    document.getElementById('form-acceso-rapido')?.addEventListener('submit', () => {
        items().forEach((el) => {
            const cb = el.querySelector('.js-acceso-check');
            if (!cb) return;
            // Deshabilitar no marcados para que no viajen en el POST
            cb.disabled = !cb.checked;
        });
    });
})();
</script>
@endif
@endsection
