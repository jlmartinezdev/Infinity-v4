@extends('layouts.app')
@section('title', 'Nueva novedad')
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-gray-100">Nueva novedad</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form method="POST" action="{{ route('loyalty.novedades.store') }}" enctype="multipart/form-data">
            @include('loyalty.novedades._form')
        </form>
    </div>
</div>
@endsection
