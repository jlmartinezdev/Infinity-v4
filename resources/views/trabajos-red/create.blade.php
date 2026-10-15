@extends('layouts.app')

@section('title', 'Nuevo trabajo de red')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nuevo trabajo de red</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('trabajos-red.store') }}" method="POST" enctype="multipart/form-data">
            @include('trabajos-red._form', ['trabajo' => null])
        </form>
    </div>
</div>
@endsection
