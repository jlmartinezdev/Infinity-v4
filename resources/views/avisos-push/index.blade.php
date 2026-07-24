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
            Enviá notificaciones editables a la app del cliente.
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $conPush }}</span> con app / push activo.
        </p>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form id="form-aviso-push" method="POST" action="{{ route('avisos-push.store') }}" class="space-y-4 p-4 sm:p-5">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Título</label>
                    <input type="text" name="titulo" value="{{ old('titulo') }}" maxlength="120" required
                           placeholder="Ej. Promoción de febrero"
                           class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
                    @error('titulo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mensaje</label>
                    <textarea id="aviso-cuerpo" name="cuerpo" rows="3" maxlength="500" required
                              placeholder="Texto que verá el cliente en la notificación…"
                              class="w-full rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">{{ old('cuerpo') }}</textarea>
                    <p class="mt-1 text-xs text-gray-400"><span id="aviso-cuerpo-len">0</span>/500</p>
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
                    <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Destino</label>
                    <div class="flex flex-wrap gap-3 pt-1">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="radio" name="destino" value="seleccionados" class="js-destino text-blue-600 focus:ring-blue-500"
                                   @checked(old('destino', 'seleccionados') === 'seleccionados')>
                            Seleccionados
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="radio" name="destino" value="todos" class="js-destino text-blue-600 focus:ring-blue-500"
                                   @checked(old('destino') === 'todos')>
                            Todos con app ({{ $conPush }})
                        </label>
                    </div>
                </div>
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

            <div id="bloque-todos" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                <label class="inline-flex items-start gap-2">
                    <input type="checkbox" name="confirmar_todos" value="1" class="mt-0.5 rounded text-blue-600 focus:ring-blue-500"
                           @checked(old('confirmar_todos'))>
                    <span>Confirmo enviar este aviso a <strong>todos</strong> los clientes con push activo ({{ $conPush }}).</span>
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
                                @if($a->destino === 'todos')
                                    Todos
                                @else
                                    {{ count($a->cliente_ids ?? []) }} seleccionado(s)
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="text-emerald-700 dark:text-emerald-300">{{ $a->enviados }} OK</span>
                                @if($a->fallidos)
                                    <span class="text-rose-600 dark:text-rose-300"> · {{ $a->fallidos }} fallo</span>
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
    var oldIds = @json($oldIds);
    var seleccionados = {};
    oldIds.forEach(function (id) {
        seleccionados[id] = { cliente_id: id, nombre: 'Cliente', apellido: '', cedula: null };
    });

    var form = document.getElementById('form-aviso-push');
    var cuerpo = document.getElementById('aviso-cuerpo');
    var cuerpoLen = document.getElementById('aviso-cuerpo-len');
    var buscarInput = document.getElementById('aviso-buscar');
    var resultadosEl = document.getElementById('aviso-resultados');
    var chipsEl = document.getElementById('aviso-chips');
    var sinSel = document.getElementById('aviso-sin-sel');
    var bloqueSel = document.getElementById('bloque-seleccionados');
    var bloqueTodos = document.getElementById('bloque-todos');
    var debounce = null;
    var abortCtrl = null;

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function destinoActual() {
        var el = form.querySelector('input[name="destino"]:checked');
        return el ? el.value : 'seleccionados';
    }

    function syncDestino() {
        var todos = destinoActual() === 'todos';
        bloqueSel.classList.toggle('hidden', todos);
        bloqueTodos.classList.toggle('hidden', !todos);
    }

    function syncLen() {
        cuerpoLen.textContent = String((cuerpo.value || '').length);
    }

    function renderChips() {
        chipsEl.innerHTML = '';
        var ids = Object.keys(seleccionados);
        sinSel.classList.toggle('hidden', ids.length > 0);
        ids.forEach(function (id) {
            var c = seleccionados[id];
            var span = document.createElement('span');
            span.className = 'inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-800';
            span.innerHTML =
                '<span>' + escapeHtml((c.nombre || '') + ' #' + c.cliente_id) + '</span>' +
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

    document.querySelectorAll('.js-destino').forEach(function (el) {
        el.addEventListener('change', syncDestino);
    });

    cuerpo.addEventListener('input', syncLen);
    syncLen();
    syncDestino();
    renderChips();

    buscarInput.addEventListener('input', function () {
        clearTimeout(debounce);
        var q = buscarInput.value.trim();
        if (q.length < 2) {
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
        if (destinoActual() === 'seleccionados' && Object.keys(seleccionados).length === 0) {
            e.preventDefault();
            alert('Seleccioná al menos un cliente.');
            return;
        }
        var btn = document.getElementById('aviso-submit');
        btn.disabled = true;
        btn.textContent = 'Enviando…';
    });
})();
</script>
@endpush
@endsection
