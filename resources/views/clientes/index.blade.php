@extends('layouts.app')

@section('title', 'Lista de clientes')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Clientes</h1>
        <a href="{{ route('clientes.create') }}"
            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
            Nuevo cliente
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('clientes.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" name="buscar" value="{{ request('buscar') }}"
                        placeholder="Buscar por cédula, nombre, apellido, email o teléfono..."
                        class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                    @if(request('buscar'))
                        <a href="{{ route('clientes.index', request()->except('buscar')) }}" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            title="Limpiar búsqueda">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    @endif
                </div>
                <div class="sm:w-48">
                    <select name="estado" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                        <option value="todos" {{ request('estado') === 'todos' || !request('estado') ? 'selected' : '' }}>Todos los estados</option>
                        <option value="activo" {{ request('estado') === 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ request('estado') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        <option value="suspendido" {{ request('estado') === 'suspendido' ? 'selected' : '' }}>Suspendido</option>
                    </select>
                </div>
                <div class="sm:w-56">
                    <label for="sin_servicio" class="sr-only">Servicios</label>
                    <select id="sin_servicio" name="sin_servicio" class="w-full py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors bg-white dark:bg-gray-700 dark:text-gray-100">
                        <option value="" {{ ! request()->boolean('sin_servicio') ? 'selected' : '' }}>Todos (con o sin servicio)</option>
                        <option value="1" {{ request()->boolean('sin_servicio') ? 'selected' : '' }}>Sin servicio asociado</option>
                    </select>
                </div>
                <button type="submit" 
                    class="inline-flex items-center justify-center px-6 py-2.5 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Buscar
                </button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre / Documento</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Direccion</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Teléfono</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Calificación</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody id="clientes-list-app" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    {{-- Vue ClientesList.vue monta aquí las filas --}}
                </tbody>
            </table>
        </div>

        @php
            $clientesListConfig = [
                'clientes' => $clientes->items(),
                'firstItem' => $clientes->firstItem() ?? 1,
                'csrfToken' => csrf_token(),
                'urlEditClienteBase' => url('clientes') . '/__id__/edit',
                'urlDestroyClienteBase' => url('clientes') . '/__id__',
                'urlCreateCliente' => route('clientes.create'),
                'urlEditServicioBase' => url('servicios') . '/__servicio_id__/edit',
                'urlCreateServicioBase' => url('servicios') . '/create?cliente_id=__cliente_id__',
                'urlBuscarTemp' => route('clientes.buscar-temp'),
                'urlActualizarDesdeTempBase' => url('clientes') . '/__id__/actualizar-desde-temp',
                'puedeEditar' => auth()->user()?->tienePermiso('clientes.editar') ?? false,
            ];
        @endphp
        <script>
        window.__CLIENTES_LIST_CONFIG__ = @json($clientesListConfig);
        </script>

        @if ($clientes->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $clientes->withQueryString()->links('vendor.pagination.clientes-numeros') }}
            </div>
        @endif
    </div>
</div>
@push('scripts')
<script src="{{ asset(mix('js/clientes-list.js')) }}" defer></script>
@endpush
@endsection
