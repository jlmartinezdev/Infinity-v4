@if($fila['cliente_id'])
    <a href="{{ route('clientes.detalle', $fila['cliente_id']) }}?tab=red" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
        {{ $fila['cliente'] }}
    </a>
@else
    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $fila['cliente'] }}</span>
@endif
