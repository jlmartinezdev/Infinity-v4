@extends('layouts.app')

@section('title', 'Avisos push')

@section('content')
@php
    $oldIds = collect(old('cliente_ids', []))->map(fn ($id) => (int) $id)->filter()->values();
@endphp
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Avisos push</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Enviá un aviso a clientes puntuales o a un grupo (saldo pendiente, vencidas, por vencer, suspendidos o cortados).
            El título y el mensaje se personalizan con variables.
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $conPush }}</span> con app / push activo.
        </p>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form id="form-aviso-push" method="POST" action="{{ route('avisos-push.store') }}" class="space-y-4 p-4 sm:p-5">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Título</label>
                    <input type="text" name="titulo" id="aviso-titulo" value="{{ old('titulo') }}" maxlength="120" required
                           placeholder="Ej. Recordatorio de pago"
                           class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
                    @error('titulo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mensaje</label>
                    <textarea id="aviso-cuerpo" name="cuerpo" rows="3" maxlength="500" required
                              placeholder="Ej. Hola [nombre], tu saldo pendiente es Gs. [saldo_pend]. Vence el [vencimiento]."
                              class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">{{ old('cuerpo') }}</textarea>
                    <p class="mt-1 text-xs text-gray-400"><span id="aviso-cuerpo-len">0</span>/500</p>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="text-xs text-gray-400">Variables</span>
                        @foreach($variables as $token => $ayuda)
                            <button type="button"
                                    class="js-var rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-blue-50 hover:text-blue-800 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-600 dark:hover:bg-blue-950/40"
                                    data-token="[{{ $token }}]"
                                    title="{{ $ayuda }}">[{{ $token }}]</button>
                        @endforeach
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Se reemplazan en cada cliente. Hacé clic en el título o en el mensaje y después en la variable.</p>
                    <p id="aviso-preview" class="mt-1 hidden text-xs text-gray-500 dark:text-gray-400"></p>
                    @error('cuerpo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</label>
                    <select name="tipo" class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
                        @foreach($tipos as $value => $label)
                            <option value="{{ $value }}" @selected(old('tipo', 'aviso') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="aviso-destino" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Destino</label>
                    <select name="destino" id="aviso-destino"
                            class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
                        @foreach($destinos as $value => $label)
                            <option value="{{ $value }}" @selected(old('destino', 'seleccionados') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p id="aviso-destino-ayuda" class="mt-1 text-xs text-gray-400"></p>
                </div>
            </div>

            <div id="bloque-dias" class="hidden">
                <label for="aviso-dias" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Días hacia adelante</label>
                <input type="number" name="dias" id="aviso-dias" min="1" max="30" value="{{ old('dias', $diasDefault) }}"
                       class="w-32 rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
                <p class="mt-1 text-xs text-gray-400">Incluye las facturas impagas que vencen hoy y hasta esa cantidad de días. Las ya vencidas quedan en el grupo «Con factura vencida».</p>
                @error('dias')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div id="bloque-seleccionados" class="space-y-2">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Buscar cliente con app</label>
                <div class="relative">
                    <input type="search" id="aviso-buscar" placeholder="Nombre, CI o ID…" autocomplete="off"
                           class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
                    <div id="aviso-resultados" class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-auto rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-900"></div>
                </div>
                <div id="aviso-chips" class="flex flex-wrap gap-2"></div>
                <p id="aviso-sin-sel" class="text-xs text-gray-400">Agregá uno o más clientes que tengan la app instalada.</p>
            </div>

            <div id="bloque-masivo" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                <p id="aviso-grupo-vacio" class="hidden">No hay clientes con la app en este grupo.</p>
                <label id="aviso-confirmar-label" class="inline-flex items-start gap-2">
                    <input type="checkbox" name="confirmar_masivo" id="aviso-confirmar" value="1" class="mt-0.5 rounded text-blue-600 focus:ring-blue-500"
                           @checked(old('confirmar_masivo') || old('confirmar_todos'))>
                    <span id="aviso-confirmar-txt"></span>
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700/80">
                <button type="submit" id="aviso-submit"
                        class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                    Enviar push
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Historial</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                        <th class="px-4 py-3">Aviso</th>
                        <th class="px-4 py-3">Destino</th>
                        <th class="px-4 py-3">Resultado</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @forelse ($historial as $a)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20">
                            <td class="px-4 py-3">
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">#{{ $a->id }} · {{ $a->titulo }}</div>
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $a->cuerpo }}</div>
                                <div class="mt-1 text-[11px] uppercase tracking-wide text-gray-400">
                                    {{ $tipos[$a->tipo] ?? $a->tipo }}
                                    @if($a->creador)
                                        · {{ $a->creador->name }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                {{ $a->etiquetaDestino() }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="text-emerald-700 dark:text-emerald-300">{{ $a->enviados }} OK</span>
                                @if($a->fallidos)
                                    <span class="text-rose-600 dark:text-rose-300"> · {{ $a->fallidos }} fallo</span>
                                @endif
                                @if($a->omitidos)
                                    <span class="text-amber-600 dark:text-amber-300"> · {{ $a->omitidos }} sin token</span>
                                @endif
                                <div class="text-xs text-gray-400">de {{ $a->total_destinatarios }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $a->created_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                @include('avisos-push._acciones', ['aviso' => $a, 'puedeEditar' => $puedeEditar])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Todavía no hay avisos enviados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($historial->hasPages())
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-700/80">
                {{ $historial->links() }}
            </div>
        @endif
    </div>
</div>

@include('avisos-push._menu_script')

@push('scripts')
<script>
(function () {
    var urlBuscar = @json(route('avisos-push.buscar'));
    var urlSegmento = @json(route('avisos-push.segmento'));
    var oldIds = @json($oldIds);
    var conteos = @json($conteos);
    var etiquetas = @json($destinos);
    var descripciones = @json($descripciones);
    var muestra = {
        nombre: 'Juan',
        apellido: 'Pérez',
        nombre_completo: 'Juan Pérez',
        saldo_pend: '150.000',
        facturas: '2',
        vencimiento: '15/11/2026',
        cedula: '1234567'
    };
    var seleccionados = {};
    oldIds.forEach(function (id) {
        seleccionados[id] = { cliente_id: id, nombre: 'Cliente', apellido: '', cedula: null };
    });

    var form = document.getElementById('form-aviso-push');
    var titulo = document.getElementById('aviso-titulo');
    var cuerpo = document.getElementById('aviso-cuerpo');
    var cuerpoLen = document.getElementById('aviso-cuerpo-len');
    var preview = document.getElementById('aviso-preview');
    var buscarInput = document.getElementById('aviso-buscar');
    var resultadosEl = document.getElementById('aviso-resultados');
    var chipsEl = document.getElementById('aviso-chips');
    var sinSel = document.getElementById('aviso-sin-sel');
    var bloqueSel = document.getElementById('bloque-seleccionados');
    var bloqueDias = document.getElementById('bloque-dias');
    var bloqueMasivo = document.getElementById('bloque-masivo');
    var destinoSel = document.getElementById('aviso-destino');
    var diasInput = document.getElementById('aviso-dias');
    var ayudaEl = document.getElementById('aviso-destino-ayuda');
    var confirmarTxt = document.getElementById('aviso-confirmar-txt');
    var confirmarLabel = document.getElementById('aviso-confirmar-label');
    var grupoVacio = document.getElementById('aviso-grupo-vacio');
    var submitBtn = document.getElementById('aviso-submit');
    var ultimoCampo = cuerpo;
    var debounce = null;
    var debounceDias = null;
    var abortCtrl = null;
    var abortDias = null;

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function destinoActual() {
        return destinoSel ? destinoSel.value : 'seleccionados';
    }

    function conteoActual() {
        var n = conteos[destinoActual()];
        return n == null ? 0 : parseInt(n, 10) || 0;
    }

    function pintarOpciones() {
        Array.prototype.forEach.call(destinoSel.options, function (opt) {
            var base = etiquetas[opt.value] || opt.value;
            if (opt.value === 'seleccionados') {
                opt.textContent = base;
                return;
            }
            var n = conteos[opt.value];
            opt.textContent = base + ' (' + (n == null ? 0 : n) + ')';
        });
    }

    function syncDestino() {
        var destino = destinoActual();
        var masivo = destino !== 'seleccionados';
        var vacio = masivo && conteoActual() === 0;
        bloqueSel.classList.toggle('hidden', destino !== 'seleccionados');
        bloqueDias.classList.toggle('hidden', destino !== 'por_vencer');
        bloqueMasivo.classList.toggle('hidden', !masivo);
        grupoVacio.classList.toggle('hidden', !vacio);
        confirmarLabel.classList.toggle('hidden', vacio);
        ayudaEl.textContent = descripciones[destino] || '';
        var etiqueta = etiquetas[destino] || 'ese grupo';
        confirmarTxt.textContent = 'Confirmo enviar este aviso a ' + conteoActual() + ' clientes con app: «' + etiqueta + '».';
        if (!submitBtn.dataset.enviando) {
            submitBtn.disabled = vacio;
        }
    }

    function aplicarMuestra(texto) {
        return String(texto || '').replace(/\[(nombre_completo|saldo_pend|vencimiento|facturas|apellido|nombre|cedula)\]/gi, function (_, clave) {
            return muestra[String(clave).toLowerCase()] || '';
        });
    }

    function syncPreview() {
        var texto = (cuerpo.value || '').trim();
        if (texto.indexOf('[') === -1) {
            preview.classList.add('hidden');
            preview.textContent = '';
            return;
        }
        preview.textContent = 'Vista previa: ' + aplicarMuestra(texto);
        preview.classList.remove('hidden');
    }

    function syncLen() {
        cuerpoLen.textContent = String((cuerpo.value || '').length);
        syncPreview();
    }

    function insertarVariable(token) {
        var el = ultimoCampo || cuerpo;
        var start = el.selectionStart == null ? el.value.length : el.selectionStart;
        var end = el.selectionEnd == null ? el.value.length : el.selectionEnd;
        var next = el.value.slice(0, start) + token + el.value.slice(end);
        if (el.maxLength > 0 && next.length > el.maxLength) {
            return;
        }
        el.value = next;
        var pos = start + token.length;
        el.focus();
        if (el.setSelectionRange) el.setSelectionRange(pos, pos);
        syncLen();
    }

    function renderChips() {
        chipsEl.innerHTML = '';
        var ids = Object.keys(seleccionados);
        sinSel.classList.toggle('hidden', ids.length > 0);
        ids.forEach(function (id) {
            var c = seleccionados[id];
            var span = document.createElement('span');
            span.className = 'inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-800';
            var nombre = ((c.nombre || '') + ' ' + (c.apellido || '')).trim();
            var label = (nombre && nombre !== 'Cliente')
                ? (nombre + ' · Cliente #' + c.cliente_id)
                : ('Cliente #' + c.cliente_id);
            span.innerHTML =
                '<span>' + escapeHtml(label) + '</span>' +
                '<button type="button" data-id="' + c.cliente_id + '" class="ml-0.5 text-blue-600 hover:text-rose-600 dark:text-blue-300">&times;</button>' +
                '<input type="hidden" name="cliente_ids[]" value="' + c.cliente_id + '">';
            chipsEl.appendChild(span);
        });
        chipsEl.querySelectorAll('button[data-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                delete seleccionados[btn.getAttribute('data-id')];
                renderChips();
            });
        });
    }

    function renderResultados(data) {
        resultadosEl.innerHTML = '';
        if (!data || !data.length) {
            resultadosEl.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Sin resultados con push</div>';
            resultadosEl.classList.remove('hidden');
            return;
        }
        data.forEach(function (c) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'block w-full border-b border-gray-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-blue-50 dark:border-gray-700 dark:hover:bg-blue-950/40';
            btn.innerHTML =
                '<span class="font-medium text-gray-900 dark:text-gray-100">' +
                escapeHtml((c.nombre || '') + ' ' + (c.apellido || '')) +
                '</span><span class="text-xs text-gray-500"> · #' + c.cliente_id +
                (c.cedula ? ' · ' + escapeHtml(c.cedula) : '') + '</span>';
            btn.addEventListener('click', function () {
                seleccionados[c.cliente_id] = c;
                buscarInput.value = '';
                resultadosEl.classList.add('hidden');
                renderChips();
            });
            resultadosEl.appendChild(btn);
        });
        resultadosEl.classList.remove('hidden');
    }

    destinoSel.addEventListener('change', function () {
        var cb = document.getElementById('aviso-confirmar');
        if (cb) cb.checked = false;
        syncDestino();
    });

    titulo.addEventListener('focus', function () { ultimoCampo = titulo; });
    cuerpo.addEventListener('focus', function () { ultimoCampo = cuerpo; });
    titulo.addEventListener('input', syncPreview);
    cuerpo.addEventListener('input', syncLen);

    document.querySelectorAll('.js-var').forEach(function (btn) {
        btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
        btn.addEventListener('click', function () {
            insertarVariable(btn.getAttribute('data-token') || '');
        });
    });

    diasInput.addEventListener('input', function () {
        clearTimeout(debounceDias);
        debounceDias = setTimeout(function () {
            var dias = diasInput.value;
            if (abortDias) abortDias.abort();
            abortDias = new AbortController();
            fetch(urlSegmento + '?destino=por_vencer&dias=' + encodeURIComponent(dias), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: abortDias.signal
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    conteos.por_vencer = data.count || 0;
                    pintarOpciones();
                    syncDestino();
                })
                .catch(function () {});
        }, 250);
    });

    pintarOpciones();
    syncLen();
    syncDestino();
    renderChips();

    buscarInput.addEventListener('input', function () {
        clearTimeout(debounce);
        var q = buscarInput.value.trim();
        if (q.length < 2 && !/^\d+$/.test(q)) {
            resultadosEl.classList.add('hidden');
            resultadosEl.innerHTML = '';
            return;
        }
        debounce = setTimeout(function () {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();
            fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: abortCtrl.signal
            })
                .then(function (r) { return r.json(); })
                .then(renderResultados)
                .catch(function () {});
        }, 280);
    });

    document.addEventListener('click', function (e) {
        if (!resultadosEl.contains(e.target) && e.target !== buscarInput) {
            resultadosEl.classList.add('hidden');
        }
    });

    form.addEventListener('submit', function (e) {
        var destino = destinoActual();
        if (destino === 'seleccionados' && Object.keys(seleccionados).length === 0) {
            e.preventDefault();
            alert('Seleccioná al menos un cliente.');
            return;
        }
        if (destino !== 'seleccionados' && conteoActual() === 0) {
            e.preventDefault();
            alert('No hay clientes con la app en ese grupo.');
            return;
        }
        var cb = document.getElementById('aviso-confirmar');
        if (destino !== 'seleccionados' && cb && !cb.checked) {
            e.preventDefault();
            alert('Confirmá el envío al grupo.');
            return;
        }
        submitBtn.dataset.enviando = '1';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando…';
    });
})();
</script>
@endpush
@endsection
