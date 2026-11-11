@php
    $detalles = $pedido->estadoPedidoDetalles->sortBy('estado_id')->values();
    $aprobados = $detalles->where('estado', 'A')->values();
    $porEstado = $detalles->keyBy('estado_id');
@endphp

<section class="mb-6 space-y-4" aria-label="Resumen del pedido">
    @if($pasos->isNotEmpty())
        <ol class="flex flex-wrap gap-2">
            @foreach($pasos as $paso)
                @php
                    $detallePaso = $porEstado->get($paso->estado_id);
                    $marca = $detallePaso?->estado;
                @endphp
                <li class="flex min-w-[16rem] max-w-sm flex-col gap-1 rounded-lg border px-3 py-2 text-xs
                    {{ $marca === 'A' ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-gray-700 dark:bg-emerald-900/30 dark:text-emerald-300' : '' }}
                    {{ $marca === 'P' ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-gray-700 dark:bg-yellow-900/30 dark:text-yellow-300' : '' }}
                    {{ $marca === 'D' ? 'border-red-200 bg-red-50 text-red-800 dark:border-gray-700 dark:bg-red-900/30 dark:text-red-300' : '' }}
                    {{ $marca === null ? 'border-gray-200 bg-white text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400' : '' }}">
                    <span class="inline-flex items-center gap-2 font-medium">
                        <span class="tabular-nums">{{ $loop->iteration }}</span>
                        {{ $paso->descripcion }}
                        <span class="font-normal">
                            {{ $marca === 'A' ? 'Aprobado' : ($marca === 'P' ? 'Pendiente' : ($marca === 'D' ? 'Descartado' : 'Sin cargar')) }}
                        </span>
                    </span>
                    @if($detallePaso)
                        <span class="font-normal">
                            {{ $marca === 'A' ? 'Aprobó' : ($marca === 'D' ? 'Descartó' : 'Cargó') }}
                            {{ $detallePaso->usuario->name ?? 'sin usuario' }}
                            @if($detallePaso->fecha)
                                · {{ $detallePaso->fecha->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                            @endif
                        </span>
                        <span class="font-normal italic whitespace-pre-wrap">
                            {{ filled($detallePaso->notas) ? $detallePaso->notas : 'Sin notas' }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Estados aprobados</h2>
        </div>
        @if($aprobados->isEmpty())
            <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Todavía no hay estados aprobados.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($aprobados as $detalle)
                    <li class="px-4 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $detalle->estado_id }}.</span>
                                {{ $detalle->estadoPedido->descripcion ?? 'Estado' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($detalle->usuario)
                                    {{ $detalle->usuario->name }}
                                @endif
                                @if($detalle->fecha)
                                    · {{ $detalle->fecha->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        </div>
                        @if($detalle->nodo || $detalle->tipoTecnologia || $detalle->plan)
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                @if($detalle->nodo)Nodo: {{ $detalle->nodo->descripcion }}@endif
                                @if($detalle->tipoTecnologia) · Tecnología: {{ $detalle->tipoTecnologia->descripcion }}@endif
                                @if($detalle->plan) · Plan: {{ $detalle->plan->nombre }}@endif
                            </p>
                        @endif
                        @if($detalle->notas)
                            <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400">{{ $detalle->notas }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
