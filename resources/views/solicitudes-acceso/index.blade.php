@extends('layouts.app')

@section('title', 'Solicitudes app')

@section('content')
@php
    $estadoFiltro = request()->has('estado') ? (string) request('estado') : 'pendiente';
    $esAdmin = auth()->user()?->esAdministrador() ?? false;
    $puedeEditar = auth()->user()?->tienePermiso('clientes.editar') ?? false;
    $tabs = [
        'pendiente' => ['label' => 'Pendientes', 'count' => $pendientesCount ?? 0, 'dot' => 'bg-amber-400'],
        'pendiente_verificacion' => ['label' => 'Esperando WA', 'count' => $verificacionCount ?? 0, 'dot' => 'bg-violet-400'],
        'aprobada' => ['label' => 'Aprobadas', 'count' => $aprobadasCount ?? 0, 'dot' => 'bg-emerald-400'],
        'rechazada' => ['label' => 'Rechazadas', 'count' => $rechazadasCount ?? 0, 'dot' => 'bg-rose-400'],
        '' => ['label' => 'Todas', 'count' => null, 'dot' => 'bg-slate-400'],
    ];
@endphp
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Solicitudes de acceso</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pedidos desde la app móvil</p>
    </div>

    @if(session('clave_portal'))
        <div class="mb-4 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-4 dark:border-blue-800 dark:from-blue-950/40 dark:to-indigo-950/30">
            <p class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-300">Clave generada (una sola vez)</p>
            <p class="mt-1 font-mono text-2xl font-bold tracking-[0.2em] text-blue-800 dark:text-blue-200">{{ session('clave_portal') }}</p>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => $tab)
            @php
                $active = $estadoFiltro === (string) $key;
                $href = route('solicitudes-acceso.index', array_filter([
                    'estado' => $key,
                    'buscar' => request('buscar') ?: null,
                ], fn ($v) => $v !== null));
            @endphp
            <a href="{{ $href }}"
               class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm transition
                      {{ $active
                          ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900 shadow-sm'
                          : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $tab['dot'] }} {{ $active ? 'ring-2 ring-white/40 dark:ring-gray-900/40' : '' }}"></span>
                {{ $tab['label'] }}
                @if($tab['count'] !== null)
                    <span class="text-xs opacity-70">{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="GET" action="{{ route('solicitudes-acceso.index') }}" class="flex flex-col gap-2 border-b border-gray-100 p-3 sm:flex-row sm:items-center dark:border-gray-700/80">
            <input type="hidden" name="estado" value="{{ $estadoFiltro }}">
            <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar nombre, CI o WhatsApp…"
                   class="w-full flex-1 rounded-xl border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500 dark:bg-gray-900/40 dark:text-gray-100 dark:ring-gray-600">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Buscar</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                        <th class="px-4 py-3">Solicitud</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">App / WA</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @forelse ($solicitudes as $s)
                        @php
                            $cliente = $s->cliente;
                            $usuarioPortal = $cliente?->usuarioPortal;
                            $wa = ($waPorSolicitud[$s->id] ?? collect())->first();
                            $pill = match ($s->estado) {
                                'pendiente_verificacion' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950/40 dark:text-violet-200 dark:ring-violet-800',
                                'pendiente' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800',
                                'aprobada' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-800',
                                default => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-800',
                            };
                            $bar = match ($s->estado) {
                                'pendiente_verificacion' => 'bg-violet-400',
                                'pendiente' => 'bg-amber-400',
                                'aprobada' => 'bg-emerald-400',
                                default => 'bg-rose-400',
                            };
                        @endphp
                        <tr class="group hover:bg-gray-50/70 dark:hover:bg-gray-700/20">
                            <td class="relative px-4 py-3.5">
                                <span class="absolute left-0 top-3 bottom-3 w-1 rounded-r {{ $bar }}"></span>
                                <div class="pl-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-xs font-medium text-gray-400">#{{ $s->id }}</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $s->nombre }}</span>
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-mono">{{ $s->cedula }}</span>
                                        @if($s->whatsapp)
                                            <a href="https://wa.me/595{{ ltrim(preg_replace('/\D+/', '', $s->whatsapp), '0') }}"
                                               target="_blank" rel="noopener"
                                               class="text-emerald-600 hover:underline dark:text-emerald-400">{{ $s->whatsapp }}</a>
                                        @endif
                                        @if($cliente)
                                            <a href="{{ route('clientes.detalle', $cliente) }}" class="text-blue-600 hover:underline dark:text-blue-400">Cli #{{ $cliente->cliente_id }}</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $pill }}">
                                    {{ App\Models\SolicitudAcceso::estados()[$s->estado] ?? $s->estado }}
                                </span>
                                @if($s->estado === 'aprobada' && $usuarioPortal && $usuarioPortal->estado !== 'activo')
                                    <div class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">Acceso suspendido</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                                @if($s->estado === 'pendiente_verificacion')
                                    <span class="text-violet-600 dark:text-violet-300">Código {{ $s->codigo_verificacion }}</span>
                                @elseif($s->telefono_verificado)
                                    <span class="text-emerald-600 dark:text-emerald-400">WA verificado</span>
                                @elseif($s->estado === 'aprobada' && $cliente)
                                    <div class="flex flex-col gap-0.5">
                                        <span class="{{ $cliente->app_activa ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                            {{ $cliente->app_activa ? 'App activa' : 'Sin login' }}
                                        </span>
                                        @if($wa)
                                            <span class="{{ in_array($wa->estado, ['entregado','leido','enviado'], true) ? 'text-emerald-600 dark:text-emerald-400' : ($wa->estado === 'fallido' ? 'text-rose-500' : '') }}">
                                                WA {{ $wa->estado }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif($wa)
                                    <span>WA {{ $wa->estado }}</span>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                {{ optional($s->created_at)->format('d/m/Y') }}
                                <div class="text-gray-400 dark:text-gray-500">{{ optional($s->created_at)->format('H:i') }}</div>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                @include('solicitudes-acceso._acciones', [
                                    'solicitud' => $s,
                                    'esAdmin' => $esAdmin,
                                    'puedeEditar' => $puedeEditar,
                                    'usuarioPortal' => $usuarioPortal,
                                    'hideVer' => false,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                                No hay solicitudes en este filtro.
                                <div class="mt-2">
                                    <a href="{{ route('solicitudes-acceso.index', ['estado' => '']) }}" class="text-blue-600 dark:text-blue-400 hover:underline">Ver todas</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($solicitudes->hasPages())
            <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-700/80">
                {{ $solicitudes->links() }}
            </div>
        @endif
    </div>
</div>

@include('solicitudes-acceso._menu_script')
@endsection
