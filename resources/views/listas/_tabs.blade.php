{{-- Tabs Clientes / Servicios (sin recarga) --}}
@php
    $listaTab = $listaTab ?? 'clientes';
    $puedeVerClientes = $puedeVerClientes ?? (auth()->user()?->tienePermiso('clientes-lista.ver') ?? false);
    $puedeVerServicios = $puedeVerServicios ?? (auth()->user()?->tienePermiso('servicios-lista.ver') ?? false);
    $tabs = [];
    if ($puedeVerClientes) {
        $tabs['clientes'] = 'Clientes';
    }
    if ($puedeVerServicios) {
        $tabs['servicios'] = 'Servicios';
    }
@endphp
@if (count($tabs) > 1)
    <div class="mt-4 flex flex-wrap items-center gap-2" role="tablist" aria-label="Clientes y servicios">
        @foreach ($tabs as $key => $label)
            @php $active = $listaTab === $key; @endphp
            <button type="button"
                    data-lista-tab="{{ $key }}"
                    role="tab"
                    aria-selected="{{ $active ? 'true' : 'false' }}"
                    class="lista-tab-btn inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition
                           {{ $active
                               ? 'bg-purple-600 text-white shadow-sm'
                               : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
@endif
