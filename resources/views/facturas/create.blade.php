@extends('layouts.app')

@section('title', 'Nueva factura')

@section('content')
<div class="max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Nueva factura</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('facturas.store') }}" method="POST">
            @include('facturas._form', ['factura' => null])
        </form>
    </div>
</div>
@endsection
