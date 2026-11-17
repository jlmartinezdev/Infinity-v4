@extends('layouts.app')

@section('title', 'Editar perfil '.$perfil->nombre)

@php
    $cuentas = (int) ($perfil->servicio_hotspots_count ?? 0);
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <div>
        <a href="{{ route('hotspot.perfiles.index') }}" class="text-sm text-gray-600 dark:text-gray-300 underline underline-offset-2 hover:text-gray-900 dark:hover:text-gray-100">&larr; Perfiles</a>
        <h1 class="mt-2 text-xl font-bold text-gray-900 dark:text-gray-100">Editar perfil “{{ $perfil->nombre }}”</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            @if($cuentas === 0)
                Ninguna cuenta usa este perfil todavía.
            @else
                Lo {{ $cuentas === 1 ? 'usa 1 cuenta' : 'usan '.$cuentas.' cuentas' }}.
            @endif
        </p>
    </div>

    @if($cuentas > 0)
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-200" role="note">
            Al guardar, la velocidad, la cuota y los tiempos se envían a RADIUS para {{ $cuentas === 1 ? 'esa cuenta' : 'esas '.$cuentas.' cuentas' }}. Quien ya está conectado los toma cuando vuelve a entrar.
        </div>
    @endif

    <form action="{{ route('hotspot.perfiles.update', $perfil) }}" method="POST" class="space-y-4" novalidate>
        @csrf
        @method('PUT')
        @include('hotspot.perfiles._form')
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-400">Guardar cambios</button>
            <a href="{{ route('hotspot.perfiles.index') }}" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Cancelar</a>
        </div>
    </form>

    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
        @if($cuentas > 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">Para eliminar este perfil, primero cambiales el perfil a las {{ $cuentas === 1 ? 'cuenta que lo usa' : $cuentas.' cuentas que lo usan' }}.</p>
        @else
            <form action="{{ route('hotspot.perfiles.destroy', $perfil) }}" method="POST"
                onsubmit="return confirm({{ json_encode('¿Eliminar el perfil '.$perfil->nombre.'? No se puede deshacer.') }});">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">Eliminar perfil</button>
            </form>
        @endif
    </div>
</div>
@endsection
