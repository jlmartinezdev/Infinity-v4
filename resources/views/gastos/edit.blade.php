@extends('layouts.app')

@section('title', 'Editar gasto')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar gasto</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('gastos.update', $gasto) }}" method="POST">
            @include('gastos._form', ['gasto' => $gasto])
        </form>
    </div>
</div>
@endsection
