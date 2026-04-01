@extends('layouts.app')

@section('title', 'Editar plan')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar plan</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('planes.update', $plan->plan_id) }}" method="POST">
            @include('planes._form', ['plan' => $plan, 'tecnologias' => $tecnologias, 'perfilesPppoe' => $perfilesPppoe])
        </form>
    </div>
</div>
@endsection
