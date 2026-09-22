@extends('layouts.app')

@section('title', 'Editar servicio')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar servicio #{{ $servicio->servicio_id }} — {{ $servicio->cliente->nombre }} {{ $servicio->cliente->apellido }}</h1>

    @if(auth()->user()?->tienePermiso('servicios.ver'))
        @php $hotspotsServicio = $servicio->servicioHotspots; @endphp
        @if($hotspotsServicio->isNotEmpty())
        <div class="mb-6 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-xl border border-purple-200 dark:border-purple-800">
            <p class="text-sm text-purple-800 dark:text-purple-200">
                <strong>Hotspot:</strong>
                {{ $hotspotsServicio->count() }} {{ $hotspotsServicio->count() === 1 ? 'usuario' : 'usuarios' }}
                anclados a este servicio ({{ $hotspotsServicio->pluck('username')->join(', ') }}).
                <a href="{{ route('hotspot.clientes.edit', $servicio->cliente) }}" class="underline">Gestionar slots del cliente</a>
            </p>
        </div>
        @else
        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <a href="{{ route('hotspot.clientes.edit', $servicio->cliente) }}" class="text-purple-600 dark:text-purple-400 hover:underline">Asociar usuario Hotspot (hasta 3 por cliente)</a>
            </p>
        </div>
        @endif
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form id="form-editar-servicio"
            action="{{ route('servicios.update', $servicio->servicio_id) }}"
            method="POST"
            data-initial-plan-id="{{ (int) $servicio->plan_id }}"
            data-initial-plan-precio="{{ (float) ($servicio->plan->precio ?? 0) }}">
            <input type="hidden" name="generar_factura_prorrateo_cambio_plan" id="generar_factura_prorrateo_cambio_plan" value="1">
            @include('servicios._form', [
                'servicio' => $servicio,
                'clientes' => $clientes,
                'planes' => $planes,
                'pools' => $pools,
            ])
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var form = document.getElementById('form-editar-servicio');
    if (!form || typeof Swal === 'undefined') return;
    var planSelect = document.getElementById('plan_id');
    var hiddenProrr = document.getElementById('generar_factura_prorrateo_cambio_plan');
    if (!planSelect || !hiddenProrr) return;

    form.addEventListener('submit', function(e) {
        var initialId = String(form.dataset.initialPlanId || '');
        var newId = String(planSelect.value || '');
        if (initialId === '' || newId === '' || initialId === newId) {
            return;
        }
        var opt = planSelect.options[planSelect.selectedIndex];
        var newPrecio = parseFloat(opt && opt.dataset ? opt.dataset.precio : '0') || 0;
        var oldPrecio = parseFloat(form.dataset.initialPlanPrecio || '0') || 0;
        if (Math.abs(newPrecio - oldPrecio) < 0.01) {
            return;
        }
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Cambio de plan con distinto precio',
            text: '¿Desea generar la factura interna prorrateada por el cambio de plan en lo que resta del mes?',
            showDenyButton: true,
            confirmButtonText: 'Sí, generar factura prorrateada',
            denyButtonText: 'No, guardar sin factura',
            focusDeny: false,
            reverseButtons: true
        }).then(function(r) {
            if (r.isDismissed) return;
            hiddenProrr.value = r.isConfirmed ? '1' : '0';
            HTMLFormElement.prototype.submit.call(form);
        });
    });
})();
</script>
@endpush
