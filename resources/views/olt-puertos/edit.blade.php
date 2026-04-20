@extends('layouts.app')

@section('title', 'Editar puerto PON')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.show', $oltPuerto->olt) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver al OLT</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Editar puerto {{ $oltPuerto->numero }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">OLT: {{ $oltPuerto->olt->codigo ?? $oltPuerto->olt->ip ?? $oltPuerto->olt->modelo ?? '#' . $oltPuerto->olt_id }} — {{ $oltPuerto->olt->marca ?? '—' }}</p>
    </div>

    <form action="{{ route('sistema.olt-puertos.update', $oltPuerto) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        @csrf
        @method('PUT')

        <div class="space-y-4">
            <div>
                <label for="numero" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de puerto *</label>
                <input type="number" name="numero" id="numero" value="{{ old('numero', $oltPuerto->numero) }}" required min="1" max="128"
                    class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('numero')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tipo_pon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo PON *</label>
                <select name="tipo_pon" id="tipo_pon" required class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="GPON" {{ old('tipo_pon', $oltPuerto->tipo_pon) === 'GPON' ? 'selected' : '' }}>GPON</option>
                    <option value="EPON" {{ old('tipo_pon', $oltPuerto->tipo_pon) === 'EPON' ? 'selected' : '' }}>EPON</option>
                    <option value="XG-PON" {{ old('tipo_pon', $oltPuerto->tipo_pon) === 'XG-PON' ? 'selected' : '' }}>XG-PON</option>
                </select>
            </div>
            <div>
                <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estado</label>
                <select name="estado" id="estado" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="activo" {{ old('estado', $oltPuerto->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ old('estado', $oltPuerto->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
            <div>
                <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
                <textarea name="notas" id="notas" rows="2" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('notas', $oltPuerto->notas) }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar cambios</button>
            <a href="{{ route('sistema.olts.show', $oltPuerto->olt) }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
