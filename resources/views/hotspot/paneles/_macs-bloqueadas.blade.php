{{-- MACs bloqueadas del router: se lee al entrar y después de bloquear o desbloquear, no en cada refresco. --}}
@php
    $macsBloqueadas = $macsBloqueadas ?? [];
    $puedeCrear = $puedeCrear ?? (bool) auth()->user()?->tienePermiso('servicios.editar');
    $card = $card ?? 'rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800';
    $routerError = $routerError ?? null;
@endphp
@if(! $routerError && count($macsBloqueadas) > 0)
    <div class="{{ $card }} px-4 py-3">
        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">MACs bloqueadas</p>
        <ul class="mt-2 flex flex-col gap-2">
            @foreach($macsBloqueadas as $macBloqueada)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm text-gray-800 dark:text-gray-100">{{ $macBloqueada }}</span>
                    @if($puedeCrear)
                        <form method="POST" action="{{ route('hotspot.dashboard.desbloquear', $selectedRouter) }}" class="inline" data-accion="desbloquear" data-confirmar="{{ '¿Desbloquear '.$macBloqueada.'?' }}">
                            @csrf
                            <input type="hidden" name="mac" value="{{ $macBloqueada }}">
                            <button type="submit" class="hsd-btn" aria-label="Desbloquear {{ $macBloqueada }}">Desbloquear</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
