@php
    $accionesEtiqueta = \App\Support\PermisosCatalogo::etiquetasAccion();
    $accionesColumnas = config('permisos.acciones', ['ver', 'crear', 'editar', 'eliminar']);
@endphp

<div class="space-y-3" id="permisos-granular">
    @foreach($arbolPermisos as $grupoIdx => $grupo)
        <details class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40" open>
            <summary class="cursor-pointer select-none px-4 py-3 font-semibold text-gray-900 dark:text-gray-100 flex items-center justify-between gap-2">
                <span>{{ $grupo['label'] }}</span>
                <button type="button"
                        class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400 permisos-toggle-grupo"
                        data-grupo="{{ $grupoIdx }}">
                    Marcar todo
                </button>
            </summary>
            <div class="px-4 pb-4 space-y-3">
                @foreach($grupo['items'] as $itemIdx => $item)
                    <div class="rounded-lg border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-800/60"
                         data-grupo="{{ $grupoIdx }}" data-item="{{ $itemIdx }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $item['label'] }}</span>
                            <button type="button"
                                    class="text-xs text-blue-600 hover:underline dark:text-blue-400 permisos-toggle-item self-start"
                                    data-grupo="{{ $grupoIdx }}" data-item="{{ $itemIdx }}">
                                Todo
                            </button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach($accionesColumnas as $accionCol)
                                @php
                                    $permisoItem = collect($item['permisos'])->firstWhere('accion', $accionCol);
                                @endphp
                                @if($permisoItem)
                                    <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                                        <input type="checkbox"
                                               name="permisos[]"
                                               value="{{ $permisoItem['codigo'] }}"
                                               data-grupo="{{ $grupoIdx }}"
                                               data-item="{{ $itemIdx }}"
                                               class="permiso-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-500 dark:bg-gray-700"
                                               {{ in_array($permisoItem['codigo'], $permisosUsuario, true) ? 'checked' : '' }}>
                                        <span class="text-gray-700 dark:text-gray-300">{{ $permisoItem['etiqueta'] }}</span>
                                    </label>
                                @else
                                    <span class="hidden sm:block"></span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
</div>
