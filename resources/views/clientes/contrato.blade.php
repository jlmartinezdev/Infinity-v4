@extends('layouts.app')

@section('title', 'Contrato — '.$cliente_nombre)

@push('styles')
<style>
    .contrato-papel {
        background: #fff;
        color: #111;
        max-width: 210mm;
    }
    .contrato-papel h1,
    .contrato-papel h2,
    .contrato-papel p,
    .contrato-papel td,
    .contrato-papel th,
    .contrato-papel li {
        color: #111;
    }
    .contrato-firma {
        min-height: 4.5rem;
        border-bottom: 1px solid #111;
    }
    @media print {
        @page { size: A4; margin: 12mm 14mm; }
        html, body, #app-main-shell { background: #fff !important; }
        .contrato-toolbar { display: none !important; }
        .contrato-papel {
            max-width: none;
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
    }
</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="contrato-toolbar mb-4 flex flex-wrap items-center gap-2 print:hidden">
        <a href="{{ route('clientes.detalle', $cliente) }}" class="text-sm font-medium text-purple-700 dark:text-purple-300 hover:underline">&larr; Volver al cliente</a>
        <button type="button" onclick="window.print()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
            </svg>
            Imprimir
        </button>
    </div>

    <article class="contrato-papel mx-auto rounded-xl border border-gray-200 shadow-sm px-8 py-8 text-[12.5px] leading-relaxed">
        <header class="flex items-start gap-4 border-b border-gray-300 pb-4 mb-5">
            @if(!empty($empresa['logo_url']))
                <img src="{{ $empresa['logo_url'] }}" alt="{{ $empresa['nombre'] }}" class="h-14 w-auto object-contain shrink-0">
            @endif
            <div class="min-w-0 flex-1">
                <p class="text-lg font-bold tracking-tight">{{ $empresa['nombre'] }}</p>
                @if(($empresa['razon_social'] ?? '') !== '' && ($empresa['razon_social'] ?? '') !== ($empresa['nombre'] ?? ''))
                    <p class="text-[11px] text-gray-700">{{ $empresa['razon_social'] }}</p>
                @endif
                <p class="text-[11px] text-gray-700 mt-1">
                    RUC {{ $empresa['ruc'] }}
                    · {{ $empresa['direccion'] }}
                    · Tel. {{ $empresa['telefono'] }}
                    @if(($empresa['email'] ?? '—') !== '—')
                        · {{ $empresa['email'] }}
                    @endif
                </p>
            </div>
        </header>

        <h1 class="text-center text-base font-bold uppercase tracking-wide mb-1">{{ $titulo }}</h1>
        <p class="text-center text-[11px] text-gray-600 mb-5">N.º de cliente {{ $cliente->cliente_id }} · {{ $ciudad }}, {{ $fecha->format('d/m/Y') }}</p>

        <p class="mb-3 text-justify">
            Entre <strong>{{ $empresa['razon_social'] }}</strong> (en adelante, el <strong>PRESTADOR</strong>),
            RUC {{ $empresa['ruc'] }}, con domicilio en {{ $empresa['direccion'] }},
            y <strong>{{ $cliente_nombre }}</strong> (en adelante, el <strong>SUSCRIPTOR</strong>),
            documento de identidad N.º {{ $cliente_documento }}, teléfono {{ $cliente_telefono }},
            domicilio {{ $cliente_direccion }},
            se conviene el presente contrato de prestación de servicio de acceso a Internet, sujeto a las siguientes cláusulas:
        </p>

        <h2 class="font-bold text-[13px] mt-4 mb-1">1. Datos del servicio</h2>
        @if(count($servicios) === 0)
            <p class="mb-2">Al momento de la impresión no hay un servicio vigente cargado. El plan, precio e instalación se consignarán al activar el servicio.</p>
        @else
            <table class="w-full border-collapse mb-3 text-[11.5px]">
                <thead>
                    <tr class="border-b border-gray-400 text-left">
                        <th class="py-1.5 pr-2 font-semibold">Plan</th>
                        <th class="py-1.5 pr-2 font-semibold">Velocidad</th>
                        <th class="py-1.5 pr-2 font-semibold">Cuota mensual</th>
                        <th class="py-1.5 pr-2 font-semibold">Instalación</th>
                        <th class="py-1.5 font-semibold">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($servicios as $s)
                        <tr class="border-b border-gray-200 align-top">
                            <td class="py-1.5 pr-2">{{ $s['plan'] }}</td>
                            <td class="py-1.5 pr-2">{{ $s['velocidad'] }}</td>
                            <td class="py-1.5 pr-2 whitespace-nowrap">{{ $s['precio'] }}</td>
                            <td class="py-1.5 pr-2 whitespace-nowrap">{{ $s['fecha_instalacion'] }}</td>
                            <td class="py-1.5 font-mono">{{ $s['ip'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2 class="font-bold text-[13px] mt-4 mb-1">2. Objeto</h2>
        <p class="mb-2 text-justify">{{ $clausulas['objeto'] ?? '' }}</p>

        <h2 class="font-bold text-[13px] mt-4 mb-1">3. Duración y permanencia</h2>
        <p class="mb-2 text-justify">{{ $clausulas['permanencia'] ?? '' }}</p>

        <h2 class="font-bold text-[13px] mt-4 mb-1">4. Precio y forma de pago</h2>
        <p class="mb-2 text-justify">{{ $clausulas['pago'] ?? '' }}</p>

        <h2 class="font-bold text-[13px] mt-4 mb-1">5. Uso del servicio</h2>
        <p class="mb-2 text-justify">{{ $clausulas['uso'] ?? '' }}</p>

        <h2 class="font-bold text-[13px] mt-4 mb-1">6. Equipos en comodato</h2>
        <p class="mb-2 text-justify">{{ $clausulas['equipos'] ?? '' }}</p>
        <table class="w-full border-collapse mb-3 text-[11.5px]">
            <thead>
                <tr class="border-b border-gray-400 text-left">
                    <th class="py-1.5 pr-2 font-semibold w-1/4">Equipo</th>
                    <th class="py-1.5 pr-2 font-semibold">Marca / modelo / serie (completar)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $onus = collect($servicios)->pluck('cpe_onu')->filter()->unique()->implode(', ');
                    $routers = collect($servicios)->pluck('cpe_router')->filter()->unique()->implode(', ');
                    $antenas = collect($servicios)->pluck('cpe_antena')->filter()->unique()->implode(', ');
                @endphp
                <tr class="border-b border-gray-200">
                    <td class="py-2 pr-2">ONU / módem</td>
                    <td class="py-2">{{ $onus !== '' ? $onus : '________________________________' }}</td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="py-2 pr-2">Router Wi‑Fi</td>
                    <td class="py-2">{{ $routers !== '' ? $routers : '________________________________' }}</td>
                </tr>
                <tr class="border-b border-gray-200">
                    <td class="py-2 pr-2">Antena / radio</td>
                    <td class="py-2">{{ $antenas !== '' ? $antenas : '________________________________' }}</td>
                </tr>
                <tr>
                    <td class="py-2 pr-2">Otros (cables, etc.)</td>
                    <td class="py-2">________________________________</td>
                </tr>
            </tbody>
        </table>

        <h2 class="font-bold text-[13px] mt-4 mb-1">7. Soporte técnico</h2>
        <p class="mb-2 text-justify">{{ $clausulas['soporte'] ?? '' }}</p>

        <h2 class="font-bold text-[13px] mt-4 mb-1">8. Aceptación</h2>
        <p class="mb-6 text-justify">{{ $clausulas['aceptacion'] ?? '' }}</p>

        <div class="grid grid-cols-2 gap-10 mt-10 pt-4">
            <div class="text-center">
                <div class="contrato-firma mb-2"></div>
                <p class="font-semibold text-[12px]">El PRESTADOR</p>
                <p class="text-[11px] text-gray-700">{{ $empresa['nombre'] }}</p>
            </div>
            <div class="text-center">
                <div class="contrato-firma mb-2"></div>
                <p class="font-semibold text-[12px]">El SUSCRIPTOR</p>
                <p class="text-[11px] text-gray-700">{{ $cliente_nombre }}</p>
                <p class="text-[11px] text-gray-700">C.I. / RUC {{ $cliente_documento }}</p>
            </div>
        </div>
    </article>
</div>
@endsection
