@extends('layouts.app')

@section('title', 'Editar modelo OLT')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar modelo: {{ $oltModelo->nombre }}</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('sistema.olt-modelos.update', $oltModelo) }}" method="POST" enctype="multipart/form-data">
            @include('sistema.olt-modelos._form', ['oltModelo' => $oltModelo, 'marcas' => $marcas])
        </form>
    </div>
</div>
@endsection
