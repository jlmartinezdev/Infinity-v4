@extends('layouts.app')

@php
    $modeloNombre = \App\Support\OltModelosCatalogo::nombre($olt->modelo) ?: ($olt->modelo ?? null);
    $modeloImagen = \App\Support\OltModelosCatalogo::imagenUrl($olt->modelo);
    $autoConsultar = $autoConsultar ?? false;
@endphp

@section('title', 'OLT: ' . ($olt->codigo ?? $olt->ip ?? $modeloNombre ?? $olt->olt_id))

@section('content')
@include('olts._consulta_async')
<div class="max-w-5xl mx-auto" @if($autoConsultar) data-olt-auto-sync="{{ route('sistema.olts.sync-vista', $olt) }}" @endif>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('sistema.olts.index') }}" class="text-sm font-medium text-purple-600 hover:text-purple-800 hover:underline dark:text-purple-400 dark:hover:text-purple-300">&larr; Volver a OLTs</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                OLT {{ $olt->codigo ?? $olt->ip ?? $modeloNombre ?? '#' . $olt->olt_id }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $olt->marca ?? '—' }}{{ $modeloNombre ? ' — ' . $modeloNombre : '' }} · {{ $olt->nodo?->descripcion ?? '—' }}
            </p>
        </div>
        <div class="shrink-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <img src="{{ $modeloImagen }}" alt="{{ $modeloNombre ?? 'OLT' }}" class="h-20 w-36 object-contain">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Datos del OLT</h2>
            </div>
            <div class="p-6">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Código</dt>
                        <dd class="min-w-0 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $olt->codigo ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Nodo</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->nodo?->descripcion ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Pools</dt>
                        <dd class="text-gray-900 dark:text-gray-100">
                            @forelse($olt->pools as $pool)
                                <span class="block">
                                    {{ $pool->descripcion ?: $pool->ip_range }}
                                    <span class="text-xs text-gray-500">({{ $pool->router?->nombre ?? 'sin router' }})</span>
                                </span>
                            @empty
                                <span class="text-gray-400">Ninguno asociado</span>
                            @endforelse
                        </dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Marca</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->marca ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Modelo</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $modeloNombre ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">IP</dt>
                        <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $olt->ip ?? '—' }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Tipo PON</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->tipo_pon }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Puertos PON</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $olt->oltPuertos->count() }} de {{ $olt->cantidad_puerto }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                        <dt class="shrink-0 text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ ucfirst($olt->estado) }}</dd>
                    </div>
                </dl>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('sistema.olts.edit', $olt) }}" class="inline-flex items-center rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-medium text-purple-800 transition-colors hover:bg-purple-100 dark:border-purple-800 dark:bg-purple-900/30 dark:text-purple-200 dark:hover:bg-purple-900/50">
                        Editar
                    </a>
                    <form action="{{ route('sistema.olts.destroy', $olt) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este OLT?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-800 transition-colors hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-900/40">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Puertos PON</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Puerto del equipo y salida de fibra (mismo PON).</p>
                </div>
                <a href="{{ route('sistema.olt-puertos.create', $olt) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-purple-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    + Puerto
                </a>
            </div>
            <div class="p-4 sm:p-6">
                @if(empty($filasPon))
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No hay puertos definidos.
                        <a href="{{ route('sistema.olt-puertos.create', $olt) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Agregar puertos</a>
                    </p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($filasPon as $fila)
                            @php
                                $p = $fila['puerto'] ?? null;
                                $sp = $fila['salida'] ?? null;
                                $numeroPon = $p?->numero ?? $sp?->puerto_olt;
                                $tipoPon = $p?->tipo_pon ?? $olt->tipo_pon;
                                $onuEnPuerto = $numeroPon !== null ? (int) ($onuCountPorPuerto[$numeroPon] ?? 0) : 0;
                                $naps = $sp?->cajaNaps?->count() ?? 0;
                                $potenciaTxt = $sp && $sp->potencia_salida !== null
                                    ? rtrim(rtrim(number_format((float) $sp->potencia_salida, 3, '.', ''), '0'), '.').' dBm'
                                    : null;
                                $chip = 'inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100';
                                $btn = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium transition-colors';
                            @endphp
                            <li class="py-3.5 first:pt-0 last:pb-0">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-sm font-bold tabular-nums text-purple-800 dark:bg-purple-900/40 dark:text-purple-200" title="Puerto {{ $numeroPon }}">
                                        {{ $numeroPon ?? '—' }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                                            <div class="min-w-0">
                                                @if($sp)
                                                    <a href="{{ route('sistema.salida-pons.edit', ['salida_pon' => $sp, 'return_olt' => $olt->olt_id]) }}"
                                                       class="block truncate text-sm font-semibold text-gray-900 hover:text-purple-700 dark:text-gray-100 dark:hover:text-purple-300">
                                                        {{ $sp->codigo }}
                                                    </a>
                                                @else
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Puerto {{ $numeroPon ?? '—' }}</p>
                                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                        Sin salida de fibra.
                                                        @if($p)
                                                            <a href="{{ route('sistema.salida-pons.create', [
                                                                'nodo_id' => $olt->nodo_id,
                                                                'olt_id' => $olt->olt_id,
                                                                'olt_puerto_id' => $p->olt_puerto_id,
                                                                'codigo' => $numeroPon ? 'PON-'.$numeroPon : null,
                                                                'return_olt' => $olt->olt_id,
                                                            ]) }}" class="font-medium text-purple-600 hover:underline dark:text-purple-400">Registrar</a>
                                                        @endif
                                                    </p>
                                                @endif
                                                <div class="mt-1.5 flex flex-wrap gap-1">
                                                    <span class="{{ $chip }}">{{ $tipoPon }}</span>
                                                    @if($sp?->tipo_modulo)
                                                        <span class="{{ $chip }}">{{ $sp->tipo_modulo }}</span>
                                                    @endif
                                                    @if($potenciaTxt)
                                                        <span class="{{ $chip }}">{{ $potenciaTxt }}</span>
                                                    @endif
                                                    @if($onuEnPuerto > 0)
                                                        <span class="{{ $chip }}">{{ $onuEnPuerto }} ONU</span>
                                                    @endif
                                                    @if($naps > 0)
                                                        <span class="{{ $chip }}">{{ $naps }} NAP</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 flex-wrap items-center gap-1">
                                                @if($numeroPon && $onuEnPuerto > 0)
                                                    <a href="{{ route('sistema.olts.pon-onus', [$olt, $numeroPon]) }}"
                                                       class="{{ $btn }} text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Ver ONUs</a>
                                                @endif
                                                @if($numeroPon && $olt->tieneCredencialesGestion())
                                                    <form action="{{ route('sistema.olts.refresh-onu-detalles-pon', [$olt, $numeroPon]) }}" method="POST"
                                                          class="inline js-olt-consulta"
                                                          data-confirm="¿Consultar descripción y RX de las ONUs en PON 0/{{ $numeroPon }}?"
                                                          data-loading="Consultando PON 0/{{ $numeroPon }}…">
                                                        @csrf
                                                        <button type="submit" class="{{ $btn }} text-purple-700 hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-purple-900/40">Consultar</button>
                                                    </form>
                                                @endif
                                                @if($sp)
                                                    <a href="{{ route('sistema.cajas-nap.create', [
                                                        'nodo_id' => $sp->nodo_id ?: $olt->nodo_id,
                                                        'salida_pon_id' => $sp->salida_pon_id,
                                                        'return_olt' => $olt->olt_id,
                                                    ]) }}"
                                                       class="{{ $btn }} text-purple-700 hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-purple-900/40">+ Caja NAP</a>
                                                @endif
                                                @if($p)
                                                    <a href="{{ route('sistema.olt-puertos.edit', $p) }}"
                                                       class="{{ $btn }} text-purple-700 hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-purple-900/40">Editar</a>
                                                    <form action="{{ route('sistema.olt-puertos.destroy', $p) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar puerto?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="{{ $btn }} text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">Eliminar</button>
                                                    </form>
                                                @elseif($sp)
                                                    <a href="{{ route('sistema.salida-pons.edit', ['salida_pon' => $sp, 'return_olt' => $olt->olt_id]) }}"
                                                       class="{{ $btn }} text-purple-700 hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-purple-900/40">Editar salida</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @if(!empty($onuSyncNotice))
        <div class="mt-6 rounded-lg border px-4 py-3 text-sm {{ ($onuSyncNotice['success'] ?? false) ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200' }}">
            {{ $onuSyncNotice['message'] ?? '' }}
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700 dark:bg-gray-900/40">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">ONUs (importadas desde OLT)</h2>
                @if($olt->tieneCredencialesGestion())
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">La vista carga al instante; las consultas al equipo corren en segundo plano.</p>
                @endif
                @if($olt->onus_synced_at)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Última consulta: {{ $olt->onus_synced_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        · Total: {{ $onus->count() }}
                        · Online: <span class="text-green-600 dark:text-green-400">{{ $onusOnline }}</span>
                        · Offline/alarma: <span class="text-amber-600 dark:text-amber-400">{{ $onusOffline }}</span>
                        @if(($onusDesconocido ?? 0) > 0)
                            · Sin estado: <span class="text-gray-500">{{ $onusDesconocido }}</span>
                        @endif
                    </p>
                @else
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aún no se importaron ONUs desde el equipo.</p>
                @endif
                @if($olt->onus_sync_error)
                    <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded border border-red-200 bg-red-50 px-2 py-1.5 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">Último error:
{{ $olt->onus_sync_error }}</pre>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if($olt->tieneCredencialesGestion())
                    <form action="{{ route('sistema.olts.test-gestion', $olt) }}" method="POST" class="inline js-olt-consulta"
                          data-loading="Probando conexión…"
                          data-keep-page="1">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            Probar conexión
                        </button>
                    </form>
                    <form action="{{ route('sistema.olts.import-onus', $olt) }}" method="POST" class="inline js-olt-consulta"
                          data-confirm="¿Importar lista completa de ONUs desde el OLT? Puede tardar un minuto."
                          data-loading="Importando ONUs…"
                          data-reload="{{ route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg bg-purple-600 px-3 py-2 text-sm font-medium text-white hover:bg-purple-700">
                            Importar ONUs
                        </button>
                    </form>
                    <a href="{{ route('sistema.olts.show', ['olt' => $olt, 'sin_sync' => 1]) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700" title="Ver datos guardados sin consultar el OLT">
                        Ver sin consultar
                    </a>
                @else
                    <a href="{{ route('sistema.olts.edit', $olt) }}" class="inline-flex items-center rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">
                        Configurar acceso Telnet
                    </a>
                @endif
            </div>
        </div>
        <div class="p-6">
            @if($onus->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No hay ONUs importadas. Configurá IP y contraseña de gestión, habilitá Telnet en la OLT y usá «Importar ONUs». Luego consultá cada PON desde la sección «Puertos PON».
                </p>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Hay {{ $onus->count() }} ONU(s) importadas. Usá <strong>Consultar</strong> en cada puerto PON para obtener descripción y potencia RX, o <strong>Ver ONUs</strong> para ver los datos ya guardados.
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
