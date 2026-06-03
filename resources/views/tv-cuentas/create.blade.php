@extends('layouts.app')

@section('title', 'Nueva cuenta TV')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('tv-cuentas.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Nueva cuenta TV</h1>
    </div>

    @if(request('cliente_id'))
        <div class="mb-4 p-4 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-200 text-sm">
            Estás creando una cuenta TV desde la ficha de un cliente. Al guardar, se abrirá la pantalla para asignar ese cliente.
        </div>
    @endif

    <form action="{{ route('tv-cuentas.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        @if(request('cliente_id'))
            <input type="hidden" name="cliente_id_prefill" value="{{ request('cliente_id') }}">
        @endif
        <div>
            <label for="aplicacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aplicación *</label>
            <select name="aplicacion" id="aplicacion" required
                    class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach($aplicaciones as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('aplicacion', \App\Models\TvCuenta::APP_NEBULA) === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nebula: 3 perfiles con nombre. Lumix: 4 pantallas por cuenta (sin nombre de perfil).</p>
            @error('aplicacion')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre interno (opcional)</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" maxlength="120"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            @error('nombre')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="usuario_app" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario de la app *</label>
            <input type="text" name="usuario_app" id="usuario_app" value="{{ old('usuario_app') }}" required maxlength="255"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" autocomplete="off">
            @error('usuario_app')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contraseña *</label>
            <input type="text" name="password" id="password" required
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" autocomplete="new-password">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="dia_aviso_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Día de aviso de vencimiento (mensual) *</label>
            <input type="number" name="dia_aviso_vencimiento" id="dia_aviso_vencimiento" value="{{ old('dia_aviso_vencimiento') }}" min="1" max="31" required
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ejemplo: 5 significa aviso el día 5 de cada mes.</p>
            @error('dia_aviso_vencimiento')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div id="bloque-nebula" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach([1, 2, 3] as $i)
                <div>
                    <label for="perfil_{{ $i }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Perfil {{ $i }} *</label>
                    <input type="text" name="perfil_{{ $i }}" id="perfil_{{ $i }}" value="{{ old('perfil_'.$i, 'Perfil '.$i) }}" maxlength="120"
                           class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 campo-nebula">
                    @error('perfil_'.$i)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <label for="precio_perfil_{{ $i }}" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Precio perfil {{ $i }}</label>
                    <input type="number" name="precio_perfil_{{ $i }}" id="precio_perfil_{{ $i }}" value="{{ old('precio_perfil_'.$i) }}" min="0" step="0.01"
                           class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('precio_perfil_'.$i)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>

        <div id="bloque-lumix" class="hidden">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Lumix no usa nombres de perfil. Cada cuenta admite hasta 4 pantallas simultáneas.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach([1, 2, 3, 4] as $i)
                    <div>
                        <label for="precio_pantalla_{{ $i }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Precio pantalla {{ $i }}</label>
                        <input type="number" name="precio_pantalla_{{ $i }}" id="precio_pantalla_{{ $i }}" value="{{ old('precio_pantalla_'.$i) }}" min="0" step="0.01"
                               class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('precio_pantalla_'.$i)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas</label>
            <textarea name="notas" id="notas" rows="3" maxlength="2000" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">{{ old('notas') }}</textarea>
            @error('notas')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Guardar</button>
            <a href="{{ route('tv-cuentas.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('aplicacion');
    const bloqueNebula = document.getElementById('bloque-nebula');
    const bloqueLumix = document.getElementById('bloque-lumix');
    const camposNebula = document.querySelectorAll('.campo-nebula');

    const actualizar = () => {
        const esLumix = select.value === '{{ \App\Models\TvCuenta::APP_LUMIX }}';
        bloqueNebula.classList.toggle('hidden', esLumix);
        bloqueLumix.classList.toggle('hidden', !esLumix);
        camposNebula.forEach((input) => {
            input.required = !esLumix;
        });
    };

    select.addEventListener('change', actualizar);
    actualizar();
});
</script>
@endpush
