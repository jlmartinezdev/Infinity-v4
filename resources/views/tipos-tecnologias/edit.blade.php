@extends('layouts.app')

@section('title', 'Editar tipo de tecnología')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar tipo de tecnología</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('tipos-tecnologias.update', $tipoTecnologia) }}" method="POST">
            @include('tipos-tecnologias._form', ['tipoTecnologia' => $tipoTecnologia])
        </form>
    </div>
</div>
@endsection
