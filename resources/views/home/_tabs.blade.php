@php
    $tab = $tab ?? 'administrador';
    $paneles = [
        'administrador' => 'Administrador',
        'tecnico' => 'Técnico',
        'cajero' => 'Cajero',
    ];
@endphp
<nav class="inline-flex rounded-lg bg-gray-100 dark:bg-white/5 p-0.5" role="tablist" aria-label="Paneles del inicio">
    @foreach($paneles as $clave => $etiqueta)
        @php $activo = $tab === $clave; @endphp
        <a href="{{ route('home', ['tab' => $clave]) }}"
           role="tab"
           id="tab-{{ $clave }}"
           aria-selected="{{ $activo ? 'true' : 'false' }}"
           class="inline-flex items-center rounded-md px-3 py-1 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500
               {{ $activo
                   ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                   : 'text-gray-500 dark:text-gray-400' }}">
            {{ $etiqueta }}
        </a>
    @endforeach
</nav>
