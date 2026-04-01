@extends('layouts.app')

@section('title', 'Editar rol')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar rol</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('roles.update', $role) }}" method="POST">
            @include('roles._form', ['role' => $role])
        </form>
    </div>
</div>
@endsection
