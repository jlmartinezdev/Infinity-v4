@extends('layouts.app')

@section('title', 'Pendiente de pago')

@section('content')
<div id="pendientes-pago-app"></div>

@php
    $pfKeys = ['pf_id', 'pf_cliente', 'pf_per_desde', 'pf_per_hasta', 'pf_ven_desde', 'pf_ven_hasta', 'pf_total_min', 'pf_total_max', 'pf_cob_min', 'pf_cob_max', 'pf_saldo_min', 'pf_saldo_max', 'pf_promesa'];
    $u = auth()->user();
    $phF = 989898989;
    $phC = 979797979;
    $config = [
        'listUrl' => route('factura-internas.pendientes.list'),
        'exportExcelUrl' => route('factura-internas.pendientes.exportar-excel'),
        'pfKeys' => $pfKeys,
        'urls' => [
            'promesasIndex' => route('promesas-pago.index'),
            'cobrosCreate' => route('cobros.create'),
            'multicobro' => route('cobros.multicobro'),
        ],
        'templates' => [
            'facturaShow' => str_replace('/'.$phF, '/{id}', route('factura-internas.show', ['factura_interna' => $phF])),
            'promesaCreate' => str_replace('/'.$phF, '/{id}', route('promesas-pago.create', ['factura_interna' => $phF])),
        ],
        'clienteDetalleTpl' => ($u?->tienePermiso('clientes.ver'))
            ? str_replace('/'.$phC, '/{id}', route('clientes.detalle', ['cliente' => $phC]))
            : '',
        'canMulticobro' => $u?->tienePermiso('cobros.crear') ?? false,
        'canCrearCobro' => $u?->tienePermiso('cobros.crear') ?? false,
        'canVerClienteDetalle' => $u?->tienePermiso('clientes.ver') ?? false,
        'flashSuccess' => session('success') ?? '',
        'flashError' => session('error') ?? '',
    ];
@endphp
<script>
    window.__PENDIENTES_PAGO_CONFIG__ = @json($config);
</script>

@push('scripts')
<script src="{{ asset(mix('js/pendientes-pago.js')) }}" defer></script>
@endpush
@endsection
