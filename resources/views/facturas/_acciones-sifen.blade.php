@php
    $estilo = $estilo ?? 'links';
    $claseBoton = $claseBoton ?? 'inline-flex items-center justify-center gap-2 h-9 px-3 rounded-lg text-sm font-medium whitespace-nowrap';
    $iconoAccion = $iconoAccion ?? 'w-4 h-4 shrink-0';
    $puedeCancelar = $factura->puedeCancelarPorEvento();
    $puedeNc = $factura->puedePrepararNotaCredito();
@endphp
@if(auth()->user()?->esAdministrador())
    @if($puedeCancelar || $puedeNc)
        @if($estilo === 'botones')
            @if($puedeCancelar)
                <button type="button"
                    class="js-sifen-cancelar {{ $claseBoton }} bg-red-600 text-white hover:bg-red-700"
                    title="Cancelar en SIFEN"
                    data-url="{{ route('facturas.cancelar', $factura) }}"
                    data-numero="{{ $factura->numero_completo ?? '#'.$factura->id }}"
                    data-limite="{{ $factura->fechaLimiteCancelacionEvento()?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}">
                    <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Cancelar
                </button>
            @endif
            @if($puedeNc)
                <button type="button"
                    class="js-sifen-nc {{ $claseBoton }} bg-sky-600 text-white hover:bg-sky-700"
                    title="Preparar nota de crédito"
                    data-url="{{ route('facturas.nota-credito', $factura) }}"
                    data-numero="{{ $factura->numero_completo ?? '#'.$factura->id }}">
                    <svg class="{{ $iconoAccion }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    N. crédito
                </button>
            @endif
        @else
            @if($puedeCancelar)
                <button type="button"
                    class="js-sifen-cancelar ml-2 text-red-600 dark:text-red-400 hover:underline text-sm"
                    data-url="{{ route('facturas.cancelar', $factura) }}"
                    data-numero="{{ $factura->numero_completo ?? '#'.$factura->id }}"
                    data-limite="{{ $factura->fechaLimiteCancelacionEvento()?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}">
                    Cancelar
                </button>
            @endif
            @if($puedeNc)
                <button type="button"
                    class="js-sifen-nc ml-2 text-sky-600 dark:text-sky-400 hover:underline text-sm"
                    data-url="{{ route('facturas.nota-credito', $factura) }}"
                    data-numero="{{ $factura->numero_completo ?? '#'.$factura->id }}">
                    N. crédito
                </button>
            @endif
        @endif
    @endif
@endif
