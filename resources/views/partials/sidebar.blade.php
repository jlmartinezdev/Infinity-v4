{{-- Partial del menú lateral. Monta Sidebar.vue con config desde Blade. --}}
@php
    use App\Support\MenuUsuario;

    $user = auth()->user();
    if ($user) {
        $user->loadMissing('rol.permisos');
    }

    $menuItems = MenuUsuario::itemsFiltrados($user);

    foreach ($menuItems as &$item) {
        if (($item['name'] ?? '') === 'home') {
            $item['path'] = $user && $user->tienePermiso('dashboard.ver') ? '/' : '/inicio';
        }
    }
    unset($item);

    // Contar pedidos pendientes (no instalados y no descartados) para el badge
    $pedidosCount = $user && $user->tienePermiso('pedidos.ver')
        ? \App\Models\Pedido::where('estado_instalado', false)
            ->whereDoesntHave('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'))
            ->count()
        : 0;

    // Tareas en columna "Pendiente" del tablero (estado = pendiente)
    $tareasPendientesCount = $user && $user->tienePermiso('tareas.ver')
        ? \App\Models\Tarea::where('estado', 'pendiente')->count()
        : 0;

    // El mismo corte que el chip En curso: todo lo que no está resuelto ni cerrado
    $ticketsPendientesCount = $user && $user->tienePermiso('tickets.ver')
        ? \App\Models\Ticket::whereNotIn('estado', ['resuelto', 'cerrado'])->count()
        : 0;

    $trabajosRedAbiertosCount = $user && $user->tienePermiso('trabajos-red.ver')
        ? \App\Models\TrabajoRed::whereIn('estado', \App\Models\TrabajoRed::estadosAbiertos())->count()
        : 0;

    foreach ($menuItems as &$item) {
        if ($item['name'] === 'clientes' && isset($item['submenu'])) {
            foreach ($item['submenu'] as &$subItem) {
                if ($subItem['name'] === 'lista-pedidos') {
                    $subItem['badge'] = (string) $pedidosCount;
                    break;
                }
            }
        }
        if (($item['name'] ?? '') === 'tareas' && $tareasPendientesCount > 0) {
            $item['badge'] = (string) $tareasPendientesCount;
        }
        if (($item['name'] ?? '') === 'tickets' && $ticketsPendientesCount > 0) {
            $item['badge'] = (string) $ticketsPendientesCount;
        }
        if (($item['name'] ?? '') === 'trabajos-red' && $trabajosRedAbiertosCount > 0) {
            $item['badge'] = (string) $trabajosRedAbiertosCount;
        }
    }
    unset($item, $subItem);
@endphp
@php
    $sidebarConfig = [
        'menu' => $menuItems,
        'user' => $user ? ['name' => $user->name, 'email' => $user->email] : null
    ];
@endphp
<div id="sidebar-app"></div>
<script>
    window.__SIDEBAR_CONFIG__ = {!! json_encode($sidebarConfig) !!};
</script>
