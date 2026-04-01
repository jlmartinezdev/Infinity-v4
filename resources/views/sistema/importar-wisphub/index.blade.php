@extends('layouts.app')

@section('title', 'Importar desde WispHub')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Importar clientes desde WispHub</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Sincroniza clientes desde la API de WispHub hacia Infinity. Se identifican por cédula: si ya existe se actualiza, si no se crea.
            También puedes exportar todos los clientes de WispHub a un archivo Excel (CSV).
        </p>
    </div>

    @if (!$configured)
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-6 text-amber-900 dark:text-amber-200">
            <p class="font-medium">WispHub no está configurado</p>
            <p class="mt-2 text-sm">Añade en tu archivo <code class="bg-amber-100 px-1 rounded">.env</code>:</p>
            <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                <li><code>WISPHUB_API_KEY=tu_clave_api</code></li>
                <li><code>WISPHUB_BASE_URL=https://api.wisphub.net</code> (o <code>https://sandbox-api.wisphub.net</code> para pruebas)</li>
            </ul>
            <p class="mt-3 text-sm">Obtén la clave API en la <a href="https://wisphub.net/staff/" target="_blank" rel="noopener" class="underline">Lista de Personal de WispHub</a>.</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <p class="text-sm text-gray-600 dark:text-gray-400">Conectando a: <strong>{{ $baseUrl }}</strong></p>
            </div>
            <div class="p-6">
                <form id="form-importar-wisphub" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registros por página</label>
                            <input type="number" id="limit" name="limit" value="50" min="1" max="100"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        </div>
                        <div>
                            <label for="max" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Máximo a importar (por ejecución)</label>
                            <input type="number" id="max" name="max" value="200" min="1" max="500"
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Máx. 500 por petición para evitar timeouts.</p>
                        </div>
                    </div>
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filtrar por estado (WispHub)</label>
                        <select id="estado" name="estado" class="w-full sm:w-64 px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">Todos</option>
                            <option value="1">Activo</option>
                            <option value="2">Suspendido</option>
                            <option value="3">Cancelado</option>
                            <option value="4">Gratis</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="dry_run" name="dry_run" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                        <label for="dry_run" class="text-sm text-gray-700 dark:text-gray-300">Solo simular (no guardar en BD)</label>
                    </div>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit" id="btn-importar" class="inline-flex items-center px-5 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="btn-text">Importar ahora</span>
                            <span class="btn-loading hidden">Importando…</span>
                        </button>
                        <a href="{{ route('sistema.importar-wisphub.exportar-excel') }}" id="btn-exportar-excel"
                            class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Exportar a Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div id="resultado-importacion" class="mt-6 hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 font-medium text-gray-900 dark:text-gray-100">Resultado</div>
            <div class="p-6">
                <div id="resultado-mensaje" class="text-sm"></div>
                <dl class="mt-4 grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Nuevos</dt>
                        <dd id="resultado-importados" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Actualizados</dt>
                        <dd id="resultado-actualizados" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Errores</dt>
                        <dd id="resultado-errores" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                    </div>
                </dl>
            </div>
        </div>
    @endif
</div>

@if($configured)
@push('scripts')
<script>
(function() {
    var form = document.getElementById('form-importar-wisphub');
    var btn = document.getElementById('btn-importar');
    var btnText = btn && btn.querySelector('.btn-text');
    var btnLoading = btn && btn.querySelector('.btn-loading');
    var resultadoBox = document.getElementById('resultado-importacion');
    var resultadoMsg = document.getElementById('resultado-mensaje');
    var resultadoImportados = document.getElementById('resultado-importados');
    var resultadoActualizados = document.getElementById('resultado-actualizados');
    var resultadoErrores = document.getElementById('resultado-errores');

    var btnExportar = document.getElementById('btn-exportar-excel');
    if (btnExportar) {
        btnExportar.addEventListener('click', function(e) {
            var estado = document.getElementById('estado') && document.getElementById('estado').value;
            if (estado) {
                e.preventDefault();
                window.location.href = btnExportar.href + '?estado=' + encodeURIComponent(estado);
            }
        });
    }

    if (form && btn) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (btn.disabled) return;
            btn.disabled = true;
            if (btnText) btnText.classList.add('hidden');
            if (btnLoading) btnLoading.classList.remove('hidden');
            if (resultadoBox) resultadoBox.classList.add('hidden');

            var formData = new FormData(form);
            var body = {
                limit: formData.get('limit') || 50,
                max: formData.get('max') || 200,
                dry_run: formData.get('dry_run') ? true : false,
                _token: formData.get('_token')
            };
            var estado = form.querySelector('#estado').value;
            if (estado) body.estado = parseInt(estado, 10);

            fetch('{{ route("sistema.importar-wisphub.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': formData.get('_token'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (resultadoBox) {
                    resultadoBox.classList.remove('hidden');
                    resultadoMsg.textContent = data.message || (data.success ? 'Importación completada.' : 'Error en la importación.');
                    resultadoMsg.className = 'text-sm ' + (data.success ? 'text-green-700' : 'text-amber-700');
                    var r = data.result || {};
                    resultadoImportados.textContent = r.importados ?? 0;
                    resultadoActualizados.textContent = r.actualizados ?? 0;
                    resultadoErrores.textContent = r.errores ?? 0;
                }
            })
            .catch(function(err) {
                if (resultadoBox) {
                    resultadoBox.classList.remove('hidden');
                    resultadoMsg.textContent = 'Error de conexión: ' + (err.message || 'vuelve a intentar.');
                    resultadoMsg.className = 'text-sm text-red-700';
                    resultadoImportados.textContent = '—';
                    resultadoActualizados.textContent = '—';
                    resultadoErrores.textContent = '—';
                }
            })
            .finally(function() {
                btn.disabled = false;
                if (btnText) btnText.classList.remove('hidden');
                if (btnLoading) btnLoading.classList.add('hidden');
            });
        });
    }
})();
</script>
@endpush
@endif
@endsection
