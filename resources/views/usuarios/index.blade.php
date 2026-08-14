@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@section('content')
@php
    $tipo = $tipo ?? 'sistema';
    $esClientes = $tipo === 'clientes';
@endphp
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Gestión de Usuarios</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $esClientes ? 'Usuarios de la app móvil (clientes) y permisos globales' : 'Personal del sistema y permisos individuales' }}
            </p>
        </div>
        @if(! $esClientes)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('liquidacion.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                    Liquidación de sueldo
                </a>
                <a href="{{ route('usuarios.sesiones') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                    Sesiones activas
                </a>
                <button type="button"
                    onclick="event.preventDefault(); event.stopPropagation(); abrirModalCrear(event); return false;"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 dark:hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    + Nuevo Usuario
                </button>
            </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="mb-4 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex gap-4" aria-label="Tipo de usuario">
            <a href="{{ route('usuarios.index', ['tipo' => 'sistema']) }}"
               class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium {{ ! $esClientes ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Personal del sistema
            </a>
            <a href="{{ route('usuarios.index', ['tipo' => 'clientes']) }}"
               class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium {{ $esClientes ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                Clientes app
                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $esClientes ? $usuarios->count() : ($totalClientesPortal ?? '') }}</span>
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-stretch">
        {{-- Lista --}}
        <div class="flex flex-col">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm flex flex-col h-full">
                <form method="GET" action="{{ route('usuarios.index') }}" class="mb-3 space-y-2">
                    <input type="hidden" name="tipo" value="{{ $tipo }}">
                    <input type="search" name="buscar" value="{{ request('buscar') }}"
                           placeholder="{{ $esClientes ? 'Buscar nombre, CI, email…' : 'Buscar nombre o email…' }}"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <select name="estado" onchange="this.form.submit()"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                        <option value="todos" @selected(request('estado', 'todos') === 'todos')>Todos los estados</option>
                        <option value="activo" @selected(request('estado') === 'activo')>Activo</option>
                        <option value="pendiente_aprobacion" @selected(request('estado') === 'pendiente_aprobacion')>Pendiente</option>
                        <option value="suspendido" @selected(request('estado') === 'suspendido')>Suspendido</option>
                    </select>
                </form>

                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                    {{ $esClientes ? 'Clientes' : 'Usuarios' }} ({{ $usuarios->count() }})
                </h3>

                <div class="space-y-2 flex-1 overflow-y-auto max-h-[calc(100vh-340px)]">
                    @forelse ($usuarios as $usuario)
                        @if($esClientes)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $usuario->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        CI {{ $usuario->cliente->cedula ?? '—' }} · {{ $usuario->email }}
                                    </div>
                                    <span class="inline-flex mt-1 px-2 py-0.5 text-xs font-medium rounded-full {{ $usuario->estado === 'activo' ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300' }}">
                                        {{ ucfirst(str_replace('_', ' ', $usuario->estado)) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 ml-2">
                                    <button type="button"
                                            onclick="event.preventDefault(); abrirModalEditar({{ $usuario->usuario_id }}, event); return false;"
                                            class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded"
                                            title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <form action="{{ route('usuarios.destroy', $usuario->usuario_id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este usuario portal?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded" title="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('usuarios.index', ['tipo' => 'sistema', 'usuario_id' => $usuario->usuario_id, 'buscar' => request('buscar'), 'estado' => request('estado')]) }}"
                               class="group relative flex items-center justify-between p-3 rounded-lg border transition-all {{ $usuarioSeleccionado && $usuarioSeleccionado->usuario_id == $usuario->usuario_id ? 'bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-600' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                <div class="flex items-start justify-between w-full">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $usuario->name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $usuario->email }}</div>
                                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ $usuario->rol->descripcion ?? 'Sin rol' }}</span>
                                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $usuario->estado === 'activo' ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : ($usuario->estado === 'pendiente_aprobacion' ? 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300') }}">
                                                {{ ucfirst(str_replace('_', ' ', $usuario->estado)) }}
                                            </span>
                                            @if($usuario->estado === 'pendiente_aprobacion' && $esAdmin)
                                                <button type="button"
                                                        onclick="event.preventDefault(); event.stopPropagation(); abrirModalAprobar({{ $usuario->usuario_id }}, '{{ $usuario->name }}', '{{ $usuario->email }}', event); return false;"
                                                        class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/40 border border-green-200 dark:border-green-700 rounded hover:bg-green-100 dark:hover:bg-green-800/50"
                                                        title="Aprobar usuario">
                                                    Aprobar
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 ml-3">
                                        <button type="button"
                                                onclick="event.preventDefault(); event.stopPropagation(); abrirModalEditar({{ $usuario->usuario_id }}, event); return false;"
                                                class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded"
                                                title="Editar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </button>
                                        <form action="{{ route('usuarios.destroy', $usuario->usuario_id) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este usuario?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded" title="Eliminar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </a>
                        @endif
                    @empty
                        <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                            @if($esClientes)
                                No hay usuarios cliente. Se crean al sincronizar clientes o al primer login en la app.
                            @else
                                No hay usuarios.
                                <button type="button" onclick="abrirModalCrear(event)" class="text-blue-600 dark:text-blue-400 hover:underline">Crear uno</button>.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Panel derechos --}}
        <div class="lg:col-span-2 flex flex-col">
            @if($esClientes)
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm flex flex-col h-full">
                    <div class="mb-4 border-b border-gray-200 dark:border-gray-700 pb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Permisos de la app (todos los clientes)</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            El mismo paquete se aplica a los <strong>{{ $totalClientesPortal }}</strong> usuarios portal.
                            Usuario de acceso: número de documento. La contraseña se carga al aprobar la solicitud de alta (clave PLUS); si no hubo alta, queda vacía.
                        </p>
                    </div>

                    <form action="{{ route('usuarios.update-permisos-clientes') }}" method="POST" id="formPermisosClientes">
                        @csrf
                        <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20 p-3 mb-4 text-sm text-amber-900 dark:text-amber-200">
                            Al guardar se actualizan el rol <em>Cliente App</em> y todos los usuarios cliente.
                        </div>
                        <div class="space-y-4 max-h-[calc(100vh-360px)] overflow-y-auto pe-1">
                            @include('usuarios._permisos-arbol', [
                                'arbolPermisos' => $arbolPermisosPortal,
                                'permisosUsuario' => $permisosPortalGlobales,
                            ])
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="submit"
                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-offset-gray-900"
                                    onclick="return confirm('¿Aplicar estos permisos a todos los clientes de la app?');">
                                Guardar y aplicar a todos
                            </button>
                        </div>
                    </form>
                </div>
            @elseif($usuarioSeleccionado)
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm flex flex-col h-full">
                    <div class="mb-4 border-b border-gray-200 dark:border-gray-700 pb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Configuración de permisos</h2>
                        <div class="mt-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $usuarioSeleccionado->name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $usuarioSeleccionado->email }}</div>
                        </div>
                    </div>

                    @php
                        $rolesConPermisos = \App\Models\Rol::with('permisos')->get()
                            ->filter(fn ($r) => strcasecmp($r->descripcion, \App\Services\ClientePortalUserService::ROL_CLIENTE_APP) !== 0)
                            ->mapWithKeys(fn ($r) => [
                                $r->descripcion => \App\Support\PermisosCatalogo::migrarPermisos($r->permisos->pluck('codigo')->toArray()),
                            ]);
                        $arbolPermisos = \App\Support\PermisosCatalogo::arbolParaUi();
                        $permisosUsuario = \App\Support\PermisosCatalogo::migrarPermisos($usuarioSeleccionado->permisos ?? []);
                    @endphp
                    <script>
                        window.__ROL_PERMISOS__ = @json($rolesConPermisos);
                    </script>
                    <form action="{{ route('usuarios.update-permisos', $usuarioSeleccionado->usuario_id) }}" method="POST" id="formPermisos">
                        @csrf
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-3">
                                    <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300">Paquetes de Rol</h4>
                                </div>
                                <p class="text-xs text-blue-700 dark:text-blue-400 mb-3">Selecciona un rol para aplicar su paquete. Luego ajustá cada módulo.</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach($rolesConPermisos as $nombreRol => $codigos)
                                        <button type="button"
                                                data-rol="{{ $nombreRol }}"
                                                onclick="aplicarPermisosRol((window.__ROL_PERMISOS__ && window.__ROL_PERMISOS__[this.getAttribute('data-rol')]) || [])"
                                                class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600">
                                            {{ $nombreRol }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4 max-h-[calc(100vh-320px)] overflow-y-auto pe-1">
                            @include('usuarios._permisos-arbol', [
                                'arbolPermisos' => $arbolPermisos,
                                'permisosUsuario' => $permisosUsuario,
                            ])
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                                Guardar permisos
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm flex items-center justify-center h-full">
                    <p class="text-gray-500 dark:text-gray-400">Selecciona un usuario para gestionar sus permisos</p>
                </div>
            @endif
        </div>
    </div>
</div>

<div id="usuario-management-app"></div>

@push('scripts')
<script>
    window.__USUARIO_MANAGEMENT_CONFIG__ = {!! json_encode([
        'csrfToken' => csrf_token(),
        'roles' => $rolesStaff,
        'storeUrl' => route('usuarios.store'),
        'updateUrl' => route('usuarios.update', ':usuario'),
        'aprobarUrl' => route('usuarios.aprobar', ':usuario'),
        'editDataUrl' => route('usuarios.edit-data', ':usuario'),
    ]) !!};

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('permisos-granular');
        if (!root) return;

        root.querySelectorAll('.permisos-toggle-grupo').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const grupo = btn.getAttribute('data-grupo');
                const boxes = root.querySelectorAll(`.permiso-checkbox[data-grupo="${grupo}"]`);
                const allChecked = Array.from(boxes).every((b) => b.checked);
                boxes.forEach((b) => { b.checked = !allChecked; });
            });
        });

        root.querySelectorAll('.permisos-toggle-item').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const grupo = btn.getAttribute('data-grupo');
                const item = btn.getAttribute('data-item');
                const boxes = root.querySelectorAll(`.permiso-checkbox[data-grupo="${grupo}"][data-item="${item}"]`);
                const allChecked = Array.from(boxes).every((b) => b.checked);
                boxes.forEach((b) => { b.checked = !allChecked; });
            });
        });
    });
</script>
<script src="{{ asset(mix('js/usuario-management.js')) }}"></script>
@endpush
@endsection
