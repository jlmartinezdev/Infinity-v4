@extends('layouts.app')

@section('title', 'Editar OLT')

@php
    $fc = 'mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $lb = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
@endphp

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.olts.show', $olt) }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver al OLT</a>
        <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Editar OLT</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $olt->codigo ?? $olt->marca ?? 'OLT #' . $olt->olt_id }} — modificá los datos y guardá.</p>
    </div>

    <form action="{{ route('sistema.olts.update', $olt) }}" method="POST" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        @csrf
        @method('PUT')

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Ubicación</h2>
        </div>
        <div class="p-6">
            <div>
                <label for="nodo_id" class="{{ $lb }}">Nodo <span class="text-red-500">*</span></label>
                <select name="nodo_id" id="nodo_id" required class="{{ $fc }}">
                    @foreach($nodos as $n)
                        <option value="{{ $n->nodo_id }}" {{ (string) old('nodo_id', $olt->nodo_id) === (string) $n->nodo_id ? 'selected' : '' }}>{{ $n->descripcion }}</option>
                    @endforeach
                </select>
                @error('nodo_id')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Identificación del equipo</h2>
        </div>
        <div class="grid gap-5 p-6 sm:grid-cols-2">
            <div>
                <label for="marca" class="{{ $lb }}">Marca <span class="text-red-500">*</span></label>
                <input type="text" name="marca" id="marca" value="{{ old('marca', $olt->marca) }}" required maxlength="100" class="{{ $fc }}" placeholder="Huawei, ZTE, Fiberhome…">
                @error('marca')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="codigo" class="{{ $lb }}">Código <span class="font-normal text-gray-500 dark:text-gray-400">(opcional)</span></label>
                <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $olt->codigo) }}" maxlength="50" class="{{ $fc }}" placeholder="Identificador interno">
                @error('codigo')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="modelo" class="{{ $lb }}">Modelo</label>
                <input type="text" name="modelo" id="modelo" value="{{ old('modelo', $olt->modelo) }}" maxlength="50" class="{{ $fc }}" placeholder="C320, MA5608T, SmartAX…">
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Red y tipo PON</h2>
        </div>
        <div class="grid gap-5 p-6 sm:grid-cols-2">
            <div>
                <label for="ip" class="{{ $lb }}">IP de gestión</label>
                <input type="text" name="ip" id="ip" value="{{ old('ip', $olt->ip) }}" maxlength="45" class="{{ $fc }}" placeholder="192.168.1.10" autocomplete="off">
                @error('ip')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tipo_pon" class="{{ $lb }}">Tipo PON <span class="text-red-500">*</span></label>
                <select name="tipo_pon" id="tipo_pon" required class="{{ $fc }}">
                    <option value="GPON" {{ old('tipo_pon', $olt->tipo_pon) === 'GPON' ? 'selected' : '' }}>GPON</option>
                    <option value="EPON" {{ old('tipo_pon', $olt->tipo_pon) === 'EPON' ? 'selected' : '' }}>EPON</option>
                    <option value="XG-PON" {{ old('tipo_pon', $olt->tipo_pon) === 'XG-PON' ? 'selected' : '' }}>XG-PON</option>
                </select>
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Capacidad y estado</h2>
        </div>
        <div class="grid gap-5 p-6 sm:grid-cols-2">
            <div>
                <label for="cantidad_puerto" class="{{ $lb }}">Cantidad de puertos PON</label>
                <input type="number" name="cantidad_puerto" id="cantidad_puerto" value="{{ old('cantidad_puerto', $olt->cantidad_puerto) }}" min="1" max="128" class="{{ $fc }}">
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Total de puertos físicos (1–128). Afecta salidas PON y asignaciones.</p>
            </div>
            <div>
                <label for="estado" class="{{ $lb }}">Estado</label>
                <select name="estado" id="estado" class="{{ $fc }}">
                    <option value="activo" {{ old('estado', $olt->estado) === 'activo' ? 'selected' : '' }}>Activo</option>
                    <option value="inactivo" {{ old('estado', $olt->estado) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Notas</h2>
        </div>
        <div class="p-6">
            <label for="notas" class="{{ $lb }}">Observaciones</label>
            <textarea name="notas" id="notas" rows="3" class="{{ $fc }}" placeholder="Credenciales, VLAN, ubicación en rack…">{{ old('notas', $olt->notas) }}</textarea>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/30">
            <button type="submit" class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                Guardar cambios
            </button>
            <a href="{{ route('sistema.olts.show', $olt) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
