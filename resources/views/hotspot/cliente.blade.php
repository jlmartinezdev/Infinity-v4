@extends('layouts.app')

@section('title', 'Hotspot — '.$cliente->nombre.' '.$cliente->apellido)

@php
    $estados = \App\Models\Servicio::estadosDisponibles();
    $maxSlots = \App\Models\ServicioHotspot::MAX_POR_CLIENTE;
    $puedeEditar = (bool) auth()->user()?->tienePermiso('servicios.editar');
    $nombre = trim($cliente->nombre.' '.$cliente->apellido);
    $usernamesPorSlot = $usernamesPorSlot ?? [];
    $slotInicial = (int) old('slot_numero', request('slot', $slotsLibres[0] ?? 0));
    if (! in_array($slotInicial, array_map('intval', $slotsLibres), true)) {
        $slotInicial = (int) ($slotsLibres[0] ?? 0);
    }
    $usernamePreview = $usernamesPorSlot[$slotInicial] ?? \App\Models\ServicioHotspot::usernameDesdeDocumento($cliente, $slotInicial ?: 1);
    $estadoBadge = [
        'A' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
        'S' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'C' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'X' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
        'P' => 'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
    ];
    $fieldClass = 'hotspot-field w-full px-3 py-2.5 min-h-11 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm';
@endphp

@push('styles')
<style>
    .hotspot-pin-btn,
    .hotspot-add,
    .hotspot-quitar,
    #hotspot-asociar,
    .hotspot-sync-btn,
    .hotspot-perfil-guardar {
        min-height: 44px;
    }
    .hotspot-pin-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 44px;
    }
    .hotspot-sync-btn {
        background-color: #e5e7eb;
        color: #111827;
    }
    .hotspot-sync-btn:hover {
        background-color: #1f2937;
        color: #f3f4f6;
    }
    html.dark .hotspot-sync-btn {
        background-color: #4b5563;
        color: #f3f4f6;
    }
    html.dark .hotspot-sync-btn:hover {
        background-color: #6b7280;
    }
    .hotspot-field:focus,
    .hotspot-field:focus-visible {
        outline: none;
        border-color: #a855f7;
        box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.2);
    }
    .hotspot-free-slot.is-selected {
        background-color: rgba(88, 28, 135, 0.2);
        border-color: transparent;
        border-style: solid;
        color: #7e22ce;
    }
    html.dark .hotspot-free-slot.is-selected {
        color: #c084fc;
    }
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('hotspot.index') }}" class="inline-flex items-center min-h-11 text-sm text-gray-600 dark:text-gray-300 underline underline-offset-2 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20 rounded-lg">&larr; Volver a usuarios hotspot</a>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2">{{ $nombre }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {{ $cliente->cedula }}
            · {{ $cliente->servicioHotspots->count() }} / {{ $maxSlots }} usuarios
            · <a href="{{ route('clientes.detalle', $cliente) }}" class="text-gray-600 dark:text-gray-300 underline underline-offset-2 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20 rounded">Ficha del cliente</a>
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Usuarios hotspot</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">La autenticación va a RADIUS. El usuario es el documento (−2 y −3 en el 2.º y 3.er). El PIN es de {{ \App\Models\ServicioHotspot::PIN_DIGITOS }} dígitos: se genera al asociar o lo cargás a mano, y después se puede cambiar.</p>

        @php $porSlot = $cliente->servicioHotspots->keyBy(fn ($h) => (int) $h->slot_numero); @endphp
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
            @for($i = 1; $i <= $maxSlots; $i++)
                @php $sh = $porSlot->get($i); @endphp
                @if($sh)
                    @php
                        $codigo = $sh->servicio?->estado;
                        $estadoLabel = $codigo ? ($estados[$codigo] ?? $codigo) : 'Sin servicio';
                    @endphp
                    <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-3 min-h-11">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Slot {{ $i }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100 font-mono break-all">{{ $sh->username }}</p>
                        <div class="mt-2 flex items-center justify-between gap-1 min-w-0">
                            <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium {{ $estadoBadge[$codigo] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $estadoLabel }}</span>
                            <span class="hotspot-pin inline-flex items-center min-w-0">
                                <span class="hotspot-pin-value font-mono text-sm text-gray-900 dark:text-gray-100" data-pin="{{ $sh->password }}">{{ \App\Models\ServicioHotspot::pinOculto($sh->password) }}</span>
                                <button type="button" class="hotspot-pin-btn rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20" title="Mostrar PIN" aria-label="Mostrar PIN de {{ $sh->username }}" aria-pressed="false">
                                    <svg class="hotspot-pin-icon-show w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg class="hotspot-pin-icon-hide w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" hidden><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </span>
                        </div>
                        @include('hotspot._perfil-form', ['sh' => $sh])
                    </div>
                @else
                    <div class="hotspot-free-slot rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-3 min-h-11 flex flex-col{{ $slotInicial === $i ? ' is-selected' : '' }}" data-slot="{{ $i }}" aria-current="{{ $slotInicial === $i ? 'true' : 'false' }}">
                        <p class="text-xs text-gray-400">Slot {{ $i }}</p>
                        @if($puedeEditar && $cliente->servicios->isNotEmpty() && $usernamePreview)
                            <div class="flex-1 flex items-center justify-center">
                                <a href="{{ route('hotspot.clientes.edit', ['cliente' => $cliente, 'slot' => $i]) }}#form-hotspot"
                                    data-slot="{{ $i }}"
                                    class="hotspot-add inline-flex items-center justify-center w-11 h-11 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20"
                                    title="Crear usuario en el slot {{ $i }}"
                                    aria-label="Crear usuario en el slot {{ $i }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                                </a>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Libre</p>
                        @endif
                    </div>
                @endif
            @endfor
        </div>

        @if($cliente->servicioHotspots->count() < $maxSlots && $puedeEditar)
            @if($cliente->servicios->isEmpty())
                <p class="mb-6 text-sm text-amber-700 dark:text-amber-300">Este cliente no tiene servicios. Creá un servicio antes de asociar hotspot.</p>
            @elseif(! $usernamePreview)
                <p class="mb-6 text-sm text-amber-700 dark:text-amber-300">Este cliente no tiene número de documento. Cargá la cédula antes de crear el usuario.</p>
            @else
                <form id="form-hotspot" action="{{ route('hotspot.clientes.store', $cliente) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                    @csrf
                    <div>
                        <label for="servicio_id" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Servicio *</label>
                        <select name="servicio_id" id="servicio_id" required class="{{ $fieldClass }}">
                            <option value="">Seleccionar…</option>
                            @foreach($cliente->servicios as $srv)
                                <option value="{{ $srv->servicio_id }}" @selected((int) old('servicio_id') === (int) $srv->servicio_id)>
                                    #{{ $srv->servicio_id }} {{ $srv->plan?->nombre }} ({{ $estados[$srv->estado] ?? $srv->estado }})
                                </option>
                            @endforeach
                        </select>
                        @error('servicio_id')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="slot_numero" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Slot *</label>
                        <select name="slot_numero" id="slot_numero" required class="{{ $fieldClass }}">
                            @foreach($slotsLibres as $slot)
                                <option value="{{ $slot }}" data-username="{{ $usernamesPorSlot[$slot] ?? '' }}" @selected($slotInicial === (int) $slot)>Slot {{ $slot }}</option>
                            @endforeach
                        </select>
                        @error('slot_numero')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="hotspot_perfil_id" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Perfil</label>
                        <select name="hotspot_perfil_id" id="hotspot_perfil_id" class="{{ $fieldClass }}">
                            <option value="">default</option>
                            @foreach($perfiles as $p)
                                <option value="{{ $p->hotspot_perfil_id }}" @selected((int) old('hotspot_perfil_id') === (int) $p->hotspot_perfil_id)>{{ $p->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Usuario</p>
                        <p id="username-preview" class="px-3 py-2.5 min-h-11 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 text-gray-900 dark:text-gray-100 text-sm font-mono">{{ $usernamePreview }}</p>
                        @error('username')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">PIN</label>
                        <input type="text" name="password" id="password" data-hotspot-pin
                            value="{{ old('password') }}"
                            inputmode="numeric"
                            maxlength="{{ \App\Models\ServicioHotspot::PIN_DIGITOS }}"
                            pattern="[0-9]{{ '{'.\App\Models\ServicioHotspot::PIN_DIGITOS.'}' }}"
                            autocomplete="off"
                            placeholder="{{ \App\Models\ServicioHotspot::PIN_DIGITOS }} dígitos · si vacío, se genera"
                            class="{{ $fieldClass }} font-mono">
                        @error('password')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" id="hotspot-asociar" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-800 disabled:opacity-60">Asociar</button>
                    </div>
                </form>
            @endif
        @elseif($cliente->servicioHotspots->count() >= $maxSlots)
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Los {{ $maxSlots }} slots están ocupados.</p>
        @endif

        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($cliente->servicioHotspots as $sh)
                <li class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 dark:text-gray-100 font-mono break-all">{{ $sh->username }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Slot {{ $sh->slot_numero }}
                            · Servicio #{{ $sh->servicio_id }} ({{ $estados[$sh->servicio?->estado] ?? ($sh->servicio?->estado ?: '—') }})
                            @if($sh->hotspotPerfil)
                                · {{ $sh->hotspotPerfil->etiqueta() }}
                            @endif
                        </p>
                        <span class="hotspot-pin mt-1 inline-flex items-center">
                            <span class="text-sm text-gray-500 dark:text-gray-400 mr-1">PIN</span>
                            <span class="hotspot-pin-value font-mono text-sm text-gray-900 dark:text-gray-100" data-pin="{{ $sh->password }}">{{ \App\Models\ServicioHotspot::pinOculto($sh->password) }}</span>
                            <button type="button" class="hotspot-pin-btn rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-700 dark:hover:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20" title="Mostrar PIN" aria-label="Mostrar PIN de {{ $sh->username }}" aria-pressed="false">
                                <svg class="hotspot-pin-icon-show w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg class="hotspot-pin-icon-hide w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" hidden><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <form action="{{ route('hotspot.sync', $sh) }}" method="POST" class="hotspot-sync-form">
                            @csrf
                            <input type="hidden" name="from_cliente" value="1">
                            <button type="submit" class="hotspot-sync-btn px-4 py-2.5 rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20 disabled:opacity-60">Sincronizar</button>
                        </form>
                        @if($puedeEditar)
                            <form action="{{ route('hotspot.clientes.destroy', [$cliente, $sh]) }}" method="POST" onsubmit="return confirm({{ json_encode('¿Vaciar el slot '.$sh->slot_numero.' y quitar a '.$sh->username.'?') }});">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="hotspot-quitar inline-flex items-center justify-center px-3 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400/20" aria-label="Vaciar slot {{ $sh->slot_numero }}, usuario {{ $sh->username }}">Quitar</button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">Ningún slot ocupado.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var PIN_HIDE_MS = 8000;
    var pinTimer = null;

    function pinMask(pin) {
        var n = pin && pin.length ? pin.length : {{ \App\Models\ServicioHotspot::PIN_DIGITOS }};
        return '•'.repeat(n);
    }

    document.querySelectorAll('[data-hotspot-pin]').forEach(function(el) {
        el.addEventListener('input', function() {
            el.value = el.value.replace(/\D/g, '').slice(0, {{ \App\Models\ServicioHotspot::PIN_DIGITOS }});
        });
    });
    var select = document.getElementById('slot_numero');
    var preview = document.getElementById('username-preview');
    var asociar = document.getElementById('hotspot-asociar');

    function setPinVisible(btn, on) {
        var wrap = btn.closest('.hotspot-pin');
        if (!wrap) return;
        var value = wrap.querySelector('.hotspot-pin-value');
        var showIcon = wrap.querySelector('.hotspot-pin-icon-show');
        var hideIcon = wrap.querySelector('.hotspot-pin-icon-hide');
        var pin = value ? (value.getAttribute('data-pin') || '') : '';
        var user = btn.getAttribute('data-user') || '';
        var verb = on ? 'Ocultar PIN' : 'Mostrar PIN';
        if (value) value.textContent = on && pin ? pin : pinMask(pin);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.setAttribute('aria-label', user ? verb + ' de ' + user : verb);
        btn.setAttribute('title', verb);
        if (showIcon) showIcon.hidden = !!on;
        if (hideIcon) hideIcon.hidden = !on;
    }

    function hideAllPins(exceptBtn) {
        document.querySelectorAll('.hotspot-pin-btn').forEach(function(btn) {
            if (btn !== exceptBtn) setPinVisible(btn, false);
        });
        if (pinTimer) {
            clearTimeout(pinTimer);
            pinTimer = null;
        }
    }

    document.querySelectorAll('.hotspot-pin-btn').forEach(function(btn) {
        var label = btn.getAttribute('aria-label') || '';
        var match = label.match(/de (.+)$/);
        if (match) btn.setAttribute('data-user', match[1]);
        btn.addEventListener('click', function() {
            var show = btn.getAttribute('aria-pressed') !== 'true';
            hideAllPins(show ? btn : null);
            setPinVisible(btn, show);
            if (show) {
                pinTimer = setTimeout(function() { setPinVisible(btn, false); }, PIN_HIDE_MS);
            }
        });
    });

    function markSlot(slot) {
        document.querySelectorAll('.hotspot-free-slot').forEach(function(el) {
            var on = String(el.getAttribute('data-slot')) === String(slot);
            el.classList.toggle('is-selected', on);
            el.setAttribute('aria-current', on ? 'true' : 'false');
        });
    }

    function syncPreview() {
        if (!select || !preview) return;
        var opt = select.options[select.selectedIndex];
        preview.textContent = opt && opt.dataset.username ? opt.dataset.username : '—';
        markSlot(select.value);
    }

    if (select) {
        select.addEventListener('change', syncPreview);
    }

    document.querySelectorAll('.hotspot-add').forEach(function(a) {
        a.addEventListener('click', function(e) {
            if (!select) return;
            var slot = a.getAttribute('data-slot');
            if (!slot) return;
            e.preventDefault();
            select.value = slot;
            syncPreview();
            var form = document.getElementById('form-hotspot');
            if (form) form.scrollIntoView({ block: 'nearest' });
            select.focus();
        });
    });

    function lockSubmit(btn, label) {
        if (!btn) return;
        btn.setAttribute('aria-busy', 'true');
        window.setTimeout(function() {
            btn.disabled = true;
            btn.textContent = label;
        }, 0);
    }

    if (asociar) {
        asociar.closest('form').addEventListener('submit', function() {
            lockSubmit(asociar, 'Asociando…');
        });
    }
    document.querySelectorAll('.hotspot-sync-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            lockSubmit(form.querySelector('.hotspot-sync-btn'), 'Sincronizando…');
        });
    });
    document.querySelectorAll('.hotspot-perfil-form').forEach(function(form) {
        form.addEventListener('submit', function() {
            lockSubmit(form.querySelector('.hotspot-perfil-guardar'), 'Guardando…');
        });
    });

    var flash = document.querySelector('[data-app-flash]');
    if (flash) {
        flash.setAttribute('role', 'status');
        flash.scrollIntoView({ block: 'nearest' });
    }
})();
</script>
@endpush
