@extends('layouts.app')
@section('title', 'Nuevo premio')
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Nuevo premio</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border p-6">
        <form method="POST" action="{{ route('loyalty.premios.store') }}" enctype="multipart/form-data">
            @include('loyalty.premios._form', ['tipos' => $tipos ?? \App\Models\Premio::tipos()])
        </form>
    </div>
</div>
@endsection
