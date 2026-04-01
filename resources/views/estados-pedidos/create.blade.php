@extends('layouts.app')

@section('title', 'Nuevo estado de pedido')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nuevo estado de pedido</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('estados-pedidos.store') }}" method="POST">
            @include('estados-pedidos._form', ['estadoPedido' => null, 'roles' => $roles])
        </form>
    </div>
</div>
@endsection
