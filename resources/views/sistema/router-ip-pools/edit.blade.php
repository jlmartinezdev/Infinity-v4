@extends('layouts.app')

@section('title', 'Editar pool de IP')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar pool de IP</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('sistema.router-ip-pools.update', $pool->pool_id) }}" method="POST">
            @include('sistema.router-ip-pools._form', ['pool' => $pool, 'routers' => $routers])
        </form>
    </div>
</div>
@endsection
