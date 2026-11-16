@php
    $tab = $tab ?? 'administrador';
    $paneles = [
        'administrador' => 'Administrador',
        'tecnico' => 'Técnico',
        'cajero' => 'Cajero',
    ];
@endphp
<nav class="flex flex-wrap gap-2" role="tablist" aria-label="Paneles del inicio">
    @foreach($paneles as $clave => $etiqueta)
        @php $activo = $tab === $clave; @endphp
        <a href="{{ route('home', ['tab' => $clave]) }}"
           role="tab"
           id="tab-{{ $clave }}"
           aria-selected="{{ $activo ? 'true' : 'false' }}"
           class="inline-flex items-center rounded-full px-4 py-1.5 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500
               {{ $activo
                   ? 'bg-violet-600 text-white shadow-sm'
                   : 'border border-gray-200/80 dark:border-white/10 bg-white dark:bg-[#12161f] text-gray-600 dark:text-gray-300 hover:border-violet-400/60 dark:hover:border-violet-500/40' }}">
            {{ $etiqueta }}
        </a>
    @endforeach
</nav>
