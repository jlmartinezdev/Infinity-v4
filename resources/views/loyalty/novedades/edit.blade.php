@extends('layouts.app')
@section('title', 'Editar novedad')
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">Editar novedad</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('loyalty.novedades.update', $novedad) }}" enctype="multipart/form-data">
            @include('loyalty.novedades._form', ['novedad' => $novedad])
        </form>
    </div>
</div>
@endsection
