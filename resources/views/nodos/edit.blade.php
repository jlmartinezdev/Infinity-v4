@extends('layouts.app')

@section('title', 'Editar nodo')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Editar nodo</h1>
        <a href="{{ route('nodos.migrar-pppoe', $nodo->nodo_id) }}"
            class="inline-flex items-center px-4 py-2 border border-sky-600 text-sky-700 dark:text-sky-400 dark:border-sky-500 rounded-lg font-medium hover:bg-sky-50 dark:hover:bg-sky-900/20">
            Migrar / copiar PPPoE entre RB
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('nodos.update', $nodo->nodo_id) }}" method="POST">
            @include('nodos._form', ['nodo' => $nodo])
        </form>
    </div>
</div>
@endsection
