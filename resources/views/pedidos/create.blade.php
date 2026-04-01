@extends('layouts.app')

@section('title', 'Nuevo pedido')

@section('content')
<div class="max-w-4xl mx-auto">
    <div id="pedido-form-app"></div>
</div>

@push('scripts')
<script>
    window.__PEDIDO_FORM_CONFIG__ = {!! json_encode([
        'pedidoId' => 'Nuevo',
        'planes' => $planes,
        'estadoId' => $estado->estado_id ?? 1,
        'buscarClienteUrl' => route('pedidos.buscar-cliente'),
        'consultarPadronUrl' => route('pedidos.consultar-padron'),
        'submitUrl' => route('pedidos.store'),
        'cancelUrl' => route('pedidos.index'),
        'csrfToken' => csrf_token(),
    ]) !!};
</script>
<script src="{{ asset(mix('js/pedido-form.js')) }}"></script>
@endpush
@endsection
