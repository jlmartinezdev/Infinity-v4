@extends('layouts.app')

@section('title', 'Editar cuenta TV')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('tv-cuentas.index') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">&larr; Volver</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-2">Editar cuenta TV</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Próximo vencimiento: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $tv_cuenta->fechaVencimientoReferencia()->format('d/m/Y') }}</span>
                · {{ $tv_cuenta->etiquetaEstadoVencimiento() }}
            </p>
        </div>
        @if(auth()->user()?->tienePermiso('tv.editar'))
            <form action="{{ route('tv-cuentas.renovar', $tv_cuenta) }}" method="POST"
                onsubmit="return confirm('¿Renovar esta cuenta por 1 mes adelante?');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 text-sm shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Renovar +1 mes
                </button>
            </form>
        @endif
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
            <label for="aplicacion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Aplicación *</label>
            <select name="aplicacion" id="aplicacion" required
                    class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                @foreach($aplicaciones as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('aplicacion', $tv_cuenta->aplicacion ?? \App\Models\TvCuenta::APP_NEBULA) === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            @error('aplicacion')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
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
        <div id="bloque-nebula" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach([1, 2, 3] as $i)
                <div>
                    <label for="perfil_{{ $i }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Perfil {{ $i }} *</label>
                    <input type="text" name="perfil_{{ $i }}" id="perfil_{{ $i }}" value="{{ old('perfil_'.$i, $tv_cuenta->{'perfil_'.$i} ?: 'Perfil '.$i) }}" maxlength="120"
                           class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 campo-nebula">
                    @error('perfil_'.$i)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <label for="precio_perfil_{{ $i }}" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Precio perfil {{ $i }}</label>
                    <input type="number" name="precio_perfil_{{ $i }}" id="precio_perfil_{{ $i }}" value="{{ old('precio_perfil_'.$i, $tv_cuenta->{'precio_perfil_'.$i}) }}" min="0" step="0.01"
                           class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    @error('precio_perfil_'.$i)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div id="bloque-lumix" class="hidden">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Lumix: hasta 4 pantallas por cuenta (sin nombre de perfil).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach([1, 2, 3, 4] as $i)
                    <div>
                        <label for="precio_pantalla_{{ $i }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Precio pantalla {{ $i }}</label>
                        <input type="number" name="precio_pantalla_{{ $i }}" id="precio_pantalla_{{ $i }}" value="{{ old('precio_pantalla_'.$i, $tv_cuenta->{'precio_pantalla_'.$i}) }}" min="0" step="0.01"
                               class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        @error('precio_pantalla_'.$i)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
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
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $tv_cuenta->esLumix() ? 'Pantallas asignadas' : 'Perfiles asignados' }}</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                @if($tv_cuenta->esLumix())
                    Cuenta Lumix: hasta {{ $tv_cuenta->maxAsignaciones() }} pantallas. El mismo servicio puede repetirse en otra pantalla.
                @else
                    Cuenta Nebula: {{ $tv_cuenta->maxAsignaciones() }} perfiles editables. El mismo servicio puede repetirse en otro perfil.
                @endif
                En la tabla de servicios se actualizan cantidad_perfil_app y precio_app (promos no suman).
            </p>
        @else
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Clientes asignados (modo compatibilidad)</h2>
            <p class="text-sm text-amber-600 dark:text-amber-300 mb-4">Falta migrar la base de datos para habilitar perfiles y fecha de activación.</p>
        @endif

        @if($tv_cuenta->asignaciones->count() < $tv_cuenta->maxAsignaciones() && auth()->user()?->tienePermiso('tv.editar'))
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
                        <label for="perfil_numero" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $tv_cuenta->etiquetaTipoSlot() }} *</label>
                        @php
                            $perfilesEnUso = $tv_cuenta->asignaciones->pluck('perfil_numero')->filter()->map(fn($p) => (int) $p)->all();
                            $maxSlots = $tv_cuenta->maxAsignaciones();
                        @endphp
                        <select name="perfil_numero" id="perfil_numero" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            <option value="">Seleccionar…</option>
                            @for($i = 1; $i <= $maxSlots; $i++)
                                @if(!in_array($i, $perfilesEnUso, true))
                                    <option value="{{ $i }}">{{ $tv_cuenta->nombreSlot($i) }}</option>
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
                <div class="md:col-span-4 flex flex-wrap gap-x-6 gap-y-2">
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="es_promo" value="1" @checked(old('es_promo')) class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                        Promo (no cargar precio en el servicio)
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="tvbox_comodato" value="1" @checked(old('tvbox_comodato')) class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                        Se entrega TV box en comodato
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
                        @if($a->servicio?->plan)
                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200 mr-1 align-middle"
                                title="{{ $a->servicio->plan->nombre }}">{{ $a->servicio->plan->iniciales() }}</span>
                        @endif
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $a->servicio?->cliente?->nombre }} {{ $a->servicio?->cliente?->apellido }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $a->servicio?->cliente?->cedula }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">
                            Servicio #{{ $a->servicio_id }}
                            @if($a->es_promo ?? false)
                                <span class="ml-1 text-amber-600 dark:text-amber-400">(Promo)</span>
                            @endif
                            @if($a->tvbox_comodato ?? false)
                                <span class="ml-1 text-blue-600 dark:text-blue-400">(TV box comodato)</span>
                            @endif
                            @if(!($a->es_promo ?? false) && isset($a->precio_aplicado) && (float) $a->precio_aplicado > 0)
                                <span class="ml-1">· Gs. {{ number_format((float) $a->precio_aplicado, 0, ',', '.') }}</span>
                            @endif
                        </p>
                        @if($asignacionPerfilesV2 ?? false)
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">
                                {{ $tv_cuenta->nombreSlot((int) ($a->perfil_numero ?? 0)) }}
                                @if(!($a->es_promo ?? false) && $a->perfil_numero && $tv_cuenta->precioSlot((int) $a->perfil_numero) !== null)
                                    (Gs. {{ number_format((float) $tv_cuenta->precioSlot((int) $a->perfil_numero), 0, ',', '.') }})
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
    const selectApp = document.getElementById('aplicacion');
    const bloqueNebula = document.getElementById('bloque-nebula');
    const bloqueLumix = document.getElementById('bloque-lumix');
    const camposNebula = document.querySelectorAll('.campo-nebula');

    if (selectApp && bloqueNebula && bloqueLumix) {
        const actualizarApp = () => {
            const esLumix = selectApp.value === '{{ \App\Models\TvCuenta::APP_LUMIX }}';
            bloqueNebula.classList.toggle('hidden', esLumix);
            bloqueLumix.classList.toggle('hidden', !esLumix);
            camposNebula.forEach((input) => {
                input.required = !esLumix;
            });
        };
        selectApp.addEventListener('change', actualizarApp);
        actualizarApp();
    }

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
