@extends('layouts.app')

@section('title', 'Nuevo plan')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nuevo plan</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('planes.store') }}" method="POST">
            @include('planes._form', ['plan' => null, 'tecnologias' => $tecnologias, 'perfilesPppoe' => $perfilesPppoe])
        </form>
    </div>
</div>
@endsection
