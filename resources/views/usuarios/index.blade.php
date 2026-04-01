@extends('layouts.app')

@section('title', 'Gestión de Usuarios')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Gestión de Usuarios</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Crear, editar usuarios y gestionar permisos</p>
        </div>
        <button type="button" 
                onclick="event.preventDefault(); event.stopPropagation(); abrirModalCrear(event); return false;"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            + Nuevo Usuario
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-stretch">
        <!-- Lista de Usuarios -->
        <div class="flex flex-col">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm flex flex-col h-full">
                
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Usuarios ({{ $usuarios->count() }})</h3>
                
                <div class="space-y-2 flex-1 overflow-y-auto max-h-[calc(100vh-300px)]">
                    @forelse ($usuarios as $usuario)
                        <a href="{{ route('usuarios.index', ['usuario_id' => $usuario->usuario_id]) }}" 
                           class="group relative flex items-center justify-between p-3 rounded-lg border transition-all {{ $usuarioSeleccionado && $usuarioSeleccionado->usuario_id == $usuario->usuario_id ? 'bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-600' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $usuario->name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $usuario->email }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $usuario->rol->descripcion ?? 'Sin rol' }}</span>
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $usuario->estado === 'activo' ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : ($usuario->estado === 'pendiente_aprobacion' ? 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300') }}">
                                            {{ ucfirst(str_replace('_', ' ', $usuario->estado)) }}
                                        </span>
                                        @if($usuario->estado === 'pendiente_aprobacion' && $esAdmin)
                                            <button type="button" 
                                                    onclick="event.preventDefault(); event.stopPropagation(); abrirModalAprobar({{ $usuario->usuario_id }}, '{{ $usuario->name }}', '{{ $usuario->email }}', event); return false;"
                                                    class="inline-flex items-center px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/40 border border-green-200 dark:border-green-700 rounded hover:bg-green-100 dark:hover:bg-green-800/50 transition-colors"
                                                    title="Aprobar usuario">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                Aprobar
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 ml-3">
                                    <button type="button" 
                                            onclick="event.preventDefault(); event.stopPropagation(); abrirModalEditar({{ $usuario->usuario_id }}, event); return false;"
                                            class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors"
                                            title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <form action="{{ route('usuarios.destroy', $usuario->usuario_id) }}" 
                                          method="POST" 
                                          class="inline" 
                                          onsubmit="return confirm('¿Eliminar este usuario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors"
                                                title="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            No hay usuarios. <button type="button" onclick="event.preventDefault(); event.stopPropagation(); abrirModalCrear(event); return false;" class="text-blue-600 dark:text-blue-400 hover:underline">Crear uno</button>.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Panel de Configuración de Permisos -->
        <div class="lg:col-span-2 flex flex-col">
            @if($usuarioSeleccionado)
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm flex flex-col h-full">
                    <div class="mb-4 border-b border-gray-200 dark:border-gray-700 pb-4"  >
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Configuración de permisos</h2>
                        <div class="mt-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $usuarioSeleccionado->name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $usuarioSeleccionado->email }}</div>
                        </div>
                    </div>


                    


                    @php
                        $rolesConPermisos = \App\Models\Rol::with('permisos')->get()->mapWithKeys(fn ($r) => [$r->descripcion => $r->permisos->pluck('codigo')->toArray()]);
                        $categoriasPermisos = \App\Models\Permiso::porCategoria();
                        $permisosUsuario = $usuarioSeleccionado->permisos ?? [];
                    @endphp
                    <script>
                        window.__ROL_PERMISOS__ = @json($rolesConPermisos);
                    </script>
                    <form action="{{ route('usuarios.update-permisos', $usuarioSeleccionado->usuario_id) }}" method="POST" id="formPermisos" >
                        @csrf
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4">
                            <!-- Paquetes de Rol -->
                            <div >
                                <div class="flex items-center gap-2 mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" data-slot="icon" class="w-5 h-5 text-blue-600 dark:text-blue-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"></path>
                                    </svg>
                                    <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300">Paquetes de Rol</h4>
                                </div>
                                <p class="text-xs text-blue-700 dark:text-blue-400 mb-3">Selecciona un rol para aplicar los permisos por defecto de ese rol:</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach($rolesConPermisos as $nombreRol => $codigos)
                                        <button type="button"
                                                data-rol="{{ $nombreRol }}"
                                                onclick="aplicarPermisosRol((window.__ROL_PERMISOS__ && window.__ROL_PERMISOS__[this.getAttribute('data-rol')]) || [])"
                                                class="px-3 py-1.5 text-xs font-medium bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                            {{ $nombreRol }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">

                            <!-- Categorías de Permisos (desde tabla permisos) -->
                            @foreach($categoriasPermisos as $categoria => $permisos)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2 pb-2 border-b dark:border-gray-600">{{ $categoria }}</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 space-y-2">
                                        @foreach($permisos as $permiso => $label)
                                            <label class="flex items-center">
                                                <input type="checkbox" 
                                                       name="permisos[]" 
                                                       value="{{ $permiso }}"
                                                       {{ in_array($permiso, $permisosUsuario) ? 'checked' : '' }}
                                                       class="w-4 h-4 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-blue-500 dark:bg-gray-700">
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                                <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">({{ $permiso }})</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="submit" 
                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
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

<!-- Componente Vue para gestión de modales -->
<div id="usuario-management-app"></div>

@push('scripts')
<script>
    window.__USUARIO_MANAGEMENT_CONFIG__ = {!! json_encode([
        'csrfToken' => csrf_token(),
        'roles' => \App\Models\Rol::orderBy('descripcion')->get(),
        'storeUrl' => route('usuarios.store'),
        'updateUrl' => route('usuarios.update', ':usuario'),
        'aprobarUrl' => route('usuarios.aprobar', ':usuario'),
        'editDataUrl' => route('usuarios.edit-data', ':usuario'),
    ]) !!};
</script>
<script src="{{ asset(mix('js/usuario-management.js')) }}"></script>
@endpush
@endsection
