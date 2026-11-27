@extends('layouts.guest')

@section('title', 'Autorizar WhatsApp')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-lg p-6">
        <h1 class="text-lg font-semibold mb-2">WhatsApp de Infinity</h1>
        @if ($error)
            <p class="text-sm text-gray-700 dark:text-gray-200">{{ $error }}</p>
        @else
            <p class="text-sm text-gray-700 dark:text-gray-200 mb-4">
                <strong>{{ $nombre }}</strong> pide leer los chats de WhatsApp y proponer respuestas.
                Un mensaje sale solo si usted lo confirma en ese chat.
            </p>
            <form method="POST" action="{{ url('/oauth/authorize') }}" class="flex gap-3">
                @csrf
                @foreach ($params as $clave => $valor)
                    <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                @endforeach
                <button type="submit" name="accion" value="autorizar" class="px-4 py-2 rounded-lg bg-sky-600 text-white text-sm">Autorizar</button>
                <button type="submit" name="accion" value="cancelar" class="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-sm">Cancelar</button>
            </form>
        @endif
    </div>
</div>
@endsection
