@extends('layouts.app')

@section('title', 'WhatsApp')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">WhatsApp</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Estado de la Cloud API, plantillas y actividad reciente</p>
    </div>

    @include('whatsapp._tabs', ['waTab' => 'estado'])

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs uppercase tracking-wide text-gray-400">Estado</p>
            <p class="mt-1 text-lg font-semibold {{ $configured ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                {{ $configured ? 'Conectado' : 'Sin configurar' }}
            </p>
            <p class="text-xs text-gray-500">enabled={{ $enabled ? 'sí' : 'no' }} · {{ $apiVersion }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs uppercase tracking-wide text-gray-400">Hoy</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $conteos['hoy'] }}</p>
            <p class="text-xs text-gray-500">{{ $conteos['salida'] }} salida · {{ $conteos['entrada'] }} entrada</p>
        </div>
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs uppercase tracking-wide text-gray-400">Fallidos (7 días)</p>
            <p class="mt-1 text-lg font-semibold {{ $conteos['fallidos'] ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $conteos['fallidos'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs uppercase tracking-wide text-gray-400">Phone ID</p>
            <p class="mt-1 truncate font-mono text-sm text-gray-900 dark:text-gray-100" title="{{ $phoneNumberId }}">{{ $phoneNumberId ?: '—' }}</p>
            <p class="truncate text-xs text-gray-500" title="{{ $businessAccountId }}">WABA {{ $businessAccountId ?: '—' }}</p>
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Avisos automáticos</h2>
        </div>
        <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($events as $key => $on)
                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm dark:bg-gray-900/40">
                    <span class="text-gray-700 dark:text-gray-200">{{ str_replace('_', ' ', $key) }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $on ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                        {{ $on ? 'ON' : 'OFF' }}
                    </span>
                </div>
            @endforeach
        </div>
        @if(!empty($templatesConfig))
            <div class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500 dark:border-gray-700/80 dark:text-gray-400">
                Plantillas en .env:
                @foreach($templatesConfig as $ev => $tpl)
                    @if($tpl)
                        <span class="mr-2 font-mono">{{ $ev }}={{ $tpl }}</span>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Plantillas Meta</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Idioma</th>
                        <th class="px-4 py-3">Categoría</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @forelse($plantillasMeta as $t)
                        @php
                            $st = strtoupper($t['status'] ?? '');
                            $pill = match ($st) {
                                'APPROVED' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-800',
                                'PENDING' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800',
                                'REJECTED' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-800',
                                default => 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:ring-gray-600',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $t['name'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $pill }}">{{ $t['status'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $t['language'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $t['category'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Sin plantillas o no se pudo consultar Meta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Últimos mensajes</h2>
            <a href="{{ route('whatsapp.mensajes') }}" class="text-sm text-emerald-600 hover:underline dark:text-emerald-400">Ver todos</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Dir</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Cuerpo</th>
                        <th class="px-4 py-3">Fecha</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @forelse($ultimos as $m)
                        <tr>
                            <td class="px-4 py-3 text-xs text-gray-400">#{{ $m->id }}</td>
                            <td class="px-4 py-3 text-sm">{{ $m->direccion }}</td>
                            <td class="px-4 py-3">
                                @if($m->contacto_nombre)
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $m->contacto_nombre }}</div>
                                @endif
                                <div class="font-mono text-sm {{ $m->contacto_nombre ? 'text-gray-500' : 'text-gray-900 dark:text-gray-100' }}">{{ $m->telefono }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $m->estado }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ $m->cuerpo }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Sin mensajes aún.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
