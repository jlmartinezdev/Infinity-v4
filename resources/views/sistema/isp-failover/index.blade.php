@extends('layouts.app')

@section('title', 'Failover ISP')

@section('content')
@php
    $enFailover = ($estado['modo'] ?? '') === 'failover';
    $puedeEditar = (bool) auth()->user()?->tienePermiso('sistema-isp-failover.editar');
    $lastAt = $estado['last_at'] ?? null;
    try {
        $lastAtFmt = $lastAt ? \Carbon\Carbon::parse($lastAt)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') : null;
    } catch (\Throwable $e) {
        $lastAtFmt = $lastAt;
    }
@endphp
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Failover de salida ISP</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Infinity hace ping a <span class="font-mono">{{ $config['ping_host'] ?: '1.1.1.1' }}</span> desde el router de borde
                con <strong>src-address de ISP 1</strong> (RouterOS 7 no permite ping por interface). Si falla, activa ISP 2 y avisa por WhatsApp.
            </p>
        </div>
        <a href="{{ route('sistema.red-monitoreo.index') }}"
            class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Monitoreo de red</a>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border p-4 {{ $enFailover ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700' : 'border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800' }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide font-medium {{ $enFailover ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                    Salida activa
                </p>
                <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    {{ $enFailover ? ($config['isp2_nombre'] ?? 'ISP 2') : ($config['isp1_nombre'] ?? 'ISP 1') }}
                    <span class="text-sm font-normal text-gray-500">
                        ({{ $enFailover ? 'failover' : 'primario' }})
                    </span>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Ping {{ $config['ping_host'] }}:
                    @if($estado['ping_ok'] === true)
                        <span class="text-emerald-600">OK{{ $estado['latency_ms'] ? ' '.$estado['latency_ms'].' ms' : '' }}</span>
                    @elseif($estado['ping_ok'] === false)
                        <span class="text-rose-600">fallo</span>
                        @if(!empty($estado['last_error'])) — {{ $estado['last_error'] }} @endif
                    @else
                        aún no chequeado
                    @endif
                    @if($lastAtFmt)
                        · {{ $lastAtFmt }}
                    @endif
                    · fallos seguidos: {{ (int) ($estado['fallos_seguidos'] ?? 0) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('sistema.isp-failover.ping') }}">
                    @csrf
                    <button type="submit" class="px-3 py-2 text-sm rounded-lg border border-cyan-400 text-cyan-700 dark:text-cyan-300 hover:bg-cyan-50 dark:hover:bg-cyan-900/20">
                        Ping ahora
                    </button>
                </form>
                @if($puedeEditar)
                <form method="POST" action="{{ route('sistema.isp-failover.forzar') }}"
                    onsubmit="return confirm('¿Forzar failover a {{ $config['isp2_nombre'] }} y avisar por WhatsApp?');">
                    @csrf
                    <button type="submit" class="px-3 py-2 text-sm rounded-lg border border-amber-400 text-amber-800 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                        Forzar ISP 2
                    </button>
                </form>
                <form method="POST" action="{{ route('sistema.isp-failover.restaurar') }}"
                    onsubmit="return confirm('¿Restaurar {{ $config['isp1_nombre'] }} como salida principal?');">
                    @csrf
                    <button type="submit" class="px-3 py-2 text-sm rounded-lg border border-emerald-400 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                        Restaurar ISP 1
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-5">
        <form method="POST" action="{{ route('sistema.isp-failover.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                    <input type="checkbox" name="enabled" value="1" @checked(!empty($config['enabled']))
                        class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500">
                    Activar chequeo automático (cada 60 s)
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                    <input type="checkbox" name="auto_failover" value="1" @checked(!empty($config['auto_failover']))
                        class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500">
                    Cambiar rutas en el MikroTik (failover real)
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Router de borde</label>
                    <select name="router_id" id="isp-router-id"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                        <option value="">— elegir —</option>
                        @foreach($routers as $r)
                            <option value="{{ $r->router_id }}" @selected((int) ($config['router_id'] ?? 0) === (int) $r->router_id)>
                                {{ $r->nombre }} ({{ $r->ip }})
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">El que tiene las dos WAN (ej. MK-N2-BORDE).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Host a pinguear</label>
                    <input type="text" name="ping_host" required value="{{ old('ping_host', $config['ping_host'] ?? '1.1.1.1') }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pings por chequeo</label>
                    <input type="number" name="ping_count" min="1" max="10" required
                        value="{{ old('ping_count', $config['ping_count'] ?? 2) }}"
                        class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-700">
                <p class="sm:col-span-2 text-sm font-semibold text-gray-800 dark:text-gray-200">ISP 1 (principal)</p>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Nombre</label>
                    <input type="text" name="isp1_nombre" required value="{{ old('isp1_nombre', $config['isp1_nombre']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">IP origen WAN (src-address) — obligatorio</label>
                    <input type="text" name="isp1_src_address" required placeholder="IP pública o WAN de ISP 1"
                        value="{{ old('isp1_src_address', $config['isp1_src_address']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                    <p class="mt-1 text-[11px] text-gray-500">RouterOS 7.23 no permite ping por interface; solo src-address.</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Interfaz WAN (opcional, solo referencia)</label>
                    <input type="text" name="isp1_interface" placeholder="ether1 / pppoe-out1"
                        value="{{ old('isp1_interface', $config['isp1_interface']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Comentario ruta default</label>
                    <input type="text" name="isp1_ruta_comentario" placeholder="ISP1"
                        value="{{ old('isp1_ruta_comentario', $config['isp1_ruta_comentario']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Gateway (si no usás comentario)</label>
                    <input type="text" name="isp1_gateway" placeholder="ej. 181.x.x.1"
                        value="{{ old('isp1_gateway', $config['isp1_gateway']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-700">
                <p class="sm:col-span-2 text-sm font-semibold text-gray-800 dark:text-gray-200">ISP 2 (respaldo)</p>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Nombre</label>
                    <input type="text" name="isp2_nombre" required value="{{ old('isp2_nombre', $config['isp2_nombre']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Comentario ruta default</label>
                    <input type="text" name="isp2_ruta_comentario" placeholder="ISP2"
                        value="{{ old('isp2_ruta_comentario', $config['isp2_ruta_comentario']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Gateway (si no usás comentario)</label>
                    <input type="text" name="isp2_gateway"
                        value="{{ old('isp2_gateway', $config['isp2_gateway']) }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                </div>
            </div>

            <div>
                <button type="button" id="isp-cargar-rutas"
                    class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Ver IPs WAN y rutas default del router
                </button>
                <pre id="isp-rutas-out" class="mt-2 hidden text-xs font-mono p-3 rounded-lg bg-slate-900 text-slate-100 overflow-x-auto max-h-48"></pre>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fallos seguidos antes de failover</label>
                    <input type="number" name="confirmaciones" min="1" max="20" required
                        value="{{ old('confirmaciones', $config['confirmaciones'] ?? 3) }}"
                        class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Con chequeo cada 60 s, 3 ≈ 3 minutos.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Éxitos seguidos para volver a ISP 1</label>
                    <input type="number" name="confirmaciones_ok" min="1" max="20" required
                        value="{{ old('confirmaciones_ok', $config['confirmaciones_ok'] ?? 3) }}"
                        class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL pública/LAN para webhook MikroTik</label>
                <input type="text" name="webhook_base_url" placeholder="{{ rtrim(config('app.url'), '/') }}"
                    value="{{ old('webhook_base_url', $config['webhook_base_url']) }}"
                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono">
                <p class="mt-1 text-xs text-gray-500">Ej. http://192.168.0.10/infinity-v4/public — el router no puede usar localhost.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quién recibe el WhatsApp</label>
                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($staff as $u)
                        <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                            <input type="checkbox" name="usuario_ids[]" value="{{ $u->usuario_id }}"
                                @checked(in_array((int) $u->usuario_id, $config['usuario_ids'] ?? [], true))
                                class="rounded border-gray-300 dark:border-gray-600 text-amber-600 focus:ring-amber-500">
                            <span class="flex-1 text-gray-900 dark:text-gray-100">{{ $u->name }}</span>
                            <span class="text-xs text-gray-400">{{ $u->telefono ?: 'sin teléfono' }}</span>
                        </label>
                    @empty
                        <p class="px-3 py-2 text-sm text-gray-500">No hay personal activo.</p>
                    @endforelse
                </div>
                <p class="mt-1 text-xs text-gray-500">Plantilla Meta: <code>docs/whatsapp-plantilla-isp-failover.md</code></p>
            </div>

            <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                @if($puedeEditar)
                <button type="submit"
                    class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700 font-medium">
                    Guardar
                </button>
                @endif
            </div>
        </form>

        @if($puedeEditar)
        <form method="POST" action="{{ route('sistema.isp-failover.probar') }}"
            onsubmit="return confirm('¿Enviar aviso de prueba [PRUEBA] a los destinatarios ya guardados?');">
            @csrf
            <button type="submit"
                class="px-4 py-2 text-sm rounded-lg border border-emerald-400 dark:border-emerald-600 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium">
                Probar WhatsApp
            </button>
        </form>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Script Netwatch (failover local + aviso a Infinity)</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Opcional pero recomendado: el MikroTik conmuta en segundos y luego informa a Infinity para el WhatsApp.
            Netwatch ICMP en ROS 7 usa <code>src-address</code>, no interface. Guardá la IP WAN de ISP 1 primero.
        </p>
        <pre class="text-xs font-mono p-3 rounded-lg bg-slate-900 text-slate-100 overflow-x-auto whitespace-pre-wrap">{{ $script }}</pre>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('isp-cargar-rutas');
    const out = document.getElementById('isp-rutas-out');
    const sel = document.getElementById('isp-router-id');
    if (!btn || !out || !sel) return;
    btn.addEventListener('click', async function () {
        const id = sel.value;
        if (!id) {
            alert('Elegí el router de borde.');
            return;
        }
        out.classList.remove('hidden');
        out.textContent = 'Leyendo…';
        try {
            const url = @json(route('sistema.isp-failover.rutas')) + '?router_id=' + encodeURIComponent(id);
            const r = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await r.json();
            if (!data.success) {
                out.textContent = data.message || 'Error';
                return;
            }
            const parts = [];
            if (data.direcciones && data.direcciones.length) {
                parts.push('IPs (copiá la WAN de ISP 1 como src-address):');
                data.direcciones.forEach(function (a) {
                    parts.push('  ' + (a.ip || a.address)
                        + '  iface=' + (a.interface || '-')
                        + (a.dynamic ? '  dyn' : '')
                        + (a.disabled ? '  DISABLED' : '')
                        + (a.comment ? '  ' + a.comment : ''));
                });
                parts.push('');
            }
            if (!data.rutas || !data.rutas.length) {
                parts.push('No hay rutas 0.0.0.0/0.');
            } else {
                parts.push('Rutas default:');
                data.rutas.forEach(function (x) {
                    parts.push('  ' + (x.comment || '(sin comentario)')
                        + '  gw=' + (x.gateway || '-')
                        + '  dist=' + (x.distance ?? '-')
                        + (x.disabled ? '  DISABLED' : '')
                        + (x.active ? '  ACTIVE' : ''));
                });
            }
            out.textContent = parts.join('\n');
        } catch (e) {
            out.textContent = 'No se pudo leer: ' + (e.message || e);
        }
    });
})();
</script>
@endsection
