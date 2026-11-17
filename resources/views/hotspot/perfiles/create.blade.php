@extends('layouts.app')

@section('title', 'Nuevo perfil hotspot')

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <div>
        <a href="{{ route('hotspot.perfiles.index') }}" class="text-sm text-gray-600 dark:text-gray-300 underline underline-offset-2 hover:text-gray-900 dark:hover:text-gray-100">&larr; Perfiles</a>
        <h1 class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">Nuevo perfil hotspot</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Define la velocidad, la cuota y los tiempos de las cuentas que lo usen.</p>
    </div>

    <form action="{{ route('hotspot.perfiles.store') }}" method="POST" class="space-y-4" novalidate>
        @csrf
        @include('hotspot.perfiles._form')
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400">Crear perfil</button>
            <a href="{{ route('hotspot.perfiles.index') }}" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
        </div>
    </form>
</div>
@endsection
