@extends('layouts.app')

@section('title', 'Nuevo proveedor')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nuevo proveedor</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('proveedores.store') }}" method="POST">
            @include('proveedores._form', ['proveedor' => null])
        </form>
    </div>
</div>
@endsection
