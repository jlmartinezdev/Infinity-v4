@extends('layouts.app')

@section('title', 'Solicitud #'.$solicitud->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <a href="{{ route('solicitudes-acceso.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Volver al listado</a>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">Solicitud #{{ $solicitud->id }}</h1>
        </div>
        @php
            $estadoClasses = match ($solicitud->estado) {
                'pendiente' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                'aprobada' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
                default => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            };
        @endphp
        <span class="inline-flex self-start rounded-full px-3 py-1 text-sm font-medium {{ $estadoClasses }}">
            {{ App\Models\SolicitudAcceso::estados()[$solicitud->estado] ?? $solicitud->estado }}
        </span>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('clave_portal'))
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-4 dark:border-blue-800 dark:bg-blue-900/30">
            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">Clave de acceso para la app (mostrarla una sola vez):</p>
            <p class="mt-2 font-mono text-2xl font-bold tracking-wider text-blue-700 dark:text-blue-300">{{ session('clave_portal') }}</p>
            <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">Usuario: documento {{ $solicitud->cedula }} · Contraseña: la clave de arriba</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos de la solicitud</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Nombre</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Documento</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->cedula }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">WhatsApp</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">
                            @if($solicitud->whatsapp)
                                <a href="https://wa.me/595{{ ltrim(preg_replace('/\D+/', '', $solicitud->whatsapp), '0') }}"
                                   target="_blank" rel="noopener"
                                   class="text-blue-600 dark:text-blue-400 hover:underline">{{ $solicitud->whatsapp }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Fecha</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Dirección</dt>
                        <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->direccion ?: '—' }}</dd>
                    </div>
                    @if($solicitud->latitud && $solicitud->longitud)
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Ubicación</dt>
                            <dd class="mt-0.5">
                                <a href="https://www.google.com/maps?q={{ $solicitud->latitud }},{{ $solicitud->longitud }}"
                                   target="_blank" rel="noopener"
                                   class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                    {{ $solicitud->latitud }}, {{ $solicitud->longitud }} (abrir mapa)
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cruce con base de clientes</h2>
                @if($coincideBd)
                    <p class="text-sm text-green-700 dark:text-green-300">
                        Coincide con cliente existente
                        @if($clienteExistente)
                            <a href="{{ route('clientes.detalle', $clienteExistente) }}" class="font-medium underline">
                                #{{ $clienteExistente->cliente_id }} — {{ $clienteExistente->nombre }} {{ $clienteExistente->apellido }}
                            </a>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Al aprobar se actualizarán teléfono/dirección/ubicación del cliente.</p>
                @else
                    <p class="text-sm text-amber-700 dark:text-amber-300">No hay cliente con este documento. Al aprobar se creará uno nuevo.</p>
                @endif
            </div>

            @if($solicitud->estado === 'aprobada')
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-300">
                    Aprobada el {{ optional($solicitud->aprobado_at)->format('d/m/Y H:i') }}
                    @if($solicitud->aprobador)
                        por {{ $solicitud->aprobador->name }}
                    @endif
                    @if($solicitud->cliente_id)
                        · Cliente
                        <a href="{{ route('clientes.detalle', $solicitud->cliente_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">#{{ $solicitud->cliente_id }}</a>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Foto cédula (frente)</h2>
                @if($solicitud->frente_url)
                    <a href="{{ $solicitud->frente_url }}" target="_blank" rel="noopener">
                        <img src="{{ $solicitud->frente_url }}" alt="Cédula frente"
                             class="w-full rounded-lg border border-gray-200 dark:border-gray-600 object-contain max-h-80 bg-gray-50 dark:bg-gray-900">
                    </a>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin imagen.</p>
                @endif
            </div>

            @if($solicitud->estado === 'pendiente' && auth()->user()?->tienePermiso('clientes.editar'))
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-3">
                    <form action="{{ route('solicitudes-acceso.aprobar', $solicitud) }}" method="POST"
                          onsubmit="return confirm('¿Aprobar y generar clave PLUS para la app?');">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700">
                            Aprobar y generar clave
                        </button>
                    </form>
                    <form action="{{ route('solicitudes-acceso.rechazar', $solicitud) }}" method="POST"
                          onsubmit="return confirm('¿Rechazar esta solicitud?');">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-lg border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900/30">
                            Rechazar
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
