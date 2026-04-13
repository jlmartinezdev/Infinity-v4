@php
    use App\Models\Servicio;
    use Illuminate\Support\Str;
    $ascii = fn (?string $s): string => Str::ascii($s ?? '');
    $facturas = $cobro->facturaInternas ?? collect();
    $saldoAFavorTotal = 0.0;
    if ($cobro->cliente) {
        if ($cobro->cliente->relationLoaded('servicios')) {
            $saldoAFavorTotal = (float) $cobro->cliente->servicios->sum(fn ($s) => (float) ($s->saldo_a_favor ?? 0));
        } else {
            $saldoAFavorTotal = (float) Servicio::where('cliente_id', $cobro->cliente->cliente_id)->sum('saldo_a_favor');
        }
    }
@endphp
@once
<style>
/* Modo recibo (localStorage reciboModo en .recibo-modo-wrapper) */
.recibo-modo-wrapper[data-recibo-modo="sin_grafico_linea"] .recibo-bloque-estandar { display: none !important; }
.recibo-modo-wrapper:not([data-recibo-modo="sin_grafico_linea"]) .recibo-bloque-linea-simple { display: none !important; }

.recibo-modo-wrapper[data-recibo-modo="sin_grafico"] .recibo-header-con-grafico { display: none !important; }
.recibo-modo-wrapper[data-recibo-modo="sin_grafico_linea"] .recibo-header-con-grafico { display: none !important; }
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-header-sin-grafico { display: none !important; }
.recibo-modo-wrapper[data-recibo-modo="sin_grafico_linea"] .recibo-header-sin-grafico { display: none !important; }

.recibo-modo-wrapper[data-recibo-modo="sin_grafico"] > .recibo-bloque-estandar > .recibo-termico {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.875rem;
    line-height: 1.25rem;
    border-width: 2px;
    border-style: dashed;
    border-color: #111827;
}
.recibo-modo-wrapper[data-recibo-modo="sin_grafico"] .recibo-linea-din {
    border-top-color: #000 !important;
    border-top-style: dashed !important;
}
/* Modo con gráfico: menos altura (pantalla e impresión) */
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-linea-din {
    border-top-color: #9ca3af;
    margin-top: 0.375rem !important;
    margin-bottom: 0.375rem !important;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-bloque-estandar > .recibo-termico {
    padding: 0.875rem 1rem;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-bloque-estandar > .recibo-termico > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.5rem !important;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-cabecera-principal {
    margin-bottom: 0.5rem !important;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-header-con-grafico img {
    max-height: 2.5rem;
    margin-bottom: 0.125rem;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-contacto {
    line-height: 1.2;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-bloque-estandar .pl-2.border-l-2.border-gray-300 {
    margin-bottom: 0.375rem !important;
    padding-left: 0.375rem;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-header-con-grafico .recibo-empresa {
    font-size: 1rem;
    line-height: 1.25rem;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-bloque-estandar > .recibo-termico .text-lg.font-bold {
    font-size: 1rem;
    line-height: 1.25rem;
}
.recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-bloque-estandar > .recibo-termico .recibo-pie p.mt-3 {
    margin-top: 0.5rem;
}
@media print {
    .recibo-modo-wrapper[data-recibo-modo="sin_grafico"] > .recibo-bloque-estandar > .recibo-termico {
        border-color: #000 !important;
    }
    .recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-bloque-estandar > .recibo-termico {
        padding: 0.625rem 0.75rem !important;
    }
    .recibo-modo-wrapper[data-recibo-modo="con_grafico"] .recibo-header-con-grafico img {
        max-height: 2.25rem;
    }
}
</style>
@endonce

{{-- Contenido del recibo: siempre apariencia clara (papel blanco), independiente del tema de la app --}}
<div class="recibo-modo-wrapper" data-recibo-modo="con_grafico">

<div class="recibo-bloque-estandar">
<div class="recibo-termico space-y-4 bg-white rounded-xl shadow p-6 print:shadow-none print:p-4 print:rounded-none border border-gray-200 text-gray-900">
    @if(!empty($pdfUrl))
        <div class="print:hidden flex justify-end -mt-1 mb-1">
            <a href="{{ $pdfUrl }}"
               data-pdf-base="{{ $pdfUrl }}"
               class="js-recibo-pdf-link inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-200 bg-red-50 text-red-800 hover:bg-red-100 transition-colors">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Descargar PDF
            </a>
        </div>
    @endif
    {{-- Cabecera: logo (modo gráfico) o solo texto (matricial / sin gráfico) --}}
    <div class="text-center mb-4 recibo-cabecera-principal">
        <div class="recibo-header-con-grafico">
        @if($ajustes && $ajustes->logo)
            <img src="{{ $ajustes->urlLogo() }}" alt="Logo" class="mx-auto h-14 object-contain print:h-12 mb-2">
        @else
        <h1 class="recibo-empresa text-lg font-bold uppercase tracking-tight text-gray-900">
            {{ $ascii($ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name')) }}
        </h1>
        @endif
        </div>
        <div class="recibo-header-sin-grafico">
            <h1 class="recibo-empresa text-base font-bold uppercase tracking-tight text-gray-900">
                {{ $ascii($ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name')) }}
            </h1>
        </div>
        @if($ajustes && ($ajustes->direccion || $ajustes->telefono || $ajustes->sitio_web))
            <div class="recibo-contacto text-xs text-gray-700 mt-1 space-y-0.5">
                @if($ajustes->direccion)<p>{{ $ascii($ajustes->direccion) }}</p>@endif
                @if($ajustes->telefono)<p>TEL: {{ $ascii($ajustes->telefono) }}</p>@endif
                @if($ajustes->email)<p>{{ $ascii($ajustes->email) }}</p>@endif
                @if($ajustes->sitio_web)<p>{{ $ascii($ajustes->sitio_web) }}</p>@endif
            </div>
        @endif
    </div>

    <div class="recibo-linea border-t recibo-linea-din my-3"></div>

    {{-- Fecha y número de recibo --}}
    <div class="recibo-mono text-xs text-gray-900">
        <p>{{ $cobro->fecha_pago->format('d/m/Y H:i') }}</p>
        <div class="flex justify-between mt-1">
            <span>RECIBO: #{{ $cobro->numero_recibo }}</span>
            @if(isset($esMulticobro) && $esMulticobro && isset($indice) && isset($total))
                <span class="text-gray-500">RECIBO {{ $indice }} / {{ $total }}</span>
            @endif
        </div>
    </div>

    <div class="recibo-linea border-t recibo-linea-din my-3"></div>

    {{-- Cliente --}}
    <div class="recibo-mono text-xs text-gray-900">
        <div class="">
            <span>CLIENTE: <span class="font-semibold text-right max-w-[60%]">{{ $ascii($cobro->cliente->nombre) }} {{ $ascii($cobro->cliente->apellido) }}</span></span>
        </div>
        <div class="mt-1">
            <span>CEDULA: {{ $ascii($cobro->cliente->cedula ?? '') ?: '—' }}</span>
        </div>
        @if($cobro->cliente->direccion)
        <div class="flex justify-between mt-1">
            <span>DIRECCION:</span>
            <span class="text-right max-w-[60%]">{{ $ascii($cobro->cliente->direccion) }}</span>
        </div>
        @endif
    </div>

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
                <span>PERIODO: {{ $fi->periodo_desde?->format('d/m/Y') }} - {{ $fi->periodo_hasta?->format('d/m/Y') }}</span>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if($cobro->concepto)
    <div class="recibo-mono text-xs text-gray-900 mt-2">
        <div class="">
            <span class="text-right max-w-[60%]">{{ $ascii($cobro->concepto) }}</span>
        </div>
    </div>
    @endif

    <div class="recibo-linea border-t recibo-linea-din my-3"></div>

    {{-- Monto total --}}
    <div class="recibo-mono text-xs text-gray-900 flex justify-between items-baseline">
        <span class="font-bold">TOTAL:</span>
        <span class="font-bold text-lg">{{ number_format($cobro->monto, 0, ',', '.') }} PYG</span>
    </div>

    @if($saldoAFavorTotal > 0)
    <div class="recibo-mono text-xs text-gray-900 flex justify-between items-baseline mt-2">
        <span class="font-semibold">SALDO A FAVOR:</span>
        <span>{{ number_format($saldoAFavorTotal, 0, ',', '.') }} PYG</span>
    </div>
    @endif

    <div class="recibo-linea border-t recibo-linea-din my-3"></div>

    {{-- Forma de pago y referencia --}}
    <div class="recibo-mono text-xs text-gray-900">
        <div class="flex justify-between">
            <span>FORMA DE PAGO:</span>
            <span>{{ $ascii(\App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago) }}</span>
        </div>
        @if($cobro->referencia)
        <div class="flex justify-between mt-1">
            <span>REF:</span>
            <span>{{ $ascii($cobro->referencia) }}</span>
        </div>
        @endif
        <div class="flex justify-between mt-1">
            <span>CAJERO:</span>
            <span>{{ $ascii($cobro->usuario?->name ?? '') ?: '—' }}</span>
        </div>
    </div>

    @if($cobro->observaciones)
    <div class="recibo-mono text-xs text-gray-900 mt-2">
        <p class="font-semibold">OBS:</p>
        <p class="break-words">{{ $ascii($cobro->observaciones) }}</p>
    </div>
    @endif

    <div class="recibo-linea border-t recibo-linea-din my-3"></div>

    {{-- Pie --}}
    <div class="text-center recibo-mono text-xs text-gray-800 recibo-pie">
        <p class="font-semibold uppercase">GRACIAS POR SU PAGO</p>
        <p class="mt-1 uppercase">VALIDO COMO COMPROBANTE</p>
        <p class="mt-3 text-gray-500">#{{ $cobro->numero_recibo }}</p>
    </div>
</div>
</div>

{{-- Modo linea simple: una linea por dato, sin cajas ni logo (solo texto ASCII) --}}
<div class="recibo-bloque-linea-simple font-mono text-xs text-gray-900 bg-white rounded-xl shadow p-6 print:shadow-none print:p-4 print:rounded-none border border-gray-200">
    @if(!empty($pdfUrl))
        <div class="print:hidden flex justify-end -mt-1 mb-3">
            <a href="{{ $pdfUrl }}"
               data-pdf-base="{{ $pdfUrl }}"
               class="js-recibo-pdf-link inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg border border-red-200 bg-red-50 text-red-800 hover:bg-red-100 transition-colors">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Descargar PDF
            </a>
        </div>
    @endif
    <p class=" uppercase tracking-tight">{{ $ascii($ajustes && $ajustes->nombre_empresa ? $ajustes->nombre_empresa : config('app.name')) }}</p>
    @if($ajustes && ($ajustes->direccion || $ajustes->telefono || $ajustes->email || $ajustes->sitio_web))
        <div class="mt-1 space-y-0.5 text-gray-800">
            @if($ajustes->direccion)<p>{{ $ascii($ajustes->direccion) }}</p>@endif
            @if($ajustes->telefono)<p>TEL: {{ $ascii($ajustes->telefono) }}</p>@endif
            @if($ajustes->email)<p>{{ $ascii($ajustes->email) }}</p>@endif
            @if($ajustes->sitio_web)<p>{{ $ascii($ajustes->sitio_web) }}</p>@endif
        </div>
    @endif
    <p class="mt-3">FECHA: {{ $cobro->fecha_pago->format('d/m/Y H:i') }}</p>
    <p>RECIBO: #{{ $cobro->numero_recibo }}</p>
    @if(isset($esMulticobro) && $esMulticobro && isset($indice) && isset($total))
        <p>RECIBO: {{ $indice }} / {{ $total }}</p>
    @endif
    <p class="mt-2">CLIENTE: {{ $ascii($cobro->cliente->nombre) }} {{ $ascii($cobro->cliente->apellido) }}</p>
    <p>CEDULA: {{ $ascii($cobro->cliente->cedula ?? '') ?: '—' }}</p>
    @if($cobro->cliente->direccion)
        <p>DIRECCION: {{ $ascii($cobro->cliente->direccion) }}</p>
    @endif

    @if($facturas->isNotEmpty())
        <p class="mt-2 font-semibold">{{ $facturas->count() > 1 ? 'FACTURAS INTERNAS:' : 'FACTURA INTERNA:' }}</p>
        @foreach($facturas as $fi)
            <p>#{{ $fi->id }} {{ number_format($fi->pivot->monto ?? $fi->total, 0, ',', '.') }} PYG | PERIODO {{ $fi->periodo_desde?->format('d/m/Y') }} - {{ $fi->periodo_hasta?->format('d/m/Y') }}</p>
        @endforeach
    @endif

    @if($cobro->concepto)
        <p class="mt-2">{{ $ascii($cobro->concepto) }}</p>
    @endif

    <p class="mt-2 font-bold">TOTAL: {{ number_format($cobro->monto, 0, ',', '.') }} PYG</p>
    @if($saldoAFavorTotal > 0)
        <p>SALDO A FAVOR: {{ number_format($saldoAFavorTotal, 0, ',', '.') }} PYG</p>
    @endif
    <p>FORMA DE PAGO: {{ $ascii(\App\Models\Cobro::formasPago()[$cobro->forma_pago] ?? $cobro->forma_pago) }}</p>
    @if($cobro->referencia)
        <p>REF: {{ $ascii($cobro->referencia) }}</p>
    @endif
    <p>CAJERO: {{ $ascii($cobro->usuario?->name ?? '') ?: '—' }}</p>

    @if($cobro->observaciones)
        <p class="mt-2">OBS: {{ $ascii($cobro->observaciones) }}</p>
    @endif
    <br>
    <br>
    <p >GRACIAS POR SU PAGO</p>
    <p >VALIDO COMO COMPROBANTE</p>
    
</div>

</div>
