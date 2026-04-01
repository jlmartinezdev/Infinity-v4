@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Hola, {{ $user->name ?? 'Usuario' }}</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">Elegí un módulo para continuar.</p>
    </div>

    @if(empty($links))
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-6 text-amber-900 dark:text-amber-100 text-sm">
            No tenés acceso a ningún módulo todavía. Pedile a un administrador que te asigne permisos o rol.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($links as $link)
                <a href="{{ url($link['path']) }}"
                   class="flex flex-col rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm hover:border-green-400 dark:hover:border-green-600 hover:shadow-md transition-all text-left">
                    @if(!empty($link['grupo']))
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">{{ $link['grupo'] }}</span>
                    @endif
                    <span class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
