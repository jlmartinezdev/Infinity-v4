@extends('layouts.app')

@section('title', 'Factura #' . $factura->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Factura {{ $factura->numero_completo ?? '#' . $factura->id }}</h1>
        <div class="flex gap-2 flex-wrap">
            @if($factura->estado === 'borrador')
                @if($factura->enColaSifen())
                    <span class="inline-flex items-center px-4 py-2 bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 rounded-lg font-medium text-sm">
                        {{ $factura->set_estado_envio === 'consultando' ? 'Consultando lote…' : 'Emitiendo en segundo plano…' }}
                    </span>
                    <a href="{{ route('facturas.show', $factura) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Actualizar</a>
                @elseif($factura->lotePendienteSifen())
                    <form action="{{ route('facturas.consultar-lote', $factura) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700">Consultar lote SIFEN</button>
                    </form>
                @else
                    <a href="{{ route('facturas.edit', $factura) }}" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700">Editar</a>
                    <form action="{{ route('facturas.emitir', $factura) }}" method="POST" class="inline" onsubmit="return confirm('¿Emitir factura electrónica y enviar a SIFEN en segundo plano?');">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">Emitir e-Kuatia</button>
                    </form>
                @endif
            @endif
            @if($factura->puedeImprimirKude())
                <a href="{{ route('facturas.kude-pos', $factura) }}" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-lg font-medium hover:bg-amber-700">
                    KuDE POS 80 mm
                    @if($factura->estado !== 'emitida')
                        <span class="ml-1 text-xs opacity-90">(pendiente)</span>
                    @endif
                </a>
                <a href="{{ route('facturas.kude', $factura) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                    KuDE PDF
                    @if($factura->estado !== 'emitida')
                        <span class="ml-1 text-xs opacity-90">(pendiente)</span>
                    @endif
                </a>
            @endif
            @if($factura->xml_path && ! str_contains((string) $factura->xml_path, 'DE_borrador_'))
                <a href="{{ route('facturas.xml', $factura) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">XML</a>
            @endif
            <a href="{{ route('facturas.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Volver</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 border border-amber-200 dark:border-amber-800 text-sm">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-600">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $factura->esOcasional() ? 'Receptor (factura ocasional)' : 'Cliente' }}</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura->receptorNombreCompleto() }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $factura->receptorDocumentoEfectivo() }}</p>
                    @if($factura->receptorDireccionEfectiva())
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $factura->receptorDireccionEfectiva() }}</p>
                    @endif
                    @if($factura->esOcasional())
                        <span class="inline-flex mt-2 px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-300">Ocasional</span>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fecha emisión</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $factura->fecha_emision->format('d/m/Y') }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Tipo</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ App\Models\Factura::tiposDocumento()[$factura->tipo_documento] ?? $factura->tipo_documento }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Estado</p>
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                        @if($factura->estado === 'emitida') bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300
                        @elseif($factura->estado === 'anulada') bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300
                        @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 @endif">
                        {{ App\Models\Factura::estados()[$factura->estado] ?? $factura->estado }}
                    </span>
                </div>
            </div>
            @if($factura->numero_timbrado)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">Timbrado: {{ $factura->numero_timbrado }} · Vigencia: {{ $factura->timbrado_vigencia_desde?->format('d/m/Y') }} - {{ $factura->timbrado_vigencia_hasta?->format('d/m/Y') }}</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Descripción</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">P. unit.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Impuesto</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600 bg-white dark:bg-gray-800">
                    @foreach ($factura->detalles as $d)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->descripcion }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->precio_unitario, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->subtotal, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->monto_impuesto, 0, ',', '.') }} @if($d->porcentaje_impuesto)({{ $d->porcentaje_impuesto }}%)@endif</td>
                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($d->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex justify-end">
                <div class="text-right space-y-1">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Subtotal: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura->subtotal, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Impuestos: <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($factura->total_impuestos, 0, ',', '.') }} {{ $factura->moneda }}</span></p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Total: {{ number_format($factura->total, 0, ',', '.') }} {{ $factura->moneda }}</p>
                </div>
            </div>
            @if($factura->observaciones)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"><span class="font-medium">Observaciones:</span> {{ $factura->observaciones }}</p>
            @endif
            @if($factura->enColaSifen())
                <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm">
                    <p class="font-medium text-amber-900 dark:text-amber-200 mb-1">Procesando en segundo plano</p>
                    <p class="text-xs text-amber-800 dark:text-amber-300">
                        Estado: {{ $factura->set_estado_envio === 'consultando' ? 'Consultando lote SIFEN' : 'Emitiendo / enviando a SIFEN' }}.
                        Actualice la página en unos segundos.
                    </p>
                </div>
            @endif
            @if($factura->lotePendienteSifen() && $factura->set_estado_envio !== 'consultando')
                <div class="mt-4 p-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 text-sm">
                    <p class="font-medium text-indigo-900 dark:text-indigo-200 mb-1">Lote asíncrono pendiente</p>
                    <p class="text-xs text-indigo-800 dark:text-indigo-300">
                        Número de lote: <span class="font-mono font-semibold">{{ $factura->set_nro_lote }}</span>
                        · Estado: {{ ucfirst($factura->set_estado_envio) }}
                    </p>
                    <p class="text-xs text-indigo-700 dark:text-indigo-400 mt-1">DNIT puede demorar varios minutos. Ya puede imprimir el KuDE mientras espera; use «Consultar lote SIFEN» para obtener la autorización.</p>
                </div>
            @endif
            @if($factura->set_cdc)
                <div class="mt-4 p-3 rounded-lg bg-gray-100 dark:bg-gray-700/50 text-sm">
                    <p class="font-medium text-gray-700 dark:text-gray-300 mb-1">Factura electrónica (SIFEN)</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 break-all">CDC: {{ $factura->set_cdc }}</p>
                    @if($factura->set_nro_lote)
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Lote: <span class="font-mono">{{ $factura->set_nro_lote }}</span></p>
                    @endif
                    @if($factura->set_estado_envio)
                        <p class="text-xs mt-1 text-gray-600 dark:text-gray-400">
                            Estado SIFEN:
                            <span class="font-medium @if($factura->set_estado_envio === 'autorizado') text-green-700 dark:text-green-400 @elseif($factura->set_estado_envio === 'rechazado') text-red-700 dark:text-red-400 @elseif(in_array($factura->set_estado_envio, ['en_proceso', 'consultando', 'en_cola'], true)) text-indigo-700 dark:text-indigo-400 @else text-amber-700 dark:text-amber-400 @endif">
                                {{ ucfirst(str_replace('_', ' ', $factura->set_estado_envio)) }}
                            </span>
                            @if($factura->set_fecha_autorizacion)
                                · {{ $factura->set_fecha_autorizacion->format('d/m/Y H:i') }}
                            @endif
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
