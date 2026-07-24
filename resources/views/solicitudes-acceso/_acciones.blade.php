{{-- Acciones estilo servicios/tickets: icono primario + menú ⋮
     Vars: $solicitud, $esAdmin, $puedeEditar, $usuarioPortal, $hideVer --}}
@php
    $hideVer = $hideVer ?? false;
    $usuarioPortal = $usuarioPortal ?? $solicitud->cliente?->usuarioPortal;
    $menuItems = [];

    if (! $hideVer) {
        $menuItems[] = [
            'type' => 'link',
            'href' => route('solicitudes-acceso.show', $solicitud),
            'label' => 'Ver detalle',
            'color' => 'slate',
            'icon' => 'eye',
        ];
    }

    if ($solicitud->estado === 'pendiente' && $puedeEditar) {
        $menuItems[] = [
            'type' => 'form',
            'action' => route('solicitudes-acceso.aprobar', $solicitud),
            'method' => 'POST',
            'label' => 'Aprobar y generar clave',
            'confirm' => '¿Aprobar #'.$solicitud->id.' y generar clave PLUS?',
            'color' => 'emerald',
            'icon' => 'check',
        ];
        $menuItems[] = [
            'type' => 'form',
            'action' => route('solicitudes-acceso.rechazar', $solicitud),
            'method' => 'POST',
            'label' => 'Rechazar',
            'confirm' => '¿Rechazar solicitud #'.$solicitud->id.'?',
            'color' => 'rose',
            'icon' => 'x',
        ];
    }

    if ($solicitud->estado === 'pendiente_verificacion' && $puedeEditar) {
        $menuItems[] = [
            'type' => 'form',
            'action' => route('solicitudes-acceso.rechazar', $solicitud),
            'method' => 'POST',
            'label' => 'Rechazar (sin verificar WA)',
            'confirm' => '¿Rechazar solicitud #'.$solicitud->id.'? El cliente aún no verificó WhatsApp.',
            'color' => 'rose',
            'icon' => 'x',
        ];
    }

    if ($solicitud->estado === 'aprobada' && $puedeEditar) {
        $menuItems[] = [
            'type' => 'form',
            'action' => route('solicitudes-acceso.reenviar-clave', $solicitud),
            'method' => 'POST',
            'label' => 'Regenerar clave (WhatsApp)',
            'confirm' => '¿Regenerar clave PLUS y enviar por WhatsApp?',
            'color' => 'blue',
            'icon' => 'key',
        ];
    }

    if ($esAdmin && $solicitud->estado === 'aprobada' && $usuarioPortal) {
        if ($usuarioPortal->estado === 'activo') {
            $menuItems[] = [
                'type' => 'form',
                'action' => route('solicitudes-acceso.suspender-acceso', $solicitud),
                'method' => 'POST',
                'label' => 'Dar de baja acceso',
                'confirm' => '¿Dar de baja el acceso app?',
                'color' => 'amber',
                'icon' => 'pause',
            ];
        } else {
            $menuItems[] = [
                'type' => 'form',
                'action' => route('solicitudes-acceso.reactivar-acceso', $solicitud),
                'method' => 'POST',
                'label' => 'Reactivar acceso',
                'confirm' => '¿Reactivar acceso app?',
                'color' => 'emerald',
                'icon' => 'play',
            ];
        }
        $menuItems[] = [
            'type' => 'form',
            'action' => route('solicitudes-acceso.eliminar-acceso', $solicitud),
            'method' => 'POST',
            'method_spoof' => 'DELETE',
            'label' => 'Quitar usuario portal',
            'confirm' => '¿Eliminar usuario portal? El cliente ISP queda.',
            'color' => 'orange',
            'icon' => 'user-x',
        ];
    }

    if ($esAdmin) {
        $menuItems[] = [
            'type' => 'form',
            'action' => route('solicitudes-acceso.destroy', $solicitud),
            'method' => 'POST',
            'method_spoof' => 'DELETE',
            'label' => 'Eliminar solicitud',
            'confirm' => '¿Eliminar la solicitud #'.$solicitud->id.'?',
            'color' => 'red',
            'icon' => 'trash',
        ];
    }

    $menuPayload = [
        'csrf' => csrf_token(),
        'items' => $menuItems,
    ];
@endphp

<div class="flex items-center justify-end gap-0.5 acciones-sol-row">
    {{-- Acciones rápidas (icono) según estado --}}
    @if($solicitud->estado === 'pendiente' && $puedeEditar)
        <form action="{{ route('solicitudes-acceso.aprobar', $solicitud) }}" method="POST" class="inline"
              onsubmit="return confirm('¿Aprobar #{{ $solicitud->id }} y generar clave PLUS?');">
            @csrf
            <button type="submit"
                    class="p-2 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                    title="Aprobar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </button>
        </form>
    @elseif($esAdmin && $solicitud->estado === 'aprobada' && $usuarioPortal)
        @if($usuarioPortal->estado === 'activo')
            <form action="{{ route('solicitudes-acceso.suspender-acceso', $solicitud) }}" method="POST" class="inline"
                  onsubmit="return confirm('¿Dar de baja el acceso app?');">
                @csrf
                <button type="submit"
                        class="p-2 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-colors"
                        title="Dar de baja acceso">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </form>
        @else
            <form action="{{ route('solicitudes-acceso.reactivar-acceso', $solicitud) }}" method="POST" class="inline"
                  onsubmit="return confirm('¿Reactivar acceso app?');">
                @csrf
                <button type="submit"
                        class="p-2 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                        title="Reactivar acceso">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </form>
        @endif
    @endif

    @if(count($menuItems) > 0)
        <button type="button"
                class="sol-acciones-kebab p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                title="Más acciones"
                aria-expanded="false"
                data-menu-b64="{{ base64_encode(json_encode($menuPayload, JSON_UNESCAPED_UNICODE)) }}">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
        </button>
    @endif
</div>
