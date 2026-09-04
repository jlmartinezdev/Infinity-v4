@extends('layouts.app')

@section('title', 'Cambiar tecnología')

@php
    $tecnologiaActualNombre = $servicio->plan?->tipoTecnologia?->descripcion ?? '—';
    $nodoActualNombre = $nodoActual?->descripcion ?? '—';
    $urlVolver = route('clientes.detalle', ['cliente' => $servicio->cliente_id, 'tab' => 'servicio']);
    $kindActualLabel = match ($kindActual ?? '') {
        'gpon' => 'Fibra',
        'wireless' => 'Antena',
        default => $tecnologiaActualNombre,
    };
@endphp

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Cambiar tecnología</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Servicio #{{ $servicio->servicio_id }} — {{ $servicio->cliente->nombre ?? '' }} {{ $servicio->cliente->apellido ?? '' }}
    </p>

    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-6">
        <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Situación actual</p>
        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
            Tecnología: <strong>{{ $kindActualLabel }}</strong>
            ({{ $tecnologiaActualNombre }}) —
            Plan: {{ $servicio->plan?->nombre ?? '—' }}
            ({{ number_format((float) ($servicio->plan->precio ?? 0), 0, ',', '.') }} Gs.) —
            Nodo: {{ $nodoActualNombre }} —
            Pool: {{ $servicio->pool?->ip_range ?? $servicio->pool?->descripcion ?? '—' }} —
            IP: {{ $servicio->ip ?? '—' }}
        </p>
        <p class="text-xs text-amber-700/90 dark:text-amber-300/90 mt-2">
            El plan sugerido es el de la tecnología nueva con el <strong>precio más cercano</strong>. Podés elegir otro. El equipo en casa se actualiza después en Editar.
        </p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('servicios.cambiar-tecnologia-store', $servicio->servicio_id) }}" method="POST" id="form-cambiar-tecnologia">
            @csrf
            <input type="hidden" name="tecnologia_id" id="tecnologia_id" value="{{ old('tecnologia_id', $tecnologiaDestinoDefault?->tecnologia_id) }}" required>
            <input type="hidden" name="generar_factura_prorrateo_cambio_plan" id="generar_factura_prorrateo_cambio_plan" value="1">

            <div class="space-y-6">
                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tecnología destino *</span>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Tecnología destino">
                        @foreach($tecnologias as $t)
                            @php
                                $kind = $tecnologiaKinds[(string) $t->tecnologia_id] ?? 'otro';
                                $esActual = (string) $tecnologiaActualId === (string) $t->tecnologia_id;
                            @endphp
                            <button type="button"
                                class="cambio-tec-card relative h-14 w-28 rounded-lg border p-2 cursor-pointer flex flex-col items-center justify-center bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600 {{ $esActual ? 'opacity-40 cursor-not-allowed' : '' }}"
                                data-tecnologia-id="{{ $t->tecnologia_id }}"
                                data-kind="{{ $kind }}"
                                @disabled($esActual)
                                aria-pressed="false">
                                @if($kind === 'gpon')
                                    <svg class="w-5 h-5 shrink-0 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h4l2-6 4 12 2-6h4"/></svg>
                                @elseif($kind === 'wireless')
                                    <svg class="w-5 h-5 shrink-0 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M5.05 13.05a10 10 0 0113.9 0M1.394 9.394c6.258-6.258 16.4-6.258 22.658 0"/></svg>
                                @endif
                                <span class="mt-1 block text-xs font-semibold leading-tight text-gray-800 dark:text-gray-100">{{ $t->descripcion }}</span>
                                @if($esActual)
                                    <span class="text-[10px] text-gray-500">actual</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                    @error('tecnologia_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="plan_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan destino *</label>
                    <select name="plan_id" id="plan_id" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Seleccione un plan</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se preselecciona el plan con precio más cercano al actual.</p>
                    @error('plan_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nodo *</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" id="nodo-modo-wrap">
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-600 p-3 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-purple-500 has-[:checked]:border-purple-500">
                            <input type="radio" name="mantener_nodo" value="1" class="mt-1" id="mantener_nodo_si"
                                {{ old('mantener_nodo', '1') === '1' ? 'checked' : '' }}>
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-100">Mantener nodo</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $nodoActualNombre }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-600 p-3 cursor-pointer has-[:checked]:ring-2 has-[:checked]:ring-purple-500 has-[:checked]:border-purple-500">
                            <input type="radio" name="mantener_nodo" value="0" class="mt-1" id="mantener_nodo_no"
                                {{ old('mantener_nodo') === '0' ? 'checked' : '' }}>
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-gray-100">Elegir otro nodo</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">Cambia pool, IP y router</span>
                            </span>
                        </label>
                    </div>
                    <p id="nodo-keep-hint" class="mt-1 text-xs text-amber-600 dark:text-amber-300 hidden"></p>
                    @error('mantener_nodo')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div id="nodo-destino-wrap" class="hidden">
                    <label for="nodo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nodo destino *</label>
                    <select name="nodo_id" id="nodo_id"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Seleccione un nodo</option>
                    </select>
                    @error('nodo_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="pool_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pool *</label>
                    <select name="pool_id" id="pool_id" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Seleccione un pool</option>
                    </select>
                    @error('pool_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IP *</label>
                    <select name="ip" id="ip" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">Primero seleccione un pool</option>
                    </select>
                    @error('ip')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Confirmar cambio
                </button>
                <a href="{{ $urlVolver }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-500 focus:outline-none">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script type="application/json" id="cambio-tec-config">@json($config)</script>
<script>
(function () {
    var cfgEl = document.getElementById('cambio-tec-config');
    var cfg = {};
    try { cfg = JSON.parse(cfgEl.textContent || '{}'); } catch (e) { cfg = {}; }

    var form = document.getElementById('form-cambiar-tecnologia');
    var techHidden = document.getElementById('tecnologia_id');
    var planSel = document.getElementById('plan_id');
    var poolSel = document.getElementById('pool_id');
    var ipSel = document.getElementById('ip');
    var nodoSel = document.getElementById('nodo_id');
    var nodoWrap = document.getElementById('nodo-destino-wrap');
    var keepHint = document.getElementById('nodo-keep-hint');
    var keepSi = document.getElementById('mantener_nodo_si');
    var keepNo = document.getElementById('mantener_nodo_no');
    var keepSiLabel = keepSi ? keepSi.closest('label') : null;
    var old = cfg.old || {};

    function formatGs(n) {
        return Math.round(Number(n) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function kindOf(techId) {
        return (cfg.tecnologiaKinds && cfg.tecnologiaKinds[String(techId)]) || 'otro';
    }

    function poolMatchKind(pool, kind) {
        if (kind === 'gpon') return !!pool.gpon;
        if (kind === 'wireless') return !!pool.wireless;
        return true;
    }

    function nodoMatchKind(nodo, kind) {
        if (kind === 'gpon') return !!nodo.gpon;
        if (kind === 'wireless') return !!nodo.wireless;
        return true;
    }

    function nodoActualSoporta(kind) {
        if (!cfg.nodoActualId) return false;
        if (kind === 'gpon') return !!cfg.nodoActualGpon;
        if (kind === 'wireless') return !!cfg.nodoActualWireless;
        return true;
    }

    function equivalentePlan(planes, precio) {
        if (!planes.length) return null;
        return planes.slice().sort(function (a, b) {
            var da = Math.abs((a.precio || 0) - precio);
            var db = Math.abs((b.precio || 0) - precio);
            if (da !== db) return da - db;
            if ((a.precio || 0) !== (b.precio || 0)) return (a.precio || 0) - (b.precio || 0);
            return (a.plan_id || 0) - (b.plan_id || 0);
        })[0];
    }

    function setCardState(techId) {
        document.querySelectorAll('.cambio-tec-card').forEach(function (card) {
            var id = String(card.getAttribute('data-tecnologia-id') || '');
            var on = id === String(techId || '') && !card.disabled;
            card.classList.toggle('ring-2', on);
            card.classList.toggle('ring-purple-500', on);
            card.classList.toggle('border-purple-500', on);
            card.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function fillSelect(sel, items, placeholder, selected) {
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        items.forEach(function (it) {
            var o = document.createElement('option');
            o.value = it.value;
            o.textContent = it.label;
            if (it.precio != null) o.setAttribute('data-precio', String(it.precio));
            if (String(it.value) === String(selected || '')) o.selected = true;
            sel.appendChild(o);
        });
    }

    function techIdActual() {
        return String(techHidden.value || '');
    }

    function mantenerNodo() {
        return keepSi && keepSi.checked;
    }

    function rebuildPlanes(preferId) {
        var techId = techIdActual();
        var planes = (cfg.planes || []).filter(function (p) {
            return String(p.tecnologia_id) === String(techId) && String(p.plan_id) !== String(cfg.planActualId);
        });
        var eq = equivalentePlan(planes, cfg.precioActual || 0);
        var selected = preferId || (eq && eq.plan_id);
        fillSelect(planSel, planes.map(function (p) {
            var extra = (eq && p.plan_id === eq.plan_id) ? ' · equivalente' : '';
            return {
                value: p.plan_id,
                precio: p.precio,
                label: (p.nombre || 'Plan') + ' — ' + (p.velocidad || '') + ' — ' + formatGs(p.precio) + ' Gs.' + extra
            };
        }), planes.length ? 'Seleccione un plan' : 'No hay planes para esta tecnología', selected);
        if (eq && !preferId) planSel.value = String(eq.plan_id);
    }

    function rebuildNodos(preferId) {
        var kind = kindOf(techIdActual());
        var nodos = (cfg.nodos || []).filter(function (n) {
            if (!nodoMatchKind(n, kind)) return false;
            if (cfg.nodoActualId && n.nodo_id === cfg.nodoActualId) return false;
            return true;
        });
        fillSelect(nodoSel, nodos.map(function (n) {
            return { value: n.nodo_id, label: n.descripcion || ('Nodo #' + n.nodo_id) };
        }), nodos.length ? 'Seleccione un nodo' : 'No hay nodos con esa tecnología', preferId);
    }

    function rebuildPools(preferId) {
        var kind = kindOf(techIdActual());
        var keep = mantenerNodo();
        var nodoId = keep ? cfg.nodoActualId : (nodoSel.value ? Number(nodoSel.value) : null);
        var pools = (cfg.pools || []).filter(function (p) {
            if (!poolMatchKind(p, kind)) return false;
            if (!nodoId) return false;
            return p.nodo_id === nodoId;
        });
        var selected = preferId;
        if (!selected && keep && cfg.poolActualId && pools.some(function (p) { return p.pool_id === cfg.poolActualId; })) {
            selected = cfg.poolActualId;
        }
        fillSelect(poolSel, pools.map(function (p) {
            return { value: p.pool_id, label: p.label };
        }), pools.length ? 'Seleccione un pool' : 'No hay pools en ese nodo para la tecnología', selected);
        cargarIps();
    }

    function cargarIps() {
        var poolId = poolSel.value;
        var oldIp = old.ip || '';
        ipSel.disabled = true;
        if (!poolId) {
            ipSel.innerHTML = '<option value="">Primero seleccione un pool</option>';
            ipSel.disabled = false;
            return;
        }
        ipSel.innerHTML = '<option value="">Cargando...</option>';
        fetch(cfg.ipsUrl + '?pool_id=' + encodeURIComponent(poolId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ips = data.ips || [];
                var keepCurrent = String(poolId) === String(cfg.poolActualId || '') && cfg.ipActual;
                ipSel.innerHTML = '<option value="">Seleccione IP</option>';
                if (keepCurrent) {
                    var cur = document.createElement('option');
                    cur.value = cfg.ipActual;
                    cur.textContent = cfg.ipActual + ' (actual)';
                    ipSel.appendChild(cur);
                }
                ips.forEach(function (ip) {
                    if (keepCurrent && ip === cfg.ipActual) return;
                    var opt = document.createElement('option');
                    opt.value = ip;
                    opt.textContent = ip;
                    ipSel.appendChild(opt);
                });
                var want = oldIp || (keepCurrent ? cfg.ipActual : '');
                if (want) ipSel.value = want;
                ipSel.disabled = false;
            })
            .catch(function () {
                ipSel.innerHTML = '<option value="">Error al cargar IPs</option>';
                ipSel.disabled = false;
            });
    }

    function syncNodoModo() {
        var kind = kindOf(techIdActual());
        var puedeKeep = nodoActualSoporta(kind);
        if (keepSi) keepSi.disabled = !puedeKeep;
        if (keepSiLabel) keepSiLabel.classList.toggle('opacity-50', !puedeKeep);
        if (!puedeKeep) {
            if (keepNo) keepNo.checked = true;
            if (keepHint) {
                keepHint.textContent = cfg.nodoActualId
                    ? 'El nodo actual no tiene esa tecnología. Hay que elegir otro nodo.'
                    : 'Este servicio no tiene nodo. Hay que elegir uno.';
                keepHint.classList.remove('hidden');
            }
        } else if (keepHint) {
            keepHint.classList.add('hidden');
        }
        var showNodo = !mantenerNodo();
        nodoWrap.classList.toggle('hidden', !showNodo);
        nodoSel.required = showNodo;
    }

    function onTechChange(techId, fromOld) {
        techHidden.value = techId;
        setCardState(techId);
        rebuildPlanes(fromOld ? old.plan_id : null);
        syncNodoModo();
        rebuildNodos(fromOld ? old.nodo_id : null);
        rebuildPools(fromOld ? old.pool_id : null);
    }

    document.querySelectorAll('.cambio-tec-card').forEach(function (card) {
        card.addEventListener('click', function () {
            if (card.disabled) return;
            old.plan_id = null;
            old.pool_id = null;
            old.ip = null;
            onTechChange(card.getAttribute('data-tecnologia-id'), false);
        });
    });

    if (keepSi) keepSi.addEventListener('change', function () {
        syncNodoModo();
        rebuildPools(null);
    });
    if (keepNo) keepNo.addEventListener('change', function () {
        syncNodoModo();
        rebuildNodos(null);
        rebuildPools(null);
    });
    nodoSel.addEventListener('change', function () { rebuildPools(null); });
    poolSel.addEventListener('change', function () {
        old.ip = null;
        cargarIps();
    });

    var initialTech = old.tecnologia_id || cfg.tecnologiaDestinoDefaultId || techHidden.value;
    if (old.mantener_nodo === '0' && keepNo) keepNo.checked = true;
    if (old.mantener_nodo === '1' && keepSi) keepSi.checked = true;
    onTechChange(initialTech, true);

    if (form && typeof Swal !== 'undefined') {
        form.addEventListener('submit', function (e) {
            var opt = planSel.options[planSel.selectedIndex];
            if (!opt || !opt.value) return;
            var newPrecio = parseFloat(opt.getAttribute('data-precio') || '0') || 0;
            var oldPrecio = Number(cfg.precioActual || 0);
            if (Math.abs(newPrecio - oldPrecio) < 0.01) return;
            e.preventDefault();
            Swal.fire({
                icon: 'question',
                title: 'Cambio de plan con distinto precio',
                text: '¿Desea generar la factura interna prorrateada por el cambio de plan en lo que resta del mes?',
                showDenyButton: true,
                confirmButtonText: 'Sí, generar factura prorrateada',
                denyButtonText: 'No, guardar sin factura',
                reverseButtons: true
            }).then(function (r) {
                if (r.isDismissed) return;
                document.getElementById('generar_factura_prorrateo_cambio_plan').value = r.isConfirmed ? '1' : '0';
                HTMLFormElement.prototype.submit.call(form);
            });
        });
    }
})();
</script>
@endpush
