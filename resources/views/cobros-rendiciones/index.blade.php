@extends('layouts.app')

@section('title', 'Rendición de efectivo')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Rendición de efectivo</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Control de cobros en efectivo pendientes de entrega al tesorero/administrador.
            Al registrar una rendición se marcan todos los recibos en efectivo del cobrador como rendidos.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Efectivo pendiente</p>
            <p class="mt-2 text-2xl font-bold text-amber-700 dark:text-amber-300">{{ number_format($totalPendiente, 0, ',', '.') }} Gs.</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $totalCobrosPendientes }} recibo{{ $totalCobrosPendientes === 1 ? '' : 's' }} sin rendir</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cobradores con saldo</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $pendientes->count() }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Rendiciones registradas</p>
            <p class="mt-2 text-2xl font-bold text-green-700 dark:text-green-400">{{ $rendiciones->total() }}</p>
            @if($sinCobrador > 0)
                <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">{{ $sinCobrador }} cobro{{ $sinCobrador === 1 ? '' : 's' }} en efectivo sin usuario cobrador</p>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pendiente por cobrador</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cobrador</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recibos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Período cobros</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($pendientes as $p)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $p['nombre'] }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ $p['cantidad'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-amber-700 dark:text-amber-300">{{ number_format($p['monto'], 0, ',', '.') }} Gs.</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                @if($p['desde'] && $p['hasta'])
                                    {{ $p['desde']->format('d/m/Y') }} — {{ $p['hasta']->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if(auth()->user()?->tienePermiso('cobros-rendicion.crear'))
                                    <button type="button"
                                        class="text-sm text-purple-600 dark:text-purple-400 hover:underline mr-3 btn-ver-cobros"
                                        data-usuario-id="{{ $p['usuario_id'] }}"
                                        data-nombre="{{ $p['nombre'] }}">
                                        Ver recibos
                                    </button>
                                    <button type="button"
                                        class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 btn-registrar-rendicion"
                                        data-usuario-id="{{ $p['usuario_id'] }}"
                                        data-nombre="{{ $p['nombre'] }}"
                                        data-monto="{{ $p['monto'] }}"
                                        data-cantidad="{{ $p['cantidad'] }}">
                                        Registrar rendición
                                    </button>
                                @else
                                    <span class="text-gray-400 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No hay efectivo pendiente de rendir.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Historial de rendiciones</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha y hora</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cobrador</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recibió (tesorero)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recibos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Monto</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($rendiciones as $r)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $r->fecha_rendicion->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $r->cobrador?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $r->tesorero?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right text-gray-700 dark:text-gray-300">{{ $r->cantidad_cobros }}</td>
                            <td class="px-4 py-3 text-sm text-right font-semibold text-green-700 dark:text-green-400">{{ number_format((float) $r->monto, 0, ',', '.') }} Gs.</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('cobros-rendiciones.show', $r) }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">Detalle</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Sin rendiciones registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rendiciones->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $rendiciones->links() }}</div>
        @endif
    </div>
</div>

{{-- Modal registrar rendición --}}
@if(auth()->user()?->tienePermiso('cobros-rendicion.crear'))
<div id="modal-rendicion" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" data-close-modal></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Registrar rendición</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Cobrador: <span id="modal-cobrador-nombre" class="font-medium text-gray-900 dark:text-gray-100"></span><br>
                Monto a recibir: <span id="modal-monto" class="font-semibold text-green-700 dark:text-green-400"></span>
                (<span id="modal-cantidad"></span> recibos)
            </p>
            <form id="form-rendicion" action="{{ route('cobros-rendiciones.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="usuario_cobrador_id" id="modal-usuario-id">
                <div>
                    <label for="fecha_rendicion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha y hora de la rendición</label>
                    <input type="datetime-local" name="fecha_rendicion" id="fecha_rendicion" required
                        value="{{ now()->format('Y-m-d\TH:i') }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label for="observaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observaciones (opcional)</label>
                    <textarea name="observaciones" id="observaciones" rows="2" maxlength="2000"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        placeholder="Ej. entrega en oficina, arqueo OK"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" data-close-modal>Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Confirmar rendición</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal ver recibos pendientes --}}
<div id="modal-cobros" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" data-close-modal-cobros></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative w-full max-w-2xl bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 p-6 max-h-[85vh] flex flex-col">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Recibos pendientes</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4"><span id="modal-cobros-cobrador"></span></p>
            <div id="modal-cobros-loading" class="text-sm text-gray-500 dark:text-gray-400 py-4">Cargando…</div>
            <div id="modal-cobros-content" class="overflow-y-auto hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase">
                            <th class="py-2 pr-3">Recibo</th>
                            <th class="py-2 pr-3">Fecha</th>
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody id="modal-cobros-tbody" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700" data-close-modal-cobros>Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const modalRendicion = document.getElementById('modal-rendicion');
    const modalCobros = document.getElementById('modal-cobros');

    function openModal(el) { if (el) { el.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); } }
    function closeModal(el) { if (el) { el.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); } }

    document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(modalRendicion); });
    });
    document.querySelectorAll('[data-close-modal-cobros]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(modalCobros); });
    });

    document.querySelectorAll('.btn-registrar-rendicion').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const monto = Number(btn.dataset.monto || 0);
            document.getElementById('modal-usuario-id').value = btn.dataset.usuarioId;
            document.getElementById('modal-cobrador-nombre').textContent = btn.dataset.nombre || '';
            document.getElementById('modal-monto').textContent = monto.toLocaleString('es-PY') + ' Gs.';
            document.getElementById('modal-cantidad').textContent = btn.dataset.cantidad || '0';
            openModal(modalRendicion);
        });
    });

    document.querySelectorAll('.btn-ver-cobros').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const usuarioId = btn.dataset.usuarioId;
            document.getElementById('modal-cobros-cobrador').textContent = btn.dataset.nombre || '';
            document.getElementById('modal-cobros-loading').classList.remove('hidden');
            document.getElementById('modal-cobros-content').classList.add('hidden');
            document.getElementById('modal-cobros-tbody').innerHTML = '';
            openModal(modalCobros);

            fetch('{{ url('/cobros/rendiciones/pendientes') }}/' + usuarioId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const tbody = document.getElementById('modal-cobros-tbody');
                    (data.cobros || []).forEach(function (c) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = '<td class="py-2 pr-3 text-gray-900 dark:text-gray-100">' + (c.numero_recibo || '') + '</td>'
                            + '<td class="py-2 pr-3 text-gray-600 dark:text-gray-400">' + (c.fecha_pago || '') + '</td>'
                            + '<td class="py-2 pr-3 text-gray-900 dark:text-gray-100">' + (c.cliente || '') + '</td>'
                            + '<td class="py-2 text-right font-medium text-gray-900 dark:text-gray-100">' + Number(c.monto || 0).toLocaleString('es-PY') + ' Gs.</td>';
                        tbody.appendChild(tr);
                    });
                    document.getElementById('modal-cobros-loading').classList.add('hidden');
                    document.getElementById('modal-cobros-content').classList.remove('hidden');
                })
                .catch(function () {
                    document.getElementById('modal-cobros-loading').textContent = 'Error al cargar los recibos.';
                });
        });
    });

    document.getElementById('form-rendicion')?.addEventListener('submit', function (e) {
        const monto = document.getElementById('modal-monto')?.textContent || '';
        if (!confirm('¿Confirmar rendición de ' + monto + '? Se marcarán todos los recibos en efectivo pendientes de este cobrador.')) {
            e.preventDefault();
        }
    });
})();
</script>
@endpush
