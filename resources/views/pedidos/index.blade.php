@extends('layouts.app')

@section('title', 'Lista de pedidos')

@section('content')
<div id="pedidos-app"></div>

<script>
    window.__PEDIDOS_APP_CONFIG__ = {!! json_encode([
        'pedidos' => $pedidos->map(function($pedido) {
            return [
                'pedido_id' => $pedido->pedido_id,
                'cliente_id' => $pedido->cliente_id,
                'cliente' => [
                    'nombre' => $pedido->cliente->nombre,
                    'apellido' => $pedido->cliente->apellido,
                    'cedula' => $pedido->cliente->cedula,
                    'telefono' => $pedido->cliente->telefono,
                ],
                'plan' => $pedido->plan ? ['nombre' => $pedido->plan->nombre] : null,
                'fecha_pedido' => $pedido->fecha_pedido ? $pedido->fecha_pedido->toDateString() : null,
                'ubicacion' => $pedido->ubicacion,
                'maps_gps' => $pedido->maps_gps,
                'lat' => $pedido->lat,
                'lon' => $pedido->lon,
                'descripcion' => $pedido->descripcion,
                'tecnologia_id_seleccionado' => $pedido->tecnologia_id_seleccionado ?? null,
                'usuario_pppoe_creado' => (bool) ($pedido->usuario_pppoe_creado ?? false),
                'estado_instalado' => (bool) ($pedido->estado_instalado ?? false),
                'tiene_agenda' => (bool) ($pedido->agendas_count ?? 0),
                'estado_pedido_detalles' => $pedido->estadoPedidoDetalles->map(function($detalle) {
                    return [
                        'pedido_id' => $detalle->pedido_id,
                        'estado_id' => $detalle->estado_id,
                        'estado_pedido' => [
                            'descripcion' => $detalle->estadoPedido->descripcion ?? null,
                            'parametro' => $detalle->estadoPedido->parametro ?? null,
                        ],
                        'usuario' => $detalle->usuario ? ['name' => $detalle->usuario->name] : null,
                        'fecha' => $detalle->fecha ? $detalle->fecha->toDateTimeString() : null,
                        'created_at' => $detalle->created_at ? $detalle->created_at->toDateTimeString() : null,
                        'estado' => $detalle->estado,
                        'notas' => $detalle->notas,
                        'nodo_id' => $detalle->nodo_id,
                        'tecnologia_id' => $detalle->tecnologia_id,
                        'plan_id' => $detalle->plan_id,
                    ];
                })->toArray(),
            ];
        })->toArray(),
        'estados' => $estados->map(fn($e) => ['estado_id' => $e->estado_id, 'descripcion' => $e->descripcion])->toArray(),
        'clientes' => $clientes->map(fn($c) => ['cliente_id' => $c->cliente_id, 'nombre' => $c->nombre, 'apellido' => $c->apellido])->toArray(),
        'nodos' => $nodos->map(fn($n) => ['nodo_id' => $n->nodo_id, 'descripcion' => $n->descripcion])->toArray(),
        'planes' => $planes->map(fn($p) => ['plan_id' => $p->plan_id, 'nombre' => $p->nombre, 'tecnologia_id' => $p->tecnologia_id])->toArray(),
        'tiposTecnologia' => $tiposTecnologia->map(fn($t) => ['tecnologia_id' => $t->tecnologia_id, 'descripcion' => $t->descripcion])->toArray(),
        'csrfToken' => csrf_token(),
        'urlPedidosIndex' => route('pedidos.index'),
        'urlPedidosStore' => route('pedidos.store'),
        'filtroEstadoId' => request('estado_id', ''),
        'filtroClienteId' => request('cliente_id', ''),
        'mostrarInstaladosInitial' => request('mostrar_instalados', '1'),
        'pedidoFormConfig' => [
            'pedidoId' => 'Nuevo',
            'planes' => $planes->map(fn($p) => ['plan_id' => $p->plan_id, 'nombre' => $p->nombre, 'precio' => $p->precio, 'tecnologia_id' => $p->tecnologia_id])->toArray(),
            'estadoId' => $estado->estado_id ?? 1,
            'buscarClienteUrl' => route('pedidos.buscar-cliente'),
            'consultarPadronUrl' => route('pedidos.consultar-padron'),
            'submitUrl' => route('pedidos.store'),
            'cancelUrl' => route('pedidos.index'),
            'csrfToken' => csrf_token(),
        ],
        'aprobarEstadoUrl' => route('pedidos.aprobar-estado', ':pedido'),
        'descartarEstadoUrl' => route('pedidos.descartar-estado', ':pedido'),
        'reabrirEstadoUrl' => route('pedidos.reabrir-estado', ':pedido'),
        'crearUsuarioPppoeUrl' => route('pedidos.crear-usuario-pppoe', ':pedido'),
        'crearAgendaUrl' => route('pedidos.crear-agenda', ':pedido'),
        'finalizarPedidoUrl' => route('pedidos.finalizar', ':pedido'),
    ]) !!};
</script>

@push('scripts')
<script src="{{ asset(mix('js/pedidos-list.js')) }}"></script>
@endpush
@endsection
