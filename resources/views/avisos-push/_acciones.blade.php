{{-- Acciones historial avisos push: reenviar rápido + menú ⋮
     Vars: $aviso, $puedeEditar --}}
@php
    $menuItems = [];

    if ($puedeEditar) {
        $menuItems[] = [
            'type' => 'form',
            'action' => route('avisos-push.reenviar', $aviso),
            'method' => 'POST',
            'label' => 'Reenviar',
            'confirm' => '¿Reenviar el aviso #'.$aviso->id.' a los mismos destinatarios?',
            'color' => 'blue',
            'icon' => 'refresh',
        ];
        $menuItems[] = [
            'type' => 'form',
            'action' => route('avisos-push.destroy', $aviso),
            'method' => 'POST',
            'method_spoof' => 'DELETE',
            'label' => 'Eliminar del historial',
            'confirm' => '¿Eliminar el aviso #'.$aviso->id.' del historial?',
            'color' => 'red',
            'icon' => 'trash',
        ];
    }

    $menuPayload = [
        'csrf' => csrf_token(),
        'items' => $menuItems,
    ];
@endphp

<div class="flex items-center justify-end gap-0.5">
    @if($puedeEditar)
        <form action="{{ route('avisos-push.reenviar', $aviso) }}" method="POST" class="inline"
              onsubmit="return confirm('¿Reenviar el aviso #{{ $aviso->id }}?');">
            @csrf
            <button type="submit"
                    class="p-2 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
                    title="Reenviar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </form>
    @endif

    @if(count($menuItems) > 0)
        <button type="button"
                class="push-acciones-kebab p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                title="Más acciones"
                aria-expanded="false"
                data-menu-b64="{{ base64_encode(json_encode($menuPayload, JSON_UNESCAPED_UNICODE)) }}">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
        </button>
    @endif
</div>
