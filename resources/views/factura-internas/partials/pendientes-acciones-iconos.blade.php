{{-- Factura interna $f --}}
@php
    $clientePend = $f->cliente;
    $payloadContacto = [
        'nombre' => $clientePend ? trim(($clientePend->nombre ?? '').' '.($clientePend->apellido ?? '')) : '',
        'cedula' => $clientePend?->cedula ?? '',
        'celular' => $clientePend?->telefono ?? '',
        'email' => $clientePend?->email ?? '',
        'direccion' => $clientePend?->direccion ?? '',
        'url_ubicacion' => $clientePend?->url_ubicacion ?? '',
        'detalle_url' => ($clientePend && auth()->user()?->tienePermiso('clientes.ver'))
            ? route('clientes.detalle', $clientePend)
            : '',
    ];
@endphp
<div class="inline-flex items-center gap-0.5">
    <button type="button"
        class="js-btn-detalle-contacto-cliente inline-flex items-center justify-center p-2 rounded-lg text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-900/30 transition-colors"
        title="Contacto, dirección y ubicación"
        aria-label="Ver contacto y ubicación del cliente"
        data-cliente='@json($payloadContacto)'>
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0Z" />
        </svg>
    </button>
    <a href="{{ route('factura-internas.show', $f) }}"
       class="inline-flex items-center justify-center p-2 rounded-lg text-purple-600 hover:bg-purple-50 dark:text-purple-400 dark:hover:bg-purple-900/30 transition-colors"
       title="Ver factura interna"
       aria-label="Ver factura interna">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
        </svg>
    </a>
    @if(auth()->user()?->tienePermiso('cobros.crear'))
        <a href="{{ route('promesas-pago.create', $f) }}"
           class="inline-flex items-center justify-center p-2 rounded-lg text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-900/30 transition-colors"
           title="Registrar promesa de pago"
           aria-label="Registrar promesa de pago">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
        </a>
        <a href="{{ route('cobros.create', ['cliente_id' => $f->cliente_id, 'factura_interna_id' => $f->id]) }}"
           class="inline-flex items-center justify-center p-2 rounded-lg text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/30 transition-colors"
           title="Registrar cobro"
           aria-label="Registrar cobro">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </a>
    @endif
</div>
