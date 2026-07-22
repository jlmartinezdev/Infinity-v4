@extends('layouts.app')

@section('title', 'Nuevo modelo MikroTik')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nuevo modelo MikroTik</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('sistema.router-modelos.store') }}" method="POST" enctype="multipart/form-data">
            @include('sistema.router-modelos._form', ['routerModelo' => null, 'series' => $series])
        </form>
    </div>
</div>
@endsection
