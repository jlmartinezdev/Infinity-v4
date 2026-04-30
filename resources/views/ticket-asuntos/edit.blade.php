@extends('layouts.app')

@section('title', 'Editar asunto de ticket')

@section('content')
<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Editar asunto de ticket</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <form action="{{ route('ticket-asuntos.update', $ticketAsunto) }}" method="POST">
            @include('ticket-asuntos._form', ['ticketAsunto' => $ticketAsunto])
        </form>
    </div>
</div>
@endsection
