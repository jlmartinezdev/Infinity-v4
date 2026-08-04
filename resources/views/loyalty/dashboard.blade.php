@extends('layouts.app')
@section('title', 'Loyalty — Dashboard')
@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Loyalty / App</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Resumen de lo activo y movimiento reciente.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('loyalty.puntos.index') }}" class="px-3 py-1.5 rounded-lg border dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Puntos</a>
            <a href="{{ route('loyalty.canjes.index') }}" class="px-3 py-1.5 rounded-lg border dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Canjes</a>
            <a href="{{ route('loyalty.premios.index') }}" class="px-3 py-1.5 rounded-lg border dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Premios</a>
            <a href="{{ route('loyalty.novedades.index') }}" class="px-3 py-1.5 rounded-lg border dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Novedades</a>
            <a href="{{ route('loyalty.upsell.index') }}" class="px-3 py-1.5 rounded-lg border dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Upsell</a>
            @if(auth()->user()?->tienePermiso('loyalty-app-config.ver'))
                <a href="{{ route('loyalty.app-config.edit') }}" class="px-3 py-1.5 rounded-lg border border-indigo-300 dark:border-indigo-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">App clientes</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <a href="{{ route('loyalty.novedades.index') }}" class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 hover:border-blue-400/50 transition">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">Novedades activas</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $stats['novedades_activas'] }}</p>
            <p class="text-xs text-gray-400 mt-1">de {{ $stats['novedades_total'] }} total</p>
        </a>
        <a href="{{ route('loyalty.premios.index') }}" class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 hover:border-blue-400/50 transition">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">Premios activos</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $stats['premios_activos'] }}</p>
            <p class="text-xs text-gray-400 mt-1">stock {{ number_format($stats['premios_stock']) }}</p>
        </a>
        <a href="{{ route('loyalty.canjes.index') }}" class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 hover:border-amber-400/50 transition">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">Canjes abiertos</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $stats['canjes_abiertos'] }}</p>
            <p class="text-xs text-gray-400 mt-1">hoy {{ $stats['canjes_hoy'] }} · mes {{ $stats['canjes_mes'] }}</p>
        </a>
        <a href="{{ route('loyalty.puntos.index') }}" class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 hover:border-emerald-400/50 transition">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">Reglas activas</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $stats['reglas_activas'] }}</p>
            <p class="text-xs text-gray-400 mt-1">de {{ $stats['reglas_total'] }} configuradas</p>
        </a>
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">Pts en circulación</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($stats['puntos_en_circulacion']) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['clientes_con_saldo'] }} clientes con saldo</p>
        </div>
        <a href="{{ route('loyalty.upsell.index') }}" class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 hover:border-blue-400/50 transition">
            <p class="text-[11px] uppercase tracking-wide text-gray-500">Planes upsell</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $stats['upsell_activos'] }}</p>
            <p class="text-xs text-gray-400 mt-1">publicados en app</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4 lg:col-span-1">
            <h2 class="font-semibold mb-3">Reglas activas ahora</h2>
            @forelse($reglasActivas as $r)
                <div class="flex items-start justify-between gap-2 py-2 border-b last:border-0 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-medium">{{ $r->nombre }}</p>
                        <p class="text-xs text-gray-500">{{ $eventos[$r->evento] ?? $r->evento }} · <span class="font-mono">{{ $r->codigo }}</span></p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums {{ $r->puntos >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ $r->puntos >= 0 ? '+'.$r->puntos : $r->puntos }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                    Ninguna regla activa. Activá bienvenida/pago en <a class="underline" href="{{ route('loyalty.puntos.index') }}">Puntos y reglas</a>.
                </p>
            @endforelse
            <div class="mt-4 grid grid-cols-2 gap-2 text-center">
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 px-2 py-2">
                    <p class="text-[10px] uppercase text-emerald-700 dark:text-emerald-300">Acred. mes</p>
                    <p class="font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">+{{ number_format($stats['puntos_acreditados_mes']) }}</p>
                </div>
                <div class="rounded-lg bg-red-50 dark:bg-red-900/20 px-2 py-2">
                    <p class="text-[10px] uppercase text-red-700 dark:text-red-300">Débito mes</p>
                    <p class="font-bold text-red-700 dark:text-red-300 tabular-nums">−{{ number_format($stats['puntos_debitados_mes']) }}</p>
                </div>
            </div>
            @if($acredPorEvento->isNotEmpty())
                <div class="mt-3 space-y-1">
                    <p class="text-xs uppercase text-gray-500">Acreditaciones del mes</p>
                    @foreach($acredPorEvento as $row)
                        <div class="flex justify-between text-sm">
                            <span>{{ $row->tipo }}</span>
                            <span class="tabular-nums text-gray-600 dark:text-gray-300">{{ $row->cantidad }} · +{{ number_format((int) $row->total) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700">
                <h2 class="font-semibold">Últimos movimientos de puntos</h2>
                <a href="{{ route('loyalty.puntos.index') }}" class="text-xs text-purple-600 hover:underline">Ver todos</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y dark:divide-gray-700">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-left">Pts</th>
                            <th class="px-3 py-2 text-left">Concepto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($movimientosRecientes as $m)
                            <tr>
                                <td class="px-3 py-2 text-sm whitespace-nowrap">{{ $m->created_at?->format('d/m H:i') }}</td>
                                <td class="px-3 py-2 text-sm">{{ $m->cliente?->cedula }} — {{ $m->cliente?->nombre }}</td>
                                <td class="px-3 py-2 text-sm tabular-nums {{ $m->puntos >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $m->puntos }}</td>
                                <td class="px-3 py-2 text-sm">{{ $m->concepto }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Sin movimientos aún.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700">
                <h2 class="font-semibold">Canjes recientes</h2>
                <a href="{{ route('loyalty.canjes.index') }}" class="text-xs text-purple-600 hover:underline">Cola</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y dark:divide-gray-700">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-left">Premio</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($canjesRecientes as $c)
                            <tr>
                                <td class="px-3 py-2 text-sm">{{ $c->cliente?->nombre }} {{ $c->cliente?->apellido }}</td>
                                <td class="px-3 py-2 text-sm">{{ $c->premio?->nombre }} <span class="text-xs text-gray-400">({{ $c->puntos_usados }} pts)</span></td>
                                <td class="px-3 py-2 text-sm">{{ $estadosCanje[$c->estado] ?? $c->estado }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin canjes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
            <div class="px-4 py-3 border-b dark:border-gray-700">
                <h2 class="font-semibold">Top saldos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y dark:divide-gray-700">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Cliente</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        @forelse($topSaldos as $s)
                            <tr>
                                <td class="px-3 py-2 text-sm">{{ $s->cliente?->cedula }} — {{ $s->cliente?->nombre }} {{ $s->cliente?->apellido }}</td>
                                <td class="px-3 py-2 text-sm text-right font-semibold tabular-nums">{{ number_format($s->saldo) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-6 text-center text-gray-500">Nadie tiene puntos todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
