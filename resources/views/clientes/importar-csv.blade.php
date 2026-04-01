@extends('layouts.app')

@section('title', 'Importar clientes desde CSV')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Importar clientes desde CSV</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Sube un archivo CSV con las columnas: cedula, estado, estado_pago, fecha_instalacion, ip, nombre, password_pppoe, plan_id, router_id, usuario_pppoe.
            Se crearán clientes (cedula, nombre, estado activo) y servicios asociados. El pool_id se determina por el prefijo de la IP.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <strong>Mapeo de IP a pool_id:</strong> 10.0→2, 10.1→7, 10.2→3, 10.3→7, 10.5→4, 10.6→5, otras→6
            </p>
        </div>
        <div class="p-6">
            <form id="form-importar-csv" class="space-y-4">
                @csrf
                <div>
                    <label for="archivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo CSV</label>
                    <input type="file" id="archivo" name="archivo" accept=".csv,.txt"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-purple-50 file:text-purple-700 dark:file:bg-purple-900/30 dark:file:text-purple-300">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Máx. 10 MB. Separador: coma o punto y coma.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="dry_run" name="dry_run" value="1" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <label for="dry_run" class="text-sm text-gray-700 dark:text-gray-300">Solo simular (no guardar en BD)</label>
                </div>
                <div class="pt-2">
                    <button type="submit" id="btn-importar" class="inline-flex items-center px-5 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="btn-text">Importar</span>
                        <span class="btn-loading hidden">Procesando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="resultado-importacion" class="mt-6 hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 font-medium text-gray-900 dark:text-gray-100">Resultado</div>
        <div class="p-6">
            <div id="resultado-mensaje" class="text-sm"></div>
            <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Clientes creados</dt>
                    <dd id="resultado-creados" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Clientes actualizados</dt>
                    <dd id="resultado-actualizados" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Servicios creados</dt>
                    <dd id="resultado-servicios" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Errores</dt>
                    <dd id="resultado-errores" class="font-semibold text-gray-900 dark:text-gray-100">0</dd>
                </div>
            </dl>
            <div id="resultado-detalle" class="mt-4 hidden">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Detalle de errores:</p>
                <ul id="resultado-mensajes" class="text-xs text-amber-700 dark:text-amber-400 space-y-1 max-h-40 overflow-y-auto"></ul>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var form = document.getElementById('form-importar-csv');
    var btn = document.getElementById('btn-importar');
    var btnText = btn && btn.querySelector('.btn-text');
    var btnLoading = btn && btn.querySelector('.btn-loading');
    var resultadoBox = document.getElementById('resultado-importacion');
    var resultadoMsg = document.getElementById('resultado-mensaje');
    var resultadoCreados = document.getElementById('resultado-creados');
    var resultadoActualizados = document.getElementById('resultado-actualizados');
    var resultadoServicios = document.getElementById('resultado-servicios');
    var resultadoErrores = document.getElementById('resultado-errores');
    var resultadoDetalle = document.getElementById('resultado-detalle');
    var resultadoMensajes = document.getElementById('resultado-mensajes');

    if (form && btn) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var archivo = document.getElementById('archivo');
            if (!archivo || !archivo.files.length) {
                alert('Selecciona un archivo CSV.');
                return;
            }
            if (btn.disabled) return;
            btn.disabled = true;
            if (btnText) btnText.classList.add('hidden');
            if (btnLoading) btnLoading.classList.remove('hidden');
            if (resultadoBox) resultadoBox.classList.add('hidden');

            var formData = new FormData(form);
            formData.append('archivo', archivo.files[0]);
            formData.append('dry_run', document.getElementById('dry_run').checked ? '1' : '0');
            formData.append('_token', document.querySelector('input[name="_token"]').value);

            fetch('{{ route("clientes.importar-csv.store") }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (resultadoBox) {
                    resultadoBox.classList.remove('hidden');
                    resultadoMsg.textContent = data.message || (data.success ? 'Importación completada.' : 'Error en la importación.');
                    resultadoMsg.className = 'text-sm ' + (data.success ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400');
                    var r = data.result || {};
                    resultadoCreados.textContent = r.creados ?? 0;
                    resultadoActualizados.textContent = r.actualizados ?? 0;
                    resultadoServicios.textContent = r.servicios_creados ?? 0;
                    resultadoErrores.textContent = r.errores ?? 0;

                    if (r.mensajes && r.mensajes.length) {
                        resultadoDetalle.classList.remove('hidden');
                        resultadoMensajes.innerHTML = r.mensajes.map(function(m) { return '<li>' + m + '</li>'; }).join('');
                    } else {
                        resultadoDetalle.classList.add('hidden');
                    }
                }
            })
            .catch(function(err) {
                if (resultadoBox) {
                    resultadoBox.classList.remove('hidden');
                    resultadoMsg.textContent = 'Error de conexión: ' + (err.message || 'vuelve a intentar.');
                    resultadoMsg.className = 'text-sm text-red-700 dark:text-red-400';
                    resultadoCreados.textContent = '—';
                    resultadoActualizados.textContent = '—';
                    resultadoServicios.textContent = '—';
                    resultadoErrores.textContent = '—';
                    resultadoDetalle.classList.add('hidden');
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
@endsection
