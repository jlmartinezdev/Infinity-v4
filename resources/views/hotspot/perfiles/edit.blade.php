@extends('layouts.app')

@section('title', 'Editar perfil Hotspot')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar perfil Hotspot</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('hotspot.perfiles.update', $perfil) }}" method="POST">
            @csrf
            @method('PUT')
            @include('hotspot.perfiles._form', ['perfil' => $perfil])
            <div class="flex gap-3 mt-6">
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Actualizar</button>
                <a href="{{ route('hotspot.perfiles.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
