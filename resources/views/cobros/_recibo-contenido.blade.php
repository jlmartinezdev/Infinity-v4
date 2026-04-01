{{-- Contenido del recibo: siempre apariencia clara (papel blanco), independiente del tema de la app --}}
<div class="recibo-termico space-y-4 bg-white rounded-xl shadow p-6 print:shadow-none print:p-4 print:rounded-none border border-gray-200 text-gray-900">
    @if(!empty($pdfUrl))
        <div class="print:hidden flex justify-end -mt-1 mb-1">
            <a href="{{ $pdfUrl }}"
               class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-200 bg-red-50 text-red-800 hover:bg-red-100 transition-colors">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Descargar PDF
            </a>
        </div>
    @endif
    {{-- Cabecera: logo + nombre empresa + contactos --}}
    <div class="text-center mb-4">
        @if($ajustes && $ajustes->logo)
            <img src="{{ $ajustes->urlLogo() }}" alt="Logo" class="mx-auto h-14 object-contain print:h-12 mb-2">
        @else
        <h1 class="recibo-empresa text-lg font-bold uppercase tracking-tight text-gray-900">
            {{ $ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name') }}
        </h1>
        @endif
        @if($ajustes && ($ajustes->direccion || $ajustes->telefono || $ajustes->sitio_web))
            <div class="recibo-contacto text-xs text-gray-700 mt-1 space-y-0.5">
                @if($ajustes->direccion)<p>{{ $ajustes->direccion }}</p>@endif
                @if($ajustes->telefono)<p>Tel: {{ $ajustes->telefono }}</p>@endif
                @if($ajustes->email)<p>{{ $ajustes->email }}</p>@endif
                @if($ajustes->sitio_web)<p>{{ $ajustes->sitio_web }}</p>@endif
            </div>
        @endif
    </div>

    <div class="recibo-linea border-t border-gray-400 my-3"></div>

    {{-- Fecha y número de recibo --}}
    <div class="recibo-mono text-xs text-gray-900">
        <p>{{ $cobro->fecha_pago->format('d/m/Y H:i') }}</p>
        <div class="flex justify-between mt-1">
            <span>RECIBO: #{{ $cobro->numero_recibo }}</span>
            @if(isset($esMulticobro) && $esMulticobro && isset($indice) && isset($total))
                <span class="text-gray-500">Recibo {{ $indice }} de {{ $total }}</span>
            @endif
        </div>
    </div>

    <div class="recibo-linea border-t border-gray-400 my-3"></div>

    {{-- Cliente --}}
    <div class="recibo-mono text-xs text-gray-900">
        <div class="">
            <span>CLIENTE: <span class="font-semibold text-right max-w-[60%]">{{ $cobro->cliente->nombre }} {{ $cobro->cliente->apellido }}</span></span>
        </div>
        <div class="mt-1">
            <span>CÉDULA:  {{ $cobro->cliente->cedula ?? '—' }}</span>
        </div>
        @if($cobro->cliente->direccion)
        <div class="flex justify-between mt-1">
            <span>DIRECCIÓN:</span>
            <span class="text-right max-w-[60%]">{{ $cobro->cliente->direccion }}</span>
        </div>
        @endif
    </div>

    @php $facturas = $cobro->facturaInternas ?? collect(); @endphp
    @if($facturas->isNotEmpty())
    <div class="recibo-mono text-xs text-gray-900 mt-2">
        <div class="font-semibold mb-1">{{ $facturas->count() > 1 ? 'FACTURAS INTERNAS:' : 'FACTURA INTERNA:' }}</div>
        @foreach($facturas as $fi)
        <div class="pl-2 border-l-2 border-gray-300 mb-2">
            <div class="flex justify-between">
                <span>#{{ $fi->id }}</span>
                <span>{{ number_format($fi->pivot->monto ?? $fi->total, 0, ',', '.') }} PYG</span>
            </div>
            <div class="flex justify-between mt-0.5 text-gray-600">
                <span>Período: {{ $fi->periodo_desde?->format('d/m/Y') }} - {{ $fi->periodo_hasta?->format('d/m/Y') }}</span>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if($cobro->concepto)
    <div class="recibo-mono text-xs text-gray-900 mt-2">
        <div class="">
            <span class="text-right max-w-[60%]">{{ $cobro->concepto }}</span>
        </div>
    </div>
    @endif

    <div class="recibo-linea border-t border-gray-400 my-3"></div>

    {{-- Monto total --}}
    <div class="recibo-mono text-xs text-gray-900 flex justify-between items-baseline">
        <span class="font-bold">TOTAL:</span>
        <span class="font-bold text-lg">{{ number_format($cobro->monto, 0, ',', '.') }} PYG</span>
    </div>

    <div class="recibo-linea border-t border-gray-400 my-3"></div>

    {{-- Forma de pago y referencia --}}
    <div class="recibo-mono text-xs text-gray-900">
        <div class="flex justify-between">
            <span>FORMA DE PAGO:</span>
            <span>{{ \App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago }}</span>
        </div>
        @if($cobro->referencia)
        <div class="flex justify-between mt-1">
            <span>REF:</span>
            <span>{{ $cobro->referencia }}</span>
        </div>
        @endif
        <div class="flex justify-between mt-1">
            <span>CAJERO:</span>
            <span>{{ $cobro->usuario?->name ?? '—' }}</span>
        </div>
    </div>

    @if($cobro->observaciones)
    <div class="recibo-mono text-xs text-gray-900 mt-2">
        <p class="font-semibold">OBS:</p>
        <p class="break-words">{{ $cobro->observaciones }}</p>
    </div>
    @endif

    <div class="recibo-linea border-t border-gray-400 my-3"></div>

    {{-- Pie --}}
    <div class="text-center recibo-mono text-xs text-gray-800">
        <p class="font-semibold uppercase">¡Gracias por su pago!</p>
        <p class="mt-1 uppercase">Válido como comprobante</p>
        <p class="mt-3 text-gray-500">#{{ $cobro->numero_recibo }}</p>
    </div>
</div>
