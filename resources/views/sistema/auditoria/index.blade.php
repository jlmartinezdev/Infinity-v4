@extends('layouts.app')

@section('title', 'Auditoría')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Auditoría</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Registro de operaciones CRUD en el sistema.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <form method="GET" action="{{ route('sistema.auditoria.index') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                <div class="sm:w-40">
                    <label for="tabla" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Tabla</label>
                    <select name="tabla" id="tabla" class="w-full py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Todas</option>
                        @foreach($tablas as $t => $label)
                            <option value="{{ $t }}" {{ request('tabla') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-32">
                    <label for="accion" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Acción</label>
                    <select name="accion" id="accion" class="w-full py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Todas</option>
                        <option value="created" {{ request('accion') === 'created' ? 'selected' : '' }}>Creado</option>
                        <option value="updated" {{ request('accion') === 'updated' ? 'selected' : '' }}>Actualizado</option>
                        <option value="deleted" {{ request('accion') === 'deleted' ? 'selected' : '' }}>Eliminado</option>
                    </select>
                </div>
                <div class="sm:w-48">
                    <label for="usuario_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Usuario</label>
                    <select name="usuario_id" id="usuario_id" class="w-full py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="">Todos</option>
                        @foreach($usuarios as $id => $name)
                            <option value="{{ $id }}" {{ request('usuario_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:w-36">
                    <label for="fecha_desde" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" value="{{ request('fecha_desde') }}"
                        class="w-full py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                </div>
                <div class="sm:w-36">
                    <label for="fecha_hasta" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" value="{{ request('fecha_hasta') }}"
                        class="w-full py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 text-sm">
                        Filtrar
                    </button>
                    <a href="{{ route('sistema.auditoria.index') }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                        Limpiar
                    </a>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tabla</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acción</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Registro</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Detalles</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($auditorias as $a)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $a->created_at ? $a->created_at->format('d/m/Y H:i') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                {{ $a->usuario?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $a->tabla }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($a->accion === 'created')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300">Creado</span>
                                @elseif($a->accion === 'updated')
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">Actualizado</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">Eliminado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                @if($a->registro_key)
                                    <span class="font-mono">{{ $a->registro_key }}</span>
                                @else
                                    {{ $a->registro_id ?: '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $a->ip_address ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                @if($a->detalles)
                                    <button type="button" class="auditoria-ver-detalles text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium"
                                        data-detalles="{{ e(json_encode($a->detalles_decoded)) }}"
                                        data-tabla="{{ e($a->tabla) }}"
                                        data-accion="{{ e($a->accion) }}"
                                        data-fecha="{{ $a->created_at ? $a->created_at->format('d/m/Y H:i') : '' }}">
                                        Ver
                                    </button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay registros de auditoría.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($auditorias->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $auditorias->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    document.querySelectorAll('.auditoria-ver-detalles').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var d = this.getAttribute('data-detalles');
            try {
                var detalles = d ? JSON.parse(d) : {};
            } catch (e) {
                detalles = { raw: d };
            }
            var tabla = this.getAttribute('data-tabla') || '';
            var accion = this.getAttribute('data-accion') || '';
            var fecha = this.getAttribute('data-fecha') || '';
            var titulo = 'Detalles de auditoría';
            if (fecha || tabla || accion) {
                titulo = [fecha, tabla, accion].filter(Boolean).join(' · ');
            }
            var jsonStr = JSON.stringify(detalles, null, 2);
            var escaped = jsonStr.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
            var html = '<div class="text-left text-xs bg-gray-100 dark:bg-gray-700 p-4 rounded-lg font-mono text-gray-800 dark:text-gray-200 max-h-96 overflow-y-auto overflow-x-auto" style="margin:0; line-height:1.6;">' + escaped + '</div>';
            Swal.fire({
                title: titulo,
                html: html,
                width: 640,
                showCloseButton: true,
                showConfirmButton: true,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#7c3aed'
            });
        });
    });
})();
</script>
@endpush
@endsection
