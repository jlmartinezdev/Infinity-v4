@extends('layouts.app')

@section('title', 'Editar salida PON')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.salida-pons.index') }}" class="text-purple-600 hover:underline text-sm">&larr; Volver</a>
        <h1 class="text-2xl font-bold mt-1">Editar: {{ $salidaPon->codigo }}</h1>
    </div>

    <form action="{{ route('sistema.salida-pons.update', $salidaPon) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border p-6">
        @csrf
        @method('PUT')
        <div class="space-y-4">
            <div>
                <label for="nodo_id" class="block text-sm font-medium">Nodo *</label>
                <select name="nodo_id" id="nodo_id" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ old('nodo_id', $salidaPon->nodo_id) == $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="caja_nap_id" class="block text-sm font-medium">Caja NAP (opcional)</label>
                <select name="caja_nap_id" id="caja_nap_id" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    <option value="">— Ninguna —</option>
                    @foreach($cajas as $c)
                        <option value="{{ $c->caja_nap_id }}" {{ old('caja_nap_id', $salidaPon->caja_nap_id) == $c->caja_nap_id ? 'selected' : '' }}>{{ $c->codigo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="olt_puerto_id" class="block text-sm font-medium">Puerto OLT (opcional)</label>
                <select name="olt_puerto_id" id="olt_puerto_id" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    <option value="">— Ninguno —</option>
                    @foreach($oltPuertos ?? [] as $p)
                        <option value="{{ $p->olt_puerto_id }}" {{ old('olt_puerto_id', $salidaPon->olt_puerto_id) == $p->olt_puerto_id ? 'selected' : '' }}>
                            {{ $p->olt?->ip ?? $p->olt?->modelo ?? 'OLT #'.$p->olt_id }} — Puerto {{ $p->numero }} ({{ $p->olt?->nodo?->descripcion ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="codigo" class="block text-sm font-medium">Código *</label>
                    <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $salidaPon->codigo) }}" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                </div>
                <div>
                    <label for="puerto" class="block text-sm font-medium">Puerto</label>
                    <input type="number" name="puerto" id="puerto" value="{{ old('puerto', $salidaPon->puerto) }}" min="1" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="lat" class="block text-sm font-medium">Latitud</label>
                    <input type="number" name="lat" id="lat" value="{{ old('lat', $salidaPon->lat) }}" step="0.0000001" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                </div>
                <div>
                    <label for="lon" class="block text-sm font-medium">Longitud</label>
                    <input type="number" name="lon" id="lon" value="{{ old('lon', $salidaPon->lon) }}" step="0.0000001" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                </div>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar</button>
            <a href="{{ route('sistema.salida-pons.index') }}" class="px-4 py-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
