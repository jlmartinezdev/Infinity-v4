@extends('layouts.app')
@section('title', 'Editar premio')
@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Editar premio</h1>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border p-6">
        <form method="POST" action="{{ route('loyalty.premios.update', $premio) }}" enctype="multipart/form-data">
            @include('loyalty.premios._form', [
                'premio' => $premio,
                'tipos' => $tipos ?? \App\Models\Premio::tipos(),
                'etiquetas' => $etiquetas ?? \App\Models\Premio::etiquetas(),
            ])
        </form>
    </div>
</div>
@endsection
