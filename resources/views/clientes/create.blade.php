@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nuevo cliente</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('clientes.store') }}" method="POST" id="form-cliente">
            @include('clientes._form', ['cliente' => null])
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
    var btn = document.getElementById('btn-buscar-padron');
    if (!btn) return;

    var cedulaInput = document.getElementById('cedula');
    var nombreInput = document.getElementById('nombre');
    var apellidoInput = document.getElementById('apellido');
    var direccionInput = document.getElementById('direccion');
    var msgEl = document.getElementById('cedula-msg');
    var textEl = document.getElementById('btn-buscar-padron-text');
    var loadingEl = document.getElementById('btn-buscar-padron-loading');

    var consultarPadronUrl = '{{ route("pedidos.consultar-padron") }}';
    var verificarCedulaUrl = '{{ route("clientes.verificar-cedula") }}';
    var clientesIndexUrl = '{{ route("clientes.index") }}';
    var clientesEditUrl = '{{ url("clientes") }}';

    function showMsg(text, isError) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.classList.remove('hidden', 'text-red-600', 'text-green-600', 'dark:text-red-400', 'dark:text-green-400');
        msgEl.classList.add(isError ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400');
    }

    function hideMsg() {
        if (msgEl) msgEl.classList.add('hidden');
    }

    btn.addEventListener('click', function () {
        var cedula = (cedulaInput && cedulaInput.value) ? cedulaInput.value.trim() : '';
        if (!cedula) {
            showMsg('Ingrese el número de cédula.', true);
            return;
        }
        hideMsg();
        btn.disabled = true;
        if (textEl) textEl.classList.add('hidden');
        if (loadingEl) loadingEl.classList.remove('hidden');

        // 1) Verificar si ya está registrado como cliente
        window.axios.post(verificarCedulaUrl, { cedula: cedula })
            .then(function (res) {
                if (res.data && res.data.existe) {
                    if (res.data.activado) {
                        showMsg('Este cliente estaba registrado desde un pedido. Se ha activado para la lista de clientes.', false);
                        if (res.data.cliente && res.data.cliente.cliente_id && msgEl) {
                            var link = clientesEditUrl + '/' + res.data.cliente.cliente_id + '/edit';
                            msgEl.innerHTML = 'Cliente activado. <a href="' + link + '" class="font-medium text-purple-600 dark:text-purple-400 hover:underline">Ir a editar</a>';
                        }
                    } else {
                        showMsg('Este cliente ya está registrado. Puede editarlo desde la lista de clientes.', true);
                        if (res.data.cliente && res.data.cliente.cliente_id && msgEl) {
                            var link = clientesEditUrl + '/' + res.data.cliente.cliente_id + '/edit';
                            msgEl.innerHTML = 'Este cliente ya está registrado. <a href="' + link + '" class="font-medium text-purple-600 dark:text-purple-400 hover:underline">Ir a editar</a>';
                        }
                    }
                    return;
                }
                // 2) Consultar padrón
                return window.axios.post(consultarPadronUrl, { cedula: cedula });
            })
            .then(function (padronRes) {
                if (!padronRes) return; // ya mostramos mensaje (cliente existe)
                if (padronRes.data && padronRes.data.encontrado) {
                    var d = padronRes.data;
                    if (nombreInput) nombreInput.value = d.nombre || '';
                    if (apellidoInput) apellidoInput.value = d.apellido || '';
                    if (direccionInput) direccionInput.value = [d.direccion, d.domicilio].filter(Boolean).join(' ').trim() || '';
                    showMsg('Datos del padrón cargados. Revise y complete si falta algo.', false);
                } else {
                    showMsg('No se encontró en el padrón. Puede completar los datos manualmente.', true);
                }
            })
            .catch(function (err) {
                if (err.response && err.response.status === 404 && err.response.data && err.response.data.encontrado === false) {
                    showMsg('No se encontró en el padrón. Puede completar los datos manualmente.', true);
                } else {
                    showMsg(err.response && err.response.data && err.response.data.error ? err.response.data.error : 'Error al consultar. Intente de nuevo.', true);
                }
            })
            .finally(function () {
                btn.disabled = false;
                if (textEl) textEl.classList.remove('hidden');
                if (loadingEl) loadingEl.classList.add('hidden');
            });
    });
})();
</script>
@endpush
@endsection
