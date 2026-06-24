@extends('layouts.app')

@section('title', 'Prueba SIFEN e-Kuatia')

@section('content')
@php
    $resultado = session('resultado');
@endphp
<div class="max-w-5xl mx-auto">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('configuracion.sifen') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración SIFEN</a>
        <span class="text-gray-300 dark:text-gray-600">|</span>
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">Configuración</a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Laboratorio de prueba SIFEN</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cree una factura de prueba y ejecute el flujo paso a paso antes de usar producción.</p>
        </div>
        @if($estado['ambiente'] === 'test')
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">Ambiente TEST</span>
        @else
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">Ambiente PRODUCCIÓN</span>
        @endif
        @if($estado['modo_api'] ?? false)
            <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300">Modo API</span>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">{{ session('error') }}</div>
    @endif

    @if($estado['modo_api'] ?? false)
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-purple-200 dark:border-purple-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-900/20">
                <h2 class="text-lg font-semibold text-purple-900 dark:text-purple-200">Conexión con sifen-api</h2>
                <p class="text-sm text-purple-700 dark:text-purple-300 mt-0.5">Firma, envío SOAP y certificado se ejecutan en el microservicio.</p>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">URL API</dt>
                        <dd class="font-mono text-xs break-all text-gray-900 dark:text-gray-100">{{ $estado['api_url'] ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="font-medium {{ ($estado['api_conectada'] ?? false) ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-400' }}">
                            {{ ($estado['api_conectada'] ?? false) ? 'Conectada' : 'Sin conexión / no configurada' }}
                        </dd>
                    </div>
                    @if(!empty($estado['api_status']['emisor']))
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Emisor en API</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $estado['api_status']['emisor'] }}</dd>
                        </div>
                    @endif
                </dl>

                @php $apiProbe = session('api_probe'); $apiTlsProbe = session('api_tls_probe'); @endphp
                @if($apiProbe)
                    <div class="p-3 rounded-lg text-xs {{ ($apiProbe['ok'] ?? false) ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-200' : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-200' }}">
                        {{ $apiProbe['mensaje'] ?? '—' }}
                        @if(!empty($apiProbe['latencia_ms'])) · {{ $apiProbe['latencia_ms'] }} ms @endif
                    </div>
                @endif
                @if($apiTlsProbe)
                    <div class="p-3 rounded-lg text-xs {{ ($apiTlsProbe['ok'] ?? false) ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-200' : 'bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200' }}">
                        mTLS: {{ $apiTlsProbe['mensaje'] ?? '—' }}
                        @if(!empty($apiTlsProbe['sifen_http_code'])) · SIFEN HTTP {{ $apiTlsProbe['sifen_http_code'] }} @endif
                    </div>
                @endif

                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('configuracion.sifen.prueba.api') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-purple-600 text-white hover:bg-purple-700">Probar conexión API</button>
                    </form>
                    @if($estado['ambiente'] === 'test')
                        <form method="POST" action="{{ route('configuracion.sifen.prueba.api.tls') }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-800 text-white hover:bg-gray-700 dark:bg-gray-700">Probar mTLS vía API</button>
                        </form>
                    @endif
                    @if(!empty($estado['api_panel_url']))
                        <a href="{{ $estado['api_panel_url'] }}" target="_blank" rel="noopener" class="px-4 py-2 text-sm font-medium rounded-lg border border-purple-300 dark:border-purple-700 text-purple-800 dark:text-purple-200 hover:bg-purple-50 dark:hover:bg-purple-900/30">Panel sifen-api</a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Resultado de última acción --}}
    @if($resultado)
        <div class="mb-6 p-5 rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/10">
            <h2 class="text-sm font-semibold text-green-900 dark:text-green-200 mb-3">Resultado de la operación</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Factura</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">
                        <a href="{{ route('facturas.show', $resultado['factura_id']) }}" class="text-green-600 dark:text-green-400 hover:underline">#{{ $resultado['factura_id'] }}</a>
                    </dd>
                </div>
                @if(!empty($resultado['sifen_api_documento_id']))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">ID en sifen-api</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">#{{ $resultado['sifen_api_documento_id'] }}</dd>
                    </div>
                @endif
                @if(!empty($resultado['modo_api']))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Motor</dt>
                        <dd class="font-medium text-purple-700 dark:text-purple-300">sifen-api (remoto)</dd>
                    </div>
                @endif
                @if(!empty($resultado['cdc']))
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">CDC</dt>
                        <dd class="font-mono text-xs break-all text-gray-900 dark:text-gray-100">{{ $resultado['cdc'] }}</dd>
                    </div>
                @endif
                @if(!empty($resultado['validacion']))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Validación XSD</dt>
                        <dd class="font-medium {{ ($resultado['validacion']['valido'] ?? false) ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                            {{ ($resultado['validacion']['valido'] ?? false) ? 'Válido' : 'Con errores' }}
                        </dd>
                    </div>
                @endif
                @if(!empty($resultado['validacion']['aviso']))
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Nota</dt>
                        <dd class="text-xs text-blue-700 dark:text-blue-300">{{ $resultado['validacion']['aviso'] }}</dd>
                    </div>
                @endif
                @if(!empty($resultado['validacion']['errores']))
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400 mb-1">Errores XSD</dt>
                        <dd>
                            <ul class="list-disc list-inside text-xs text-red-700 dark:text-red-300 space-y-0.5">
                                @foreach($resultado['validacion']['errores'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                @endif
                @if(!empty($resultado['sifen']))
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Código SIFEN</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $resultado['sifen']['codigo'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Protocolo</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $resultado['sifen']['protocolo'] ?? '—' }}</dd>
                    </div>
                    @if(!empty($resultado['sifen']['mensaje']))
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Mensaje SIFEN</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $resultado['sifen']['mensaje'] }}</dd>
                        </div>
                    @endif
                @endif
                @if(!empty($resultado['sifen']['detalles']))
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400 mb-1">Detalle SIFEN</dt>
                        <dd>
                            <ul class="list-disc list-inside text-xs text-gray-700 dark:text-gray-300 space-y-0.5">
                                @foreach($resultado['sifen']['detalles'] as $det)
                                    <li>[{{ $det['codigo'] ?? '?' }}] {{ $det['mensaje'] ?? '—' }}</li>
                                @endforeach
                            </ul>
                        </dd>
                    </div>
                @endif
                @if(!empty($resultado['respuesta']))
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 dark:text-gray-400">Respuesta SIFEN</dt>
                        <dd class="text-xs text-gray-700 dark:text-gray-300 break-words">{{ $resultado['respuesta'] }}</dd>
                    </div>
                @endif
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('facturas.show', $resultado['factura_id']) }}" class="text-sm text-green-700 dark:text-green-400 hover:underline">Ver factura</a>
                @if(!empty($resultado['pdf_path']))
                    <span class="text-gray-300">·</span>
                    <a href="{{ route('facturas.kude', $resultado['factura_id']) }}" class="text-sm text-blue-700 dark:text-blue-400 hover:underline">Descargar KuDE</a>
                @endif
                @if(!empty($resultado['xml_path']))
                    <span class="text-gray-300">·</span>
                    <a href="{{ route('facturas.xml', $resultado['factura_id']) }}" class="text-sm text-gray-700 dark:text-gray-300 hover:underline">Descargar XML</a>
                    <p class="w-full mt-1 text-xs text-amber-700 dark:text-amber-400">Suba ese archivo al <a href="https://ekuatia.set.gov.py/prevalidador/validacion" class="underline" target="_blank" rel="noopener">prevalidador</a> (no use borradores ni <code class="text-xs">last_soap_request.xml</code>).</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Checklist prerrequisitos --}}
    <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Prerrequisitos</h2>
        </div>
        <div class="p-6">
            <ul class="space-y-3 text-sm">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold {{ $estado['config_activa'] ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">{{ $estado['config_activa'] ? '✓' : '!' }}</span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">Configuración emisor activa</p>
                        @if($estado['emisor'])
                            <p class="text-gray-500 dark:text-gray-400">{{ $estado['emisor'] }} · Timbrado {{ $estado['timbrado'] }} · Próximo nº {{ $estado['siguiente_numero'] }}</p>
                        @else
                            <p class="text-gray-500 dark:text-gray-400"><a href="{{ route('configuracion.sifen') }}" class="text-green-600 hover:underline">Complete la configuración SIFEN</a></p>
                        @endif
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold {{ $estado['certificado_configurado'] ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">{{ $estado['certificado_configurado'] ? '✓' : '!' }}</span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">Certificado digital P12</p>
                        <p class="text-gray-500 dark:text-gray-400">
                            @if($estado['modo_api'] ?? false)
                                {{ $estado['certificado_configurado'] ? 'Certificado listo en sifen-api' : 'Suba certificado y contraseña en el panel sifen-api' }}
                            @else
                                {{ $estado['certificado_configurado'] ? 'Certificado listo para firmar' : 'Suba el certificado y contraseña en configuración' }}
                            @endif
                        </p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold {{ $estado['csc_configurado'] ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">{{ $estado['csc_configurado'] ? '✓' : '!' }}</span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">CSC para código QR</p>
                        <p class="text-gray-500 dark:text-gray-400">Id CSC: {{ $estado['csc_id'] ?? '—' }}</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold {{ ($estado['motor_firma'] ?? '') === 'node-tips' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">i</span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">Motor de firma</p>
                        <p class="text-gray-500 dark:text-gray-400">
                            @if(($estado['motor_firma'] ?? '') === 'node-tips')
                                TIPS / Node.js (recomendado para SIFEN)
                            @else
                                PHP xmlseclibs (fallback — puede fallar verificación local)
                            @endif
                        </p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">Endpoint SIFEN</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 break-all">{{ $estado['endpoint'] }}</p>
                    </div>
                </li>
            </ul>

            @php
                $tlsProbe = session('tls_probe');
                $diagCert = session('diagnostico_cert', $diagnosticoCert ?? []);
            @endphp
            @if(!($estado['modo_api'] ?? false))
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Certificado y conexión mTLS</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    La firma del XML y el envío a SIFEN son distintos: el validador e-Kuatia solo comprueba la firma;
                    SIFEN exige además autenticación TLS con el P12 habilitado en ambiente TEST.
                </p>
                @if(!empty($diagCert['sha1']))
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs mb-3">
                        <div>
                            <dt class="text-gray-500">Titular P12</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $diagCert['cn'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Huella SHA1</dt>
                            <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $diagCert['sha1'] }}</dd>
                        </div>
                        @if(!empty($diagCert['referencia_aprobada']['sha1']))
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500">Coincide con factura aprobada #62</dt>
                                <dd class="font-medium {{ !empty($diagCert['coincide_con_aprobado']) ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                    {{ !empty($diagCert['coincide_con_aprobado']) ? 'Sí — mismo certificado que autorizó DE en TEST' : 'No — el P12 actual difiere del que aprobó SIFEN' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                @elseif(!empty($diagCert['error_p12']))
                    <p class="text-xs text-red-700 dark:text-red-300 mb-3">{{ $diagCert['error_p12'] }}</p>
                @endif

                @if($tlsProbe)
                    <div class="mb-3 p-3 rounded-lg text-xs {{ ($tlsProbe['ok'] ?? false) ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-200' : 'bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200' }}">
                        Prueba TLS: HTTP {{ $tlsProbe['http_code'] ?? '?' }}
                        @if(!empty($tlsProbe['cert']['clientAuth']))
                            · clientAuth presente
                        @elseif(isset($tlsProbe['cert']['clientAuth']))
                            · <strong>sin clientAuth</strong> (requerido por SIFEN)
                        @endif
                        @if(!empty($tlsProbe['redirectUrl']))
                            · {{ $tlsProbe['redirectUrl'] }}
                        @endif
                    </div>
                @endif

                @if($estado['certificado_configurado'] && $estado['ambiente'] === 'test')
                    <form method="POST" action="{{ route('configuracion.sifen.prueba.tls') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-800 text-white hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600">
                            Probar conexión mTLS con SIFEN
                        </button>
                    </form>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Crear factura de prueba --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">1. Crear factura de prueba</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Genera un borrador con un ítem simple en PYG.</p>
            </div>
            <form action="{{ route('configuracion.sifen.prueba.factura') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente (con cédula/RUC)</label>
                    <select name="cliente_id" id="cliente_id" required
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100"
                            {{ $clientes->isEmpty() ? 'disabled' : '' }}>
                        <option value="">Seleccione…</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->cliente_id }}" {{ old('cliente_id') == $c->cliente_id ? 'selected' : '' }}>
                                {{ $c->nombre }} {{ $c->apellido }} — {{ $c->cedula }}
                            </option>
                        @endforeach
                    </select>
                    @if($clientes->isEmpty())
                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">No hay clientes con documento. Agregue cédula o RUC a un cliente primero.</p>
                    @endif
                </div>
                <div>
                    <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción del ítem</label>
                    <input type="text" name="descripcion" id="descripcion" required maxlength="255"
                           value="{{ old('descripcion', 'Servicio de internet - prueba SIFEN') }}"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="monto" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Monto (PYG, con IVA)</label>
                        <input type="number" name="monto" id="monto" required min="1000" step="1"
                               value="{{ old('monto', 100000) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="impuesto_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Impuesto</label>
                        <select name="impuesto_id" id="impuesto_id"
                                class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                            @foreach(\App\Models\Impuesto::activos() as $imp)
                                <option value="{{ $imp->id }}" {{ (old('impuesto_id', $impuestoDefault?->id) == $imp->id) ? 'selected' : '' }}>
                                    {{ $imp->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50"
                        {{ $clientes->isEmpty() ? 'disabled' : '' }}>
                    Crear borrador de prueba
                </button>
            </form>
        </div>

        {{-- Flujo recomendado --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Flujo recomendado</h2>
            </div>
            <div class="p-6">
                <ol class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 flex items-center justify-center text-xs font-bold">1</span>
                        <span><strong>Crear borrador</strong> con un cliente que tenga cédula o RUC válido.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 flex items-center justify-center text-xs font-bold">2</span>
                        <span><strong>Preparar DE</strong> — genera XML, CDC y valida contra XSD sin firmar ni enviar.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 flex items-center justify-center text-xs font-bold">3</span>
                        <span><strong>Emitir local</strong> — firma, QR y KuDE PDF sin llamar a SIFEN.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 flex items-center justify-center text-xs font-bold">4</span>
                        <span><strong>Enviar a SIFEN</strong> — solo en ambiente TEST. Autoriza el documento en e-Kuatia de prueba.</span>
                    </li>
                </ol>
                @if(!$listo)
                    <div class="mt-5 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-200">
                        Complete los prerrequisitos antes de emitir. Puede preparar el DE sin certificado.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Borradores para probar --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">2. Ejecutar pruebas sobre borradores</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Últimos borradores disponibles.</p>
        </div>
        <div class="overflow-x-auto">
            @if($borradores->isEmpty())
                <p class="p-6 text-sm text-gray-500 dark:text-gray-400">No hay facturas en borrador. Cree una arriba o desde <a href="{{ route('facturas.create') }}" class="text-green-600 hover:underline">Facturas electrónicas</a>.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">CDC</th>
                            @if($estado['modo_api'] ?? false)
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">API ID</th>
                            @endif
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($borradores as $f)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('facturas.show', $f) }}" class="text-green-600 dark:text-green-400 hover:underline">#{{ $f->id }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $f->cliente?->nombre }} {{ $f->cliente?->apellido }}
                                    <span class="block text-xs text-gray-500">{{ $f->cliente?->cedula }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($f->total, 0, ',', '.') }} PYG</td>
                                <td class="px-4 py-3 text-xs font-mono text-gray-500 dark:text-gray-400 max-w-[120px] truncate" title="{{ $f->set_cdc }}">
                                    {{ $f->set_cdc ? Str::limit($f->set_cdc, 12) : '—' }}
                                </td>
                                @if($estado['modo_api'] ?? false)
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ $f->sifen_api_documento_id ? '#'.$f->sifen_api_documento_id : '—' }}</td>
                                @endif
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <form action="{{ route('configuracion.sifen.prueba.preparar', $f) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2 py-1 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600" title="Generar XML y CDC">Preparar</button>
                                        </form>
                                        @if($listo)
                                            <form action="{{ route('configuracion.sifen.prueba.emitir-local', $f) }}" method="POST" class="inline" onsubmit="return confirm('¿Firmar y generar KuDE sin enviar a SIFEN?');">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 text-xs rounded bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300 hover:bg-purple-200">Local</button>
                                            </form>
                                            @if($estado['ambiente'] === 'test')
                                                <form action="{{ route('configuracion.sifen.prueba.emitir', $f) }}" method="POST" class="inline" onsubmit="return confirm('¿Enviar a SIFEN ambiente de PRUEBA?');">
                                                    @csrf
                                                    <button type="submit" class="px-2 py-1 text-xs rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 hover:bg-green-200">SIFEN</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Historial pruebas --}}
    @if($totalPruebas > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Historial de pruebas</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $totalPruebas }} factura(s) marcadas como prueba SIFEN.</p>
                </div>
                @if(config('sifen.ambiente') === 'test')
                    <form action="{{ route('configuracion.sifen.prueba.limpiar') }}" method="POST"
                          onsubmit="return confirm('¿Eliminar las {{ $totalPruebas }} factura(s) de prueba?\n\nSe borrarán registros, detalles y archivos XML/PDF locales.\nLos DE ya autorizados en SIFEN TEST no se anulan en el SET.\nLos números de timbrado usados no se recuperan.\n\nEsta acción no se puede deshacer.');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Limpiar pruebas ({{ $totalPruebas }})
                        </button>
                    </form>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">SIFEN</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">CDC</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($recientes as $f)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('facturas.show', $f) }}" class="text-green-600 dark:text-green-400 hover:underline">#{{ $f->id }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                        @if($f->estado === 'emitida') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                                        @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                                        {{ App\Models\Factura::estados()[$f->estado] ?? $f->estado }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $f->set_estado_envio ? ucfirst($f->set_estado_envio) : '—' }}</td>
                                <td class="px-4 py-3 text-xs font-mono text-gray-500 max-w-[140px] truncate" title="{{ $f->set_cdc }}">{{ $f->set_cdc ? Str::limit($f->set_cdc, 14) : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($f->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
