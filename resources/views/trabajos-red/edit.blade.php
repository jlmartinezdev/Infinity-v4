@extends('layouts.app')

@section('title', 'Editar trabajo de red')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar trabajo #{{ $trabajo->id }}</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('trabajos-red.update', $trabajo) }}" method="POST" enctype="multipart/form-data">
            @include('trabajos-red._form', ['trabajo' => $trabajo])
        </form>
        @if(auth()->user()?->tienePermiso('trabajos-red.eliminar'))
            <form id="trabajo-eliminar-form" action="{{ route('trabajos-red.destroy', $trabajo) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>
</div>
@endsection
