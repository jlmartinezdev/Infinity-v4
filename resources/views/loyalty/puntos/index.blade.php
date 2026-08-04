@extends('layouts.app')
@section('title', 'Puntos y reglas')
@section('content')
@php
    $diasMax = $diasPagoMax ?? 5;
    $resumenPago = collect($puntosPorDiaPago ?? [])
        ->filter(fn ($p) => (int) $p > 0)
        ->map(fn ($p, $d) => "D{$d}:{$p}")
        ->implode(' · ');
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                <a href="{{ route('loyalty.dashboard') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Loyalty</a>
                <span>/</span>
                <span>Puntos</span>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">Puntos y reglas</h1>
        </div>
        <button type="button" data-open-modal="modal-nueva-regla"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium hover:opacity-90 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nueva regla
        </button>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-200 px-4 py-2.5 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 dark:bg-red-950/40 dark:border-red-800 dark:text-red-200 px-4 py-2.5 text-sm">{{ session('error') }}</div>
    @endif

    {{-- KPIs compactos --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 px-4 py-3">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Reglas activas</p>
            <p class="mt-1 text-xl font-semibold tabular-nums {{ $stats['reglas_activas'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $stats['reglas_activas'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 px-4 py-3">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Con saldo</p>
            <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($stats['clientes_con_saldo']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 px-4 py-3">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">En circulación</p>
            <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($stats['puntos_circulacion']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 px-4 py-3">
            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Acred. mes</p>
            <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-600">+{{ number_format($stats['acreditados_mes']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        {{-- Consulta saldo --}}
        <section class="xl:col-span-4 rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Consultar saldo</h2>
            <form method="GET" class="flex gap-2">
                <input type="text" name="cedula" value="{{ request('cedula') }}" placeholder="Cédula"
                       class="flex-1 min-w-0 px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 focus:ring-2 focus:ring-gray-400/30 outline-none">
                <button class="px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/60">Buscar</button>
            </form>

            @if($clienteBuscado)
                <div class="mt-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-white/5 p-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $clienteBuscado->nombre }} {{ $clienteBuscado->apellido }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $clienteBuscado->cedula }}</p>
                    <p class="mt-3 text-2xl font-semibold tabular-nums tracking-tight">{{ number_format($saldoCliente) }} <span class="text-sm font-normal text-gray-400">pts</span></p>
                    <a href="{{ route('clientes.detalle', $clienteBuscado) }}" class="inline-block mt-2 text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 underline">Ver ficha del cliente</a>
                </div>
                <form method="POST" action="{{ route('loyalty.puntos.ajustar') }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="cliente_id" value="{{ $clienteBuscado->cliente_id }}">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Puntos</label>
                            <input type="number" name="puntos" required placeholder="+ / −"
                                   class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-500 mb-1">Concepto</label>
                            <input type="text" name="concepto" required maxlength="255"
                                   class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30">
                        </div>
                    </div>
                    <button class="w-full px-3 py-2 text-sm rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-medium hover:opacity-90">Aplicar ajuste</button>
                </form>
            @elseif(request('cedula'))
                <p class="mt-4 text-sm text-red-600">Cliente no encontrado.</p>
            @else
                <p class="mt-4 text-sm text-gray-400">Buscá por CI para ver saldo o hacer un ajuste manual.</p>
            @endif
        </section>

        {{-- Reglas --}}
        <section class="xl:col-span-8 rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Reglas</h2>
                @if($reglaPago && $resumenPago)
                    <span class="text-[11px] text-gray-400 truncate max-w-[60%]" title="{{ $resumenPago }}">Pago · {{ $resumenPago }}</span>
                @endif
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse($reglas as $r)
                    <div class="px-5 py-3.5 flex items-center gap-3 {{ $r->activa ? '' : 'opacity-55' }}">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $r->nombre }}</p>
                                <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300">{{ $eventos[$r->evento] ?? $r->evento }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5 font-mono truncate">{{ $r->codigo }}
                                @if($r->usaPuntosPorDia())
                                    ·
                                    @foreach($r->puntosPorDiaHasta($diasMax) as $dia => $pts)
                                        @if($pts > 0)<span class="text-gray-500">d{{ $dia }}={{ $pts }}</span>@endif
                                    @endforeach
                                @else
                                    · {{ $r->puntos }} pts
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($r->evento === \App\Models\LoyaltyRegla::EVENTO_PAGO)
                                <button type="button"
                                        data-open-modal="modal-pago-dias"
                                        title="Configurar puntos por día"
                                        class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-700/60 transition"
                                        aria-label="Configurar puntos por día">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </button>
                            @endif
                            <form method="POST" action="{{ route('loyalty.puntos.reglas.toggle', $r) }}">
                                @csrf
                                <button type="submit" title="{{ $r->activa ? 'Desactivar' : 'Activar' }}"
                                        class="px-2 py-1 rounded-md text-[11px] font-medium {{ $r->activa ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ $r->activa ? 'On' : 'Off' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('loyalty.puntos.reglas.destroy', $r) }}" onsubmit="return confirm('¿Eliminar regla?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 rounded-lg text-gray-300 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition" title="Eliminar" aria-label="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0V5a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-400">Sin reglas. Creá la primera.</p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Movimientos --}}
    <section class="rounded-2xl border border-gray-200/80 dark:border-white/10 bg-white dark:bg-gray-800/80 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Últimos movimientos</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-white/5">
                        <th class="px-5 py-2.5 font-medium">Fecha</th>
                        <th class="px-5 py-2.5 font-medium">Cliente</th>
                        <th class="px-5 py-2.5 font-medium">Pts</th>
                        <th class="px-5 py-2.5 font-medium">Saldo</th>
                        <th class="px-5 py-2.5 font-medium">Concepto</th>
                        <th class="px-5 py-2.5 font-medium w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                    @forelse($movimientos as $m)
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-2.5 whitespace-nowrap text-gray-500">{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-2.5 text-gray-800 dark:text-gray-200">{{ $m->cliente?->cedula }} — {{ $m->cliente?->nombre }}</td>
                            <td class="px-5 py-2.5 tabular-nums font-medium {{ $m->puntos >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $m->puntos >= 0 ? '+'.$m->puntos : $m->puntos }}</td>
                            <td class="px-5 py-2.5 tabular-nums text-gray-500">{{ $m->saldo_despues }}</td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-gray-300">{{ $m->concepto }}</td>
                            <td class="px-5 py-2.5 text-right">
                                @if(auth()->user()?->tienePermiso('loyalty-puntos.eliminar'))
                                    <form method="POST" action="{{ route('loyalty.puntos.movimientos.destroy', $m) }}"
                                          onsubmit="return confirm('¿Eliminar este movimiento y revertir el saldo?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg text-gray-300 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition" title="Eliminar movimiento" aria-label="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0V5a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">Sin movimientos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

{{-- Modal: nueva regla --}}
<div id="modal-nueva-regla" class="fixed inset-0 z-50 hidden" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" data-close-modal></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Nueva regla</h3>
                <button type="button" data-close-modal class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('loyalty.puntos.reglas.store') }}" class="p-5 space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Código</label>
                    <input type="text" name="codigo" required pattern="[a-z0-9_\-]+" placeholder="ej. bienvenida_app"
                           class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Nombre</label>
                    <input type="text" name="nombre" required
                           class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Tipo de evento</label>
                    <div class="flex items-center gap-2">
                        <select name="evento" id="nueva-evento"
                                class="flex-1 px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30">
                            @foreach($eventos as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" id="btn-gear-nueva-pago" title="Configurar días de pago"
                                class="hidden p-2.5 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-400 hover:text-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                data-open-modal="modal-pago-dias-nueva" aria-label="Configurar días">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="wrap-puntos-fijos">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Puntos</label>
                    <input type="number" name="puntos" id="nueva-puntos-fijos"
                           class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30">
                </div>
                <div id="preview-dias-nueva" class="hidden rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-white/5 px-3 py-2 text-xs text-gray-500">
                    Días de pago: configurá con el engranaje →
                </div>
                {{-- Hidden day inputs filled from nested modal --}}
                @for($d = 1; $d <= $diasMax; $d++)
                    <input type="hidden" name="dia_puntos[{{ $d }}]" id="nueva-dia-{{ $d }}" value="">
                @endfor
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Descripción</label>
                    <textarea name="descripcion" rows="2" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="activa" value="1" checked class="rounded border-gray-300"> Activa
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-close-modal class="px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</button>
                    <button class="px-4 py-2 text-sm rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-medium">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: config días (regla existente) --}}
@if($reglaPago)
<div id="modal-pago-dias" class="fixed inset-0 z-50 hidden" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" data-close-modal></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold">Puntos por día de pago</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Solo factura de servicio · días 1–{{ $diasMax }}</p>
                </div>
                <button type="button" data-close-modal class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('loyalty.puntos.reglas.dias', $reglaPago) }}" class="p-5 space-y-4">
                @csrf
                <div class="space-y-2">
                    @for($d = 1; $d <= $diasMax; $d++)
                        <div class="flex items-center gap-3">
                            <span class="w-14 text-xs font-medium text-gray-500">Día {{ $d }}</span>
                            <input type="number" name="dia_puntos[{{ $d }}]" min="0" step="1"
                                   value="{{ old('dia_puntos.'.$d, $puntosPorDiaPago[$d] ?? 0) }}"
                                   class="flex-1 px-3 py-2 text-sm text-right tabular-nums rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30"
                                   placeholder="0">
                            <span class="text-xs text-gray-400 w-8">pts</span>
                        </div>
                    @endfor
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="checkbox" name="activa" value="1" @checked(old('activa', $reglaPago->activa)) class="rounded border-gray-300">
                    Regla activa
                </label>
                <p class="text-[11px] text-gray-400 leading-relaxed">Si paga el día 3 del mes una factura de servicio, recibe los puntos del día 3. Día en 0 = no acredita.</p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" data-close-modal class="px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600">Cancelar</button>
                    <button class="px-4 py-2 text-sm rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-medium">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Modal: días al crear regla de pago (escribe a hidden inputs) --}}
<div id="modal-pago-dias-nueva" class="fixed inset-0 z-[60] hidden" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/30" data-close-nested></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold">Días de pago</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Configurá los {{ $diasMax }} primeros días del mes</p>
                </div>
                <button type="button" data-close-nested class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div class="space-y-2">
                    @for($d = 1; $d <= $diasMax; $d++)
                        <div class="flex items-center gap-3">
                            <span class="w-14 text-xs font-medium text-gray-500">Día {{ $d }}</span>
                            <input type="number" min="0" step="1" id="tmp-nueva-dia-{{ $d }}"
                                   class="flex-1 px-3 py-2 text-sm text-right tabular-nums rounded-lg border border-gray-200 dark:border-gray-600 dark:bg-gray-900/50 outline-none focus:ring-2 focus:ring-gray-400/30"
                                   placeholder="0">
                            <span class="text-xs text-gray-400 w-8">pts</span>
                        </div>
                    @endfor
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-close-nested class="px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-gray-600">Cancelar</button>
                    <button type="button" id="aplicar-dias-nueva" class="px-4 py-2 text-sm rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-medium">Aplicar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const diasMax = {{ (int) $diasMax }};

    function openModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal(el) {
        if (!el) return;
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('[role="dialog"]:not(.hidden)')) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn.getAttribute('data-open-modal')));
    });
    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.closest('[role="dialog"]')));
    });
    document.querySelectorAll('[data-close-nested]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const nested = document.getElementById('modal-pago-dias-nueva');
            closeModal(nested);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const nested = document.getElementById('modal-pago-dias-nueva');
        if (nested && !nested.classList.contains('hidden')) {
            closeModal(nested);
            return;
        }
        document.querySelectorAll('[role="dialog"]:not(.hidden)').forEach(closeModal);
    });

    const sel = document.getElementById('nueva-evento');
    const gear = document.getElementById('btn-gear-nueva-pago');
    const wrapPts = document.getElementById('wrap-puntos-fijos');
    const preview = document.getElementById('preview-dias-nueva');

    function syncEvento() {
        const esPago = sel && sel.value === 'pago_recibido';
        if (gear) gear.classList.toggle('hidden', !esPago);
        if (wrapPts) wrapPts.classList.toggle('hidden', !!esPago);
        if (preview) preview.classList.toggle('hidden', !esPago);
        updatePreview();
    }

    function updatePreview() {
        if (!preview) return;
        const parts = [];
        for (let d = 1; d <= diasMax; d++) {
            const v = parseInt(document.getElementById('nueva-dia-' + d)?.value || '0', 10) || 0;
            if (v > 0) parts.push('D' + d + ':' + v);
        }
        preview.textContent = parts.length
            ? 'Días de pago · ' + parts.join(' · ')
            : 'Días de pago: configurá con el engranaje →';
    }

    sel?.addEventListener('change', syncEvento);
    syncEvento();

    document.getElementById('aplicar-dias-nueva')?.addEventListener('click', () => {
        for (let d = 1; d <= diasMax; d++) {
            const tmp = document.getElementById('tmp-nueva-dia-' + d);
            const hid = document.getElementById('nueva-dia-' + d);
            if (tmp && hid) hid.value = tmp.value || '';
        }
        updatePreview();
        closeModal(document.getElementById('modal-pago-dias-nueva'));
    });

    @if(session('errors') || old('codigo'))
        openModal('modal-nueva-regla');
    @endif
})();
</script>
@endsection
