@extends('layouts.app')

@section('title', 'Link TPago #'.$link->id)

@section('content')
@php
    $badge = [
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    ];
    $color = $badge[$link->colorEstado()] ?? $badge['gray'];
@endphp
<div class="max-w-4xl mx-auto pb-10">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Link TPago #{{ $link->id }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $link->description }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($link->link_url)
                <a href="{{ $link->link_url }}" target="_blank" rel="noopener"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Abrir link</a>
            @endif
            <a href="{{ route('tpago.links.index') }}"
               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-lg text-sm font-medium">Volver</a>
        </div>
    </div>

    <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-5 space-y-4 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex px-2.5 py-1 rounded-full text-sm font-medium {{ $color }}">{{ $link->etiquetaEstado() }}</span>
            <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ number_format($link->amount, 0, ',', '.') }} Gs</span>
        </div>

        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-gray-500">Cliente</dt>
                <dd class="text-gray-900 dark:text-gray-100">
                    {{ $link->cliente?->nombre }} {{ $link->cliente?->apellido }}
                    <span class="text-gray-500">({{ $link->cliente?->cedula }})</span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Factura interna</dt>
                <dd>
                    @if($link->factura_interna_id)
                        <a href="{{ route('factura-internas.show', $link->factura_interna_id) }}" class="text-indigo-600 hover:underline">#{{ $link->factura_interna_id }}</a>
                        @if($link->facturaInterna)
                            <span class="text-gray-500">· estado {{ $link->facturaInterna->estado }}</span>
                        @endif
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Alias</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $link->link_alias ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Reference ID</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $link->reference_id ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Ticket</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $link->ticket_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Auth / response</dt>
                <dd class="text-gray-900 dark:text-gray-100">
                    {{ $link->authorization_code ?: '—' }}
                    @if($link->response_code)
                        <span class="text-gray-500">(code {{ $link->response_code }})</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Creado</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $link->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Vence</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $link->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Pagado</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $link->paid_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Cobro</dt>
                <dd>
                    @if($link->cobro)
                        <a href="{{ route('cobros.show', $link->cobro) }}" class="text-green-600 hover:underline">
                            {{ $link->cobro->numero_recibo }}
                        </a>
                        <span class="text-gray-500">· {{ number_format($link->cobro->monto, 0, ',', '.') }} Gs</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>

        @if($link->link_url)
            <div>
                <p class="text-xs text-gray-500 mb-1">URL</p>
                <input type="text" readonly value="{{ $link->link_url }}"
                       class="w-full text-xs font-mono rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                       onclick="this.select()">
            </div>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Request (generación)</h2>
            <pre class="text-xs overflow-x-auto whitespace-pre-wrap text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">{{ json_encode($link->request_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: '—' }}</pre>
        </div>
        <div class="rounded-xl border bg-white dark:bg-gray-800 dark:border-gray-700 p-4">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Callback TPago</h2>
            <pre class="text-xs overflow-x-auto whitespace-pre-wrap text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">{{ json_encode($link->callback_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) ?: 'Sin callback aún' }}</pre>
        </div>
    </div>
</div>
@endsection
