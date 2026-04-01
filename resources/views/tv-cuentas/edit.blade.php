@extends('layouts.app')

@section('title', 'Editar cuenta TV')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('tv-cuentas.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Editar cuenta TV</h1>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 text-sm">{{ session('error') }}</div>
    @endif

    <form action="{{ route('tv-cuentas.update', $tv_cuenta) }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-4 mb-6">
        @csrf
        @method('PUT')
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre interno (opcional)</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $tv_cuenta->nombre) }}" maxlength="120"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            @error('nombre')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="usuario_app" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario de la app *</label>
            <input type="text" name="usuario_app" id="usuario_app" value="{{ old('usuario_app', $tv_cuenta->usuario_app) }}" required maxlength="255"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" autocomplete="off">
            @error('usuario_app')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nueva contraseña</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Dejá en blanco para no cambiar la contraseña guardada.</p>
            <input type="password" name="password" id="password"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" autocomplete="new-password">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="vencimiento_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vencimiento de pago *</label>
            <input type="date" name="vencimiento_pago" id="vencimiento_pago" value="{{ old('vencimiento_pago', $tv_cuenta->vencimiento_pago?->format('Y-m-d')) }}" required
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            @error('vencimiento_pago')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
            <textarea name="notas" id="notas" rows="3" maxlength="2000" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('notas', $tv_cuenta->notas) }}</textarea>
            @error('notas')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar datos</button>
            <a href="{{ route('tv-cuentas.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
    @if(auth()->user()?->tienePermiso('tv.editar'))
        <div class="mb-6 flex justify-end">
            <form action="{{ route('tv-cuentas.destroy', $tv_cuenta) }}" method="POST" onsubmit="return confirm('¿Eliminar esta cuenta TV y todas sus asignaciones?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg text-sm font-medium border border-red-200 dark:border-red-800">Eliminar cuenta</button>
            </form>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Clientes asignados (1 dispositivo c/u)</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Máximo 3 clientes distintos por cuenta (3 dispositivos en total).</p>

        @if($tv_cuenta->asignaciones->count() < \App\Models\TvCuenta::MAX_ASIGNACIONES && auth()->user()?->tienePermiso('tv.editar'))
            <form action="{{ route('tv-cuentas.asignaciones.store', $tv_cuenta) }}" method="POST" class="flex flex-col sm:flex-row gap-3 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                @csrf
                <div class="flex-1">
                    <label for="cliente_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Agregar cliente</label>
                    <select name="cliente_id" id="cliente_id" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Seleccionar…</option>
                        @php
                            $idsAsignados = $tv_cuenta->asignaciones->pluck('cliente_id')->all();
                        @endphp
                        @foreach($clientes as $cl)
                            @if(!in_array($cl->cliente_id, $idsAsignados, true))
                                <option value="{{ $cl->cliente_id }}">{{ $cl->nombre }} {{ $cl->apellido }} ({{ $cl->cedula }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 whitespace-nowrap">Asignar</button>
                </div>
            </form>
        @endif

        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($tv_cuenta->asignaciones as $a)
                <li class="py-3 flex items-center justify-between gap-4">
                    <div>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $a->cliente->nombre }} {{ $a->cliente->apellido }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $a->cliente->cedula }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">1 dispositivo</p>
                    </div>
                    @if(auth()->user()?->tienePermiso('tv.editar'))
                        <form action="{{ route('tv-cuentas.asignaciones.destroy', [$tv_cuenta, $a]) }}" method="POST" onsubmit="return confirm('¿Quitar este cliente de la cuenta?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Quitar</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">Nadie asignado aún.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
