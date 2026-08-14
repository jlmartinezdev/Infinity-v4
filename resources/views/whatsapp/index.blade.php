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
        <a href="#fallidos" class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm transition-colors hover:border-rose-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-rose-700">
            <p class="text-xs uppercase tracking-wide text-gray-400">Fallidos (7 días)</p>
            <p class="mt-1 text-lg font-semibold {{ $conteos['fallidos'] ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $conteos['fallidos'] }}</p>
            <p class="text-xs text-gray-500">Ver listado ↓</p>
        </a>
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

    @php
        $plantillasAprobadas = collect($plantillasMeta ?? [])
            ->filter(fn ($t) => strtoupper((string) ($t['status'] ?? '')) === 'APPROVED')
            ->values();
        $plantillasOtras = collect($plantillasMeta ?? [])
            ->filter(fn ($t) => strtoupper((string) ($t['status'] ?? '')) !== 'APPROVED')
            ->values();
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Plantillas aprobadas</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Texto y parámetros (variables) según Meta</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ $plantillasAprobadas->count() }} APPROVED
            </span>
        </div>

        @if($plantillasAprobadas->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No hay plantillas APPROVED o no se pudo consultar Meta.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @foreach($plantillasAprobadas as $t)
                    <div class="p-4 sm:p-5 space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $t['name'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $t['language'] }} · {{ $t['category'] }}
                                    @if(!empty($t['parameter_format']))
                                        · formato {{ $t['parameter_format'] }}
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('whatsapp.enviar', ['plantilla' => $t['name']]) }}"
                               class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">Usar en envío</a>
                        </div>

                        @if(!empty($t['header_text']) || !empty($t['header_format']))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Header
                                    @if(!empty($t['header_format']) && $t['header_format'] !== 'TEXT')
                                        ({{ $t['header_format'] }})
                                    @endif
                                </p>
                                @if(!empty($t['header_text']))
                                    <p class="mt-1 whitespace-pre-wrap rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">{{ $t['header_text'] }}</p>
                                @endif
                            </div>
                        @endif

                        @if(!empty($t['body_text']))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Body</p>
                                <p class="mt-1 whitespace-pre-wrap rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:bg-gray-900/40 dark:text-gray-200">{{ $t['body_text'] }}</p>
                            </div>
                        @endif

                        @if(!empty($t['footer_text']))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Footer</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t['footer_text'] }}</p>
                            </div>
                        @endif

                        @php $params = $t['params'] ?? []; @endphp
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Parámetros</p>
                            @if(count($params) === 0)
                                <p class="mt-1 text-sm text-gray-500">Sin variables (mensaje fijo).</p>
                            @else
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($params as $p)
                                        <span class="inline-flex flex-col rounded-xl border border-gray-200 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-900/50">
                                            <span class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ $p['label'] }}</span>
                                            <span class="text-[10px] uppercase tracking-wide text-gray-400">{{ $p['component'] }}</span>
                                            @if(!empty($p['example']))
                                                <span class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">ej: {{ $p['example'] }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @if(!empty($t['buttons']))
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Botones</p>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach($t['buttons'] as $btn)
                                        <span class="rounded-lg bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">{{ $btn }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($plantillasOtras->isNotEmpty())
    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Otras plantillas (no aprobadas)</h2>
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
                    @foreach($plantillasOtras as $t)
                        @php
                            $st = strtoupper($t['status'] ?? '');
                            $pill = match ($st) {
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div id="fallidos" class="mb-6 overflow-hidden rounded-2xl border border-rose-200/70 bg-white shadow-sm dark:border-rose-900/50 dark:bg-gray-800 scroll-mt-20">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-rose-100 px-4 py-3 dark:border-rose-900/40">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Mensajes no enviados</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Salidas fallidas de los últimos 7 días (últimos 40)</p>
            </div>
            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">
                {{ number_format($conteos['fallidos'] ?? 0) }} fallidos
            </span>
        </div>

        @if(($fallidosPorCodigo ?? collect())->isNotEmpty())
            <div class="flex flex-wrap gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700/80">
                @foreach($fallidosPorCodigo as $row)
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1 text-xs dark:bg-gray-900/40">
                        <span class="font-mono font-semibold text-rose-600 dark:text-rose-300">{{ $row->codigo }}</span>
                        <span class="text-gray-500">×{{ $row->total }}</span>
                    </span>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:border-gray-700/80 dark:text-gray-500">
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Destino</th>
                        <th class="px-4 py-3">Plantilla / cuerpo</th>
                        <th class="px-4 py-3">Error</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @forelse(($mensajesFallidos ?? []) as $m)
                        @php $fallo = $m->detalleFallo(); @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                <div>{{ $m->created_at?->format('d/m/Y H:i') }}</div>
                                <div class="text-gray-400">#{{ $m->id }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($m->cliente)
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ trim($m->cliente->nombre.' '.$m->cliente->apellido) }}</div>
                                @elseif($m->contacto_nombre)
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $m->contacto_nombre }}</div>
                                @endif
                                <div class="font-mono text-sm text-gray-500">{{ $m->telefono }}</div>
                                @if($m->contexto_tipo)
                                    <div class="mt-0.5 text-[11px] text-gray-400">{{ $m->contexto_tipo }}{{ $m->contexto_id ? ' #'.$m->contexto_id : '' }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200 max-w-xs">
                                @php
                                    $cuerpoVisible = app(\App\Services\WhatsApp\WhatsAppService::class)->cuerpoVisibleMensaje($m);
                                @endphp
                                @if($m->template_name)
                                    <div class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ $m->template_name }}
                                        @if($m->template_language)
                                            <span class="font-normal text-gray-400">({{ $m->template_language }})</span>
                                        @endif
                                    </div>
                                @endif
                                <div class="line-clamp-3 text-xs text-gray-700 dark:text-gray-200 whitespace-pre-line" title="{{ $cuerpoVisible }}">{{ $cuerpoVisible ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3 max-w-sm">
                                @if($fallo['codigo'])
                                    <span class="inline-flex rounded-md bg-rose-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">{{ $fallo['codigo'] }}</span>
                                @endif
                                @if($fallo['titulo'])
                                    <div class="mt-1 text-xs font-medium text-gray-800 dark:text-gray-200">{{ $fallo['titulo'] }}</div>
                                @endif
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $fallo['mensaje'] ?: $m->error_message ?: 'Sin detalle' }}</div>
                                @if($fallo['tip'])
                                    <div class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">{{ $fallo['tip'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex flex-col gap-1.5">
                                    <a href="{{ route('whatsapp.mensajes', ['tel' => $m->telefono]) }}"
                                       class="text-xs font-medium text-emerald-600 hover:underline dark:text-emerald-400">Ver chat</a>
                                    @if(!empty($puedeEditar))
                                        <form method="POST" action="{{ route('whatsapp.reintentar', $m) }}" onsubmit="return confirm('¿Reintentar envío #{{ $m->id }}?');">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Reintentar</button>
                                        </form>
                                        @if($m->template_name)
                                            <a href="{{ route('whatsapp.enviar', ['telefono' => $m->telefono, 'plantilla' => $m->template_name]) }}"
                                               class="text-xs font-medium text-gray-500 hover:underline">Enviar plantilla</a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No hay salidas fallidas en los últimos 7 días.</td>
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
                        @php $cuerpoUltimo = app(\App\Services\WhatsApp\WhatsAppService::class)->cuerpoVisibleMensaje($m); @endphp
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
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate" title="{{ $cuerpoUltimo }}">{{ $cuerpoUltimo }}</td>
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
