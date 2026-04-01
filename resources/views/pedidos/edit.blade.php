@extends('layouts.app')

@section('title', 'Editar pedido')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('pedidos.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver a pedidos</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar pedido</h1>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('pedidos.update', $pedido) }}" method="POST">
            @include('pedidos._form', ['pedido' => $pedido, 'clientes' => $clientes, 'estados' => $estados, 'estadoActual' => $estadoActual])
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('btn-buscar-padron-pedido');
    if (!btn) return;

    var cedulaInput = document.getElementById('cedula-padron');
    var selectCliente = document.getElementById('cliente_id');
    var msgEl = document.getElementById('cedula-padron-msg');
    var textEl = document.getElementById('btn-buscar-padron-pedido-text');
    var loadingEl = document.getElementById('btn-buscar-padron-pedido-loading');

    var crearDesdePadronUrl = '{{ route("clientes.crear-desde-padron") }}';

    function showMsg(text, isError) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.classList.remove('hidden', 'text-red-600', 'text-green-600');
        msgEl.classList.add(isError ? 'text-red-600' : 'text-green-600');
    }

    function addClienteOptionAndSelect(cliente) {
        if (!selectCliente || !cliente || !cliente.cliente_id) return;
        var opt = selectCliente.querySelector('option[value="' + cliente.cliente_id + '"]');
        if (!opt) {
            var label = [cliente.nombre, cliente.apellido].filter(Boolean).join(' ') + ' (' + (cliente.cedula || '') + ')';
            opt = new Option(label, cliente.cliente_id, false, true);
            opt.setAttribute('data-telefono', cliente.telefono || '');
            selectCliente.appendChild(opt);
        }
        selectCliente.value = String(cliente.cliente_id);
        var celularInput = document.getElementById('celular');
        if (celularInput && cliente.telefono !== undefined) celularInput.value = cliente.telefono || '';
    }

    btn.addEventListener('click', function () {
        var cedula = (cedulaInput && cedulaInput.value) ? cedulaInput.value.trim() : '';
        if (!cedula) {
            showMsg('Ingrese el número de cédula.', true);
            return;
        }
        msgEl.classList.add('hidden');
        btn.disabled = true;
        if (textEl) textEl.classList.add('hidden');
        if (loadingEl) loadingEl.classList.remove('hidden');

        window.axios.post(crearDesdePadronUrl, { cedula: cedula })
            .then(function (res) {
                var data = res.data;
                if (data.existe) {
                    showMsg('Cliente ya estaba cargado. Se seleccionó en la lista.', false);
                    addClienteOptionAndSelect(data.cliente);
                } else if (data.creado && data.cliente) {
                    showMsg('Cliente creado desde el padrón y seleccionado.', false);
                    addClienteOptionAndSelect(data.cliente);
                }
            })
            .catch(function (err) {
                if (err.response && err.response.status === 404) {
                    showMsg(err.response.data && err.response.data.mensaje ? err.response.data.mensaje : 'No se encontró en el padrón.', true);
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
