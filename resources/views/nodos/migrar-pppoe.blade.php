@extends('layouts.app')

@section('title', 'Migrar PPPoE — ' . ($nodo->descripcion ?? 'Nodo'))

@section('content')
<div id="nodo-migrar-pppoe-app"></div>

<script>
    window.__NODO_MIGRAR_PPPOE_CONFIG__ = {!! json_encode([
        'nodo' => [
            'nodo_id' => $nodo->nodo_id,
            'descripcion' => $nodo->descripcion,
        ],
        'canEditar' => auth()->user()?->tienePermiso('referenciales.editar') ?? false,
        'urlIndex' => route('nodos.index'),
        'urlDatos' => route('nodos.migrar-pppoe.datos', $nodo->nodo_id),
        'urlServicios' => route('nodos.migrar-pppoe.servicios', $nodo->nodo_id),
        'urlPools' => route('nodos.migrar-pppoe.pools', $nodo->nodo_id),
        'urlEjecutar' => route('nodos.migrar-pppoe.ejecutar', $nodo->nodo_id),
        'csrfToken' => csrf_token(),
    ]) !!};
</script>
<script src="{{ mix('js/nodo-migrar-pppoe.js') }}"></script>
@endsection
