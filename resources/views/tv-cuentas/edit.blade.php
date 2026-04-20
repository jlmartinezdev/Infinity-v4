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
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contraseña *</label>
            <input type="text" name="password" id="password" value="{{ old('password', $tv_cuenta->password) }}" required
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" autocomplete="new-password">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="dia_aviso_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Día de aviso de vencimiento (mensual) *</label>
            <input type="number" name="dia_aviso_vencimiento" id="dia_aviso_vencimiento" value="{{ old('dia_aviso_vencimiento', $tv_cuenta->dia_aviso_vencimiento ?? $tv_cuenta->vencimiento_pago?->day) }}" min="1" max="31" required
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se usará este día todos los meses para el próximo aviso de vencimiento.</p>
            @error('dia_aviso_vencimiento')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label for="perfil_1" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Perfil 1 *</label>
                <input type="text" name="perfil_1" id="perfil_1" value="{{ old('perfil_1', $tv_cuenta->perfil_1 ?: 'Perfil 1') }}" required maxlength="120"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('perfil_1')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <label for="precio_perfil_1" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Precio perfil 1</label>
                <input type="number" name="precio_perfil_1" id="precio_perfil_1" value="{{ old('precio_perfil_1', $tv_cuenta->precio_perfil_1) }}" min="0" step="0.01"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('precio_perfil_1')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="perfil_2" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Perfil 2 *</label>
                <input type="text" name="perfil_2" id="perfil_2" value="{{ old('perfil_2', $tv_cuenta->perfil_2 ?: 'Perfil 2') }}" required maxlength="120"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('perfil_2')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <label for="precio_perfil_2" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Precio perfil 2</label>
                <input type="number" name="precio_perfil_2" id="precio_perfil_2" value="{{ old('precio_perfil_2', $tv_cuenta->precio_perfil_2) }}" min="0" step="0.01"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('precio_perfil_2')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="perfil_3" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Perfil 3 *</label>
                <input type="text" name="perfil_3" id="perfil_3" value="{{ old('perfil_3', $tv_cuenta->perfil_3 ?: 'Perfil 3') }}" required maxlength="120"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('perfil_3')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <label for="precio_perfil_3" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Precio perfil 3</label>
                <input type="number" name="precio_perfil_3" id="precio_perfil_3" value="{{ old('precio_perfil_3', $tv_cuenta->precio_perfil_3) }}" min="0" step="0.01"
                       class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @error('precio_perfil_3')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
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
        @if($asignacionPerfilesV2 ?? false)
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Perfiles asignados</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Cada cuenta tiene 3 perfiles editables. Podés asignar varios perfiles al mismo servicio; en la tabla de servicios se actualizan cantidad_perfil_app (cantidad de asignaciones) y precio_app (suma de precios cargados; las promos no suman).</p>
        @else
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Clientes asignados (modo compatibilidad)</h2>
            <p class="text-sm text-amber-600 dark:text-amber-300 mb-4">Falta migrar la base de datos para habilitar perfiles y fecha de activación.</p>
        @endif

        @if($tv_cuenta->asignaciones->count() < \App\Models\TvCuenta::MAX_ASIGNACIONES && auth()->user()?->tienePermiso('tv.editar'))
            <form action="{{ route('tv-cuentas.asignaciones.store', $tv_cuenta) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                @csrf
                <div class="{{ ($asignacionPerfilesV2 ?? false) ? 'md:col-span-2' : 'md:col-span-3' }}">
                    <label for="cliente_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cliente</label>
                    <select name="cliente_id" id="cliente_id" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Seleccionar…</option>
                        @foreach($clientes as $cl)
                            <option value="{{ $cl->cliente_id }}" @selected((int) old('cliente_id', request('cliente_id')) === (int) $cl->cliente_id)>{{ $cl->nombre }} {{ $cl->apellido }} ({{ $cl->cedula }})</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="{{ ($asignacionPerfilesV2 ?? false) ? 'md:col-span-2' : 'md:col-span-3' }}">
                    <label for="servicio_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Servicio del cliente</label>
                    <select name="servicio_id" id="servicio_id" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Seleccionar servicio…</option>
                        @foreach($servicios as $srv)
                            <option
                                value="{{ $srv->servicio_id }}"
                                data-cliente-id="{{ $srv->cliente_id }}"
                                @selected((int) old('servicio_id') === (int) $srv->servicio_id)
                            >
                                #{{ $srv->servicio_id }} - {{ $srv->cliente?->nombre }} {{ $srv->cliente?->apellido }} ({{ $srv->cliente?->cedula }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si el cliente tiene varios servicios, elegí uno. El mismo servicio puede repetirse en otro perfil de esta cuenta.</p>
                    @error('servicio_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @if($asignacionPerfilesV2 ?? false)
                    <div>
                        <label for="perfil_numero" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Perfil *</label>
                        @php
                            $perfilesEnUso = $tv_cuenta->asignaciones->pluck('perfil_numero')->filter()->map(fn($p) => (int) $p)->all();
                            $nombresPerfiles = [
                                1 => $tv_cuenta->perfil_1 ?: 'Perfil 1',
                                2 => $tv_cuenta->perfil_2 ?: 'Perfil 2',
                                3 => $tv_cuenta->perfil_3 ?: 'Perfil 3',
                            ];
                        @endphp
                        <select name="perfil_numero" id="perfil_numero" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">Seleccionar…</option>
                            @for($i = 1; $i <= 3; $i++)
                                @if(!in_array($i, $perfilesEnUso, true))
                                    <option value="{{ $i }}">Perfil {{ $i }} - {{ $nombresPerfiles[$i] }}</option>
                                @endif
                            @endfor
                        </select>
                        @error('perfil_numero')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="fecha_activacion" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha activación *</label>
                        <input type="date" name="fecha_activacion" id="fecha_activacion" required value="{{ old('fecha_activacion', now()->format('Y-m-d')) }}"
                               class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        @error('fecha_activacion')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="md:col-span-4">
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="es_promo" value="1" @checked(old('es_promo')) class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                        Promo (no cargar precio en el servicio)
                    </label>
                </div>
                <div class="md:col-span-4 flex items-end">
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 whitespace-nowrap">Asignar</button>
                </div>
            </form>
        @endif

        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($tv_cuenta->asignaciones as $a)
                <li class="py-3 flex items-center justify-between gap-4">
                    <div>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $a->servicio?->cliente?->nombre }} {{ $a->servicio?->cliente?->apellido }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $a->servicio?->cliente?->cedula }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">
                            Servicio #{{ $a->servicio_id }}
                            @if($a->es_promo ?? false)
                                <span class="ml-1 text-amber-600 dark:text-amber-400">(Promo)</span>
                            @elseif(isset($a->precio_aplicado) && (float) $a->precio_aplicado > 0)
                                <span class="ml-1">· Gs. {{ number_format((float) $a->precio_aplicado, 0, ',', '.') }}</span>
                            @endif
                        </p>
                        @if($asignacionPerfilesV2 ?? false)
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">
                                Perfil {{ $a->perfil_numero ?? '—' }}
                                @if($a->perfil_numero === 1)
                                    - {{ $tv_cuenta->perfil_1 ?: 'Perfil 1' }}
                                    @if($tv_cuenta->precio_perfil_1 !== null)
                                        (Gs. {{ number_format((float) $tv_cuenta->precio_perfil_1, 0, ',', '.') }})
                                    @endif
                                @elseif($a->perfil_numero === 2)
                                    - {{ $tv_cuenta->perfil_2 ?: 'Perfil 2' }}
                                    @if($tv_cuenta->precio_perfil_2 !== null)
                                        (Gs. {{ number_format((float) $tv_cuenta->precio_perfil_2, 0, ',', '.') }})
                                    @endif
                                @elseif($a->perfil_numero === 3)
                                    - {{ $tv_cuenta->perfil_3 ?: 'Perfil 3' }}
                                    @if($tv_cuenta->precio_perfil_3 !== null)
                                        (Gs. {{ number_format((float) $tv_cuenta->precio_perfil_3, 0, ',', '.') }})
                                    @endif
                                @endif
                                @if($a->fecha_activacion)
                                    | Activado: {{ $a->fecha_activacion->format('d/m/Y') }}
                                @endif
                            </p>
                        @else
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">1 dispositivo</p>
                        @endif
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const clienteSelect = document.getElementById('cliente_id');
    const servicioSelect = document.getElementById('servicio_id');
    if (!clienteSelect || !servicioSelect) return;

    const options = Array.from(servicioSelect.querySelectorAll('option[data-cliente-id]'));

    const filtrarServicios = () => {
        const clienteId = clienteSelect.value;
        const selected = servicioSelect.value;
        let selectedVisible = false;

        options.forEach((option) => {
            const visible = !clienteId || option.dataset.clienteId === clienteId;
            option.hidden = !visible;
            if (visible && option.value === selected) {
                selectedVisible = true;
            }
        });

        if (!selectedVisible) {
            servicioSelect.value = '';
        }
    };

    clienteSelect.addEventListener('change', filtrarServicios);
    filtrarServicios();
});
</script>
@endpush
@endsection
