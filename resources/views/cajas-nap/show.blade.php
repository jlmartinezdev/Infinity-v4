@extends('layouts.app')

@section('title', 'Caja NAP: ' . $cajaNap->codigo)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('sistema.cajas-nap.index') }}" class="text-purple-600 hover:text-purple-700 text-sm font-medium">&larr; Volver a cajas NAP</a>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">Caja NAP: {{ $cajaNap->codigo }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $cajaNap->nodo?->descripcion }} — {{ ucfirst($cajaNap->tipo) }}</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-sm">{{ session('error') }}</div>
    @endif

    @php
        $napCap = $cajaNap->splitter_segundo_nivel ? (int) $cajaNap->splitter_segundo_nivel : 0;
        $napPorPuerto = $cajaNap->puertosActivos->keyBy('numero_puerto');
        $napPuedeEditar = auth()->user()?->tienePermiso('sistema.editar');
        $napPuedeVerServicio = auth()->user()?->tienePermiso('servicios.ver');
        $napMostrarGrilla = in_array($napCap, [8, 16], true) && $cajaNap->puertosActivos->isNotEmpty();
    @endphp

    @if($cajaNap->splitter_segundo_nivel || $cajaNap->puertosActivos->isNotEmpty())
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Puertos cliente (FTTH)</h2>
            @if($cajaNap->splitter_segundo_nivel)
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                    Splitter segundo nivel: <span class="font-medium">1×{{ $cajaNap->splitter_segundo_nivel }}</span>
                    @php
                        $ocup = $cajaNap->puertosActivos->whereNotNull('servicio_id')->count();
                    @endphp
                    — <span class="text-gray-500 dark:text-gray-400">{{ $napCap - $ocup }} libres</span> de {{ $napCap }} puertos
                </p>
            @else
                <p class="text-sm text-amber-700 dark:text-amber-300 mb-4">Definí el splitter (1×8 o 1×16) en <a href="{{ route('sistema.cajas-nap.edit', $cajaNap) }}" class="underline font-medium">editar caja</a> para gestionar puertos.</p>
            @endif

            @if($napMostrarGrilla)
                <div
                    id="nap-puertos-app"
                    class="mt-4"
                    data-url-servicios="{{ route('sistema.cajas-nap.servicios-por-cliente', $cajaNap) }}"
                    data-url-buscar-cliente="{{ route('clientes.buscar') }}"
                    data-puede-editar="{{ $napPuedeEditar ? '1' : '0' }}"
                    data-puede-ver-servicio="{{ $napPuedeVerServicio ? '1' : '0' }}"
                    data-url-servicio-edit="{{ url('/servicios') }}"
                >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Cuadrícula: <span class="text-green-800 dark:text-green-300 font-medium">verde</span> libre · <span class="text-red-700 dark:text-red-300 font-medium">rojo</span> asignado. Tocá un puerto libre para elegir cliente y servicio.</p>
                    <div class="grid grid-cols-8 gap-2 max-w-2xl">
                        @for($n = 1; $n <= $napCap; $n++)
                            @php $p = $napPorPuerto->get($n); @endphp
                            @if($p)
                                @php
                                    $ocupado = (bool) $p->servicio_id;
                                    $cl = $p->servicio?->cliente;
                                    $nombreCliente = $cl ? trim($cl->nombre.' '.$cl->apellido) : '';
                                @endphp
                                <button
                                    type="button"
                                    class="nap-puerto-celda aspect-square min-h-[2.75rem] rounded-md border-2 border-gray-900 dark:border-gray-100 text-center text-sm font-bold text-white shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 {{ $ocupado ? 'bg-red-600 hover:bg-red-700' : 'bg-green-800 hover:bg-green-900' }} {{ $ocupado ? '' : ($napPuedeEditar ? 'cursor-pointer' : 'cursor-default opacity-80') }}"
                                    data-numero="{{ $n }}"
                                    data-libre="{{ $ocupado ? '0' : '1' }}"
                                    data-asignar-url="{{ route('sistema.cajas-nap.puertos-activos.asignar', [$cajaNap, $p]) }}"
                                    data-liberar-url="{{ route('sistema.cajas-nap.puertos-activos.liberar', [$cajaNap, $p]) }}"
                                    data-servicio-id="{{ $p->servicio_id ?? '' }}"
                                    data-cliente-nombre="{{ e($nombreCliente) }}"
                                    data-cedula="{{ $cl?->cedula ?? '' }}"
                                    data-potencia="{{ $p->potencia_cliente !== null ? number_format((float) $p->potencia_cliente, 2) : '' }}"
                                >{{ $n }}</button>
                            @else
                                <div class="aspect-square min-h-[2.75rem] rounded-md border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-xs text-gray-400" title="Sin registro de puerto">{{ $n }}</div>
                            @endif
                        @endfor
                    </div>
                </div>

                {{-- Modal: asignar servicio a puerto libre --}}
                <div id="nap-modal-asignar" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50" aria-hidden="true">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700 relative" role="dialog" aria-labelledby="nap-modal-asignar-titulo">
                        <button type="button" class="nap-modal-cerrar absolute top-3 right-3 p-1 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">&times;</button>
                        <h3 id="nap-modal-asignar-titulo" class="text-lg font-semibold text-gray-900 dark:text-gray-100 pr-8">Asignar puerto</h3>
                        <form id="nap-form-asignar" method="POST" action="#" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label for="nap-buscar-cliente" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar cliente</label>
                                <input type="text" id="nap-buscar-cliente" autocomplete="off" placeholder="Nombre, apellido o cédula…" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm" />
                                <div id="nap-resultados-cliente" class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 hidden divide-y divide-gray-100 dark:divide-gray-600"></div>
                            </div>
                            <div id="nap-servicios-wrap" class="hidden">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Elegí el servicio a enlazar</p>
                                <div id="nap-servicios-lista" class="space-y-2 max-h-44 overflow-y-auto"></div>
                                <input type="hidden" name="servicio_id" id="nap-asignar-servicio-id" value="" />
                            </div>
                            <div>
                                <label for="nap-asignar-potencia" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Potencia cliente (dBm, opcional)</label>
                                <input type="text" name="potencia_cliente" id="nap-asignar-potencia" inputmode="decimal" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm" placeholder="Ej. -22,5" />
                            </div>
                            <p id="nap-asignar-msg" class="text-sm text-amber-700 dark:text-amber-300 hidden"></p>
                            <div class="flex gap-2 justify-end">
                                <button type="button" class="nap-modal-cerrar px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm">Cancelar</button>
                                <button type="submit" id="nap-asignar-submit" class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm font-medium hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>Asignar</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Modal: ver cliente en puerto ocupado --}}
                <div id="nap-modal-ver" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50" aria-hidden="true">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-gray-200 dark:border-gray-700 relative" role="dialog" aria-labelledby="nap-modal-ver-titulo">
                        <button type="button" class="nap-modal-cerrar absolute top-3 right-3 p-1 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Cerrar">&times;</button>
                        <h3 id="nap-modal-ver-titulo" class="text-lg font-semibold text-gray-900 dark:text-gray-100 pr-8">Puerto</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div><dt class="text-gray-500 dark:text-gray-400">Cliente</dt><dd id="nap-ver-cliente" class="font-medium text-gray-900 dark:text-gray-100"></dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Cédula</dt><dd id="nap-ver-cedula" class="text-gray-800 dark:text-gray-200"></dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Servicio</dt><dd id="nap-ver-servicio" class="text-gray-800 dark:text-gray-200"></dd></div>
                            <div><dt class="text-gray-500 dark:text-gray-400">Potencia (dBm)</dt><dd id="nap-ver-potencia" class="text-gray-800 dark:text-gray-200"></dd></div>
                        </dl>
                        <div class="mt-6 flex flex-wrap gap-2">
                            <a id="nap-ver-link-servicio" href="#" class="hidden text-sm px-3 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700">Abrir servicio</a>
                            <form id="nap-form-liberar" method="POST" action="#" class="hidden" onsubmit="return confirm('¿Liberar este puerto?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm px-3 py-2 rounded-lg border border-red-300 text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20">Liberar puerto</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if($cajaNap->puertosActivos->isNotEmpty())
                <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400 px-3 py-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">Vista tabular (misma información que la cuadrícula)</p>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Puerto</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cliente / servicio</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Potencia (dBm)</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($cajaNap->puertosActivos as $p)
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-3 py-2 font-mono font-medium">{{ $p->numero_puerto }}</td>
                                    <td class="px-3 py-2">
                                        @if($p->servicio_id)
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200">Ocupado</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">Libre</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if($p->servicio)
                                            @php $cl = $p->servicio->cliente; @endphp
                                            <span class="font-medium">{{ $cl ? trim($cl->nombre.' '.$cl->apellido) : '—' }}</span>
                                            <span class="text-gray-500 dark:text-gray-400 text-xs ml-1">Serv. #{{ $p->servicio_id }}</span>
                                            @if(auth()->user()?->tienePermiso('servicios.ver'))
                                                <a href="{{ route('servicios.edit', $p->servicio_id) }}" class="ml-2 text-purple-600 dark:text-purple-400 hover:underline text-xs">Ver servicio</a>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $p->potencia_cliente !== null ? number_format((float) $p->potencia_cliente, 2) : '—' }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if(auth()->user()?->tienePermiso('sistema.editar'))
                                            @if($p->servicio_id)
                                                <form action="{{ route('sistema.cajas-nap.puertos-activos.liberar', [$cajaNap, $p]) }}" method="POST" class="inline" onsubmit="return confirm('¿Liberar este puerto?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-xs">Liberar</button>
                                                </form>
                                            @else
                                                <form action="{{ route('sistema.cajas-nap.puertos-activos.asignar', [$cajaNap, $p]) }}" method="POST" class="inline-flex flex-wrap items-center justify-end gap-1">
                                                    @csrf
                                                    <input type="number" name="servicio_id" required placeholder="ID servicio" class="w-24 px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs" min="1">
                                                    <input type="text" name="potencia_cliente" placeholder="Pot." class="w-16 px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs" inputmode="decimal">
                                                    <button type="submit" class="text-xs px-2 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">Asignar</button>
                                                </form>
                                            @endif
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif($cajaNap->splitter_segundo_nivel)
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">No hay filas de puertos aún; guardá de nuevo la caja en editar o esperá a que se sincronicen los puertos.</p>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos generales</h2>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-gray-500 dark:text-gray-400">Código</dt><dd class="font-medium">{{ $cajaNap->codigo }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Nodo</dt><dd>{{ $cajaNap->nodo?->descripcion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Tipo</dt><dd>{{ ucfirst($cajaNap->tipo) }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Salida PON</dt><dd>
                    @if($cajaNap->salidaPon)
                        <a href="{{ route('sistema.salida-pons.edit', $cajaNap->salidaPon) }}" class="text-purple-600 dark:text-purple-400 hover:underline">{{ $cajaNap->salidaPon->codigo }}</a>
                    @else
                        —
                    @endif
                </dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Splitter 1º nivel</dt><dd>{{ $cajaNap->splitter_primer_nivel ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Splitter FTTH (2º)</dt><dd>{{ $cajaNap->splitter_segundo_nivel ? '1×'.$cajaNap->splitter_segundo_nivel : '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Potencia salida</dt><dd>{{ $cajaNap->potencia_salida !== null ? number_format((float) $cajaNap->potencia_salida, 2).' dBm' : '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Descripción</dt><dd>{{ $cajaNap->descripcion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Nota</dt><dd>{{ $cajaNap->nota ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Dirección</dt><dd>{{ $cajaNap->direccion ?? '—' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Coordenadas</dt><dd>{{ $cajaNap->lat && $cajaNap->lon ? number_format($cajaNap->lat, 6) . ', ' . number_format($cajaNap->lon, 6) : '—' }}</dd></div>
            </dl>
            <div class="mt-4">
                <a href="{{ route('sistema.cajas-nap.edit', $cajaNap) }}" class="text-purple-600 dark:text-purple-400 hover:underline font-medium">Editar caja</a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Splitters primarios</h2>
                <a href="{{ route('sistema.splitters-primarios.create', $cajaNap) }}" class="text-sm px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700">+ Primario</a>
            </div>
            @if($cajaNap->splitterPrimarios->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay splitters primarios. <a href="{{ route('sistema.splitters-primarios.create', $cajaNap) }}" class="text-purple-600 hover:underline">Crear uno</a></p>
            @else
                <ul class="space-y-3">
                    @foreach($cajaNap->splitterPrimarios as $sp)
                        <li class="py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <span class="font-medium">{{ $sp->codigo }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">({{ $sp->ratio }})</span>
                                    @if($sp->potencia_entrada !== null || $sp->potencia_salida !== null)
                                        <span class="ml-2 text-xs text-cyan-600 dark:text-cyan-400">
                                            @if($sp->potencia_entrada !== null)
                                                Ent: {{ number_format($sp->potencia_entrada, 1) }} dBm
                                            @endif
                                            @if($sp->potencia_entrada !== null && $sp->potencia_salida !== null) · @endif
                                            @if($sp->potencia_salida !== null)
                                                Sal: {{ number_format($sp->potencia_salida, 1) }} dBm
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <a href="{{ route('sistema.splitters-primarios.edit', $sp) }}" class="text-purple-600 dark:text-purple-400 hover:underline text-sm">Editar</a>
                                    <form action="{{ route('sistema.splitters-primarios.destroy', $sp) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este splitter primario?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-sm">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                            @if($sp->splitterSecundarios->isNotEmpty())
                                <ul class="mt-2 ml-4 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                    @foreach($sp->splitterSecundarios as $ss)
                                        <li class="flex justify-between items-center">
                                            <span>{{ $ss->codigo }} ({{ $ss->ratio }})</span>
                                            @if($ss->potencia_entrada !== null || $ss->potencia_salida !== null)
                                                <span class="text-xs text-cyan-600 dark:text-cyan-400">
                                                    @if($ss->potencia_entrada !== null)
                                                        Ent: {{ number_format($ss->potencia_entrada, 1) }} dBm
                                                    @endif
                                                    @if($ss->potencia_entrada !== null && $ss->potencia_salida !== null) · @endif
                                                    @if($ss->potencia_salida !== null)
                                                        Sal: {{ number_format($ss->potencia_salida, 1) }} dBm
                                                    @endif
                                                </span>
                                            @endif
                                            <span>
                                                <a href="{{ route('sistema.splitters-secundarios.edit', $ss) }}" class="text-purple-600 hover:underline">Editar</a>
                                                <form action="{{ route('sistema.splitters-secundarios.destroy', $ss) }}" method="POST" class="inline ml-1" onsubmit="return confirm('¿Eliminar este splitter secundario?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                                                </form>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <a href="{{ route('sistema.splitters-secundarios.create', ['cajaNap' => $cajaNap, 'splitter_primario_id' => $sp->splitter_primario_id]) }}" class="mt-1 ml-4 text-xs text-purple-600 hover:underline">+ Secundario</a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('sistema.splitters-secundarios.create', $cajaNap) }}" class="mt-3 inline-block text-sm text-purple-600 dark:text-purple-400 hover:underline">+ Splitter secundario</a>
            @endif
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Enlace fibra (salida PON)</h2>
        @if($cajaNap->salidaPon)
            <p class="text-sm text-gray-700 dark:text-gray-300">
                <span class="font-medium">{{ $cajaNap->salidaPon->codigo }}</span>
                — puerto OLT {{ $cajaNap->salidaPon->puerto_olt }}
                @if($cajaNap->salidaPon->olt)
                    — OLT {{ $cajaNap->salidaPon->olt->codigo ?? $cajaNap->salidaPon->olt->ip ?? '#'.$cajaNap->salidaPon->olt_id }}
                @endif
            </p>
            <a href="{{ route('sistema.salida-pons.edit', $cajaNap->salidaPon) }}" class="text-sm text-purple-600 dark:text-purple-400 hover:underline mt-2 inline-block">Editar salida PON</a>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Esta caja no está enlazada a una salida PON. Podés hacerlo en <a href="{{ route('sistema.cajas-nap.edit', $cajaNap) }}" class="text-purple-600 hover:underline">editar caja</a>.</p>
        @endif
    </div>
</div>
@if($napMostrarGrilla ?? false)
@push('scripts')
<script>
(function() {
    var root = document.getElementById('nap-puertos-app');
    if (!root) return;

    var urlServicios = root.getAttribute('data-url-servicios') || '';
    var urlBuscarCliente = root.getAttribute('data-url-buscar-cliente') || '';
    var puedeEditar = root.getAttribute('data-puede-editar') === '1';
    var puedeVerServicio = root.getAttribute('data-puede-ver-servicio') === '1';
    var urlServicioEditBase = (root.getAttribute('data-url-servicio-edit') || '').replace(/\/$/, '');

    var modalAsignar = document.getElementById('nap-modal-asignar');
    var modalVer = document.getElementById('nap-modal-ver');
    var formAsignar = document.getElementById('nap-form-asignar');
    var inputBuscar = document.getElementById('nap-buscar-cliente');
    var resultadosCliente = document.getElementById('nap-resultados-cliente');
    var wrapServicios = document.getElementById('nap-servicios-wrap');
    var listaServicios = document.getElementById('nap-servicios-lista');
    var hiddenServicioId = document.getElementById('nap-asignar-servicio-id');
    var inputPotencia = document.getElementById('nap-asignar-potencia');
    var btnSubmitAsignar = document.getElementById('nap-asignar-submit');
    var msgAsignar = document.getElementById('nap-asignar-msg');
    var tituloAsignar = document.getElementById('nap-modal-asignar-titulo');

    var tituloVer = document.getElementById('nap-modal-ver-titulo');
    var elVerCliente = document.getElementById('nap-ver-cliente');
    var elVerCedula = document.getElementById('nap-ver-cedula');
    var elVerServicio = document.getElementById('nap-ver-servicio');
    var elVerPotencia = document.getElementById('nap-ver-potencia');
    var linkVerServicio = document.getElementById('nap-ver-link-servicio');
    var formLiberar = document.getElementById('nap-form-liberar');

    var debounceTimer = null;
    var clienteSeleccionadoId = null;

    /** Evita res.json() cuando Laravel devuelve HTML (login, 403, error). */
    function fetchJsonSeguro(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function(r) {
            return r.text().then(function(text) {
                var t = text.trim();
                if (t.charAt(0) === '<') {
                    var err = new Error('html');
                    err.esHtml = true;
                    throw err;
                }
                var data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    var err2 = new Error('parse');
                    throw err2;
                }
                if (!r.ok) {
                    var msg = (data && (data.message || (data.errors && JSON.stringify(data.errors)))) || ('Error ' + r.status);
                    var err3 = new Error(msg);
                    err3.status = r.status;
                    throw err3;
                }
                return data;
            });
        });
    }

    function mostrarModal(el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
        el.setAttribute('aria-hidden', 'false');
    }
    function ocultarModal(el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
        el.setAttribute('aria-hidden', 'true');
    }
    function cerrarTodos() {
        if (modalAsignar) ocultarModal(modalAsignar);
        if (modalVer) ocultarModal(modalVer);
    }

    document.querySelectorAll('.nap-modal-cerrar').forEach(function(btn) {
        btn.addEventListener('click', cerrarTodos);
    });
    [modalAsignar, modalVer].forEach(function(m) {
        if (!m) return;
        m.addEventListener('click', function(e) {
            if (e.target === m) cerrarTodos();
        });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarTodos();
    });

    function resetAsignarModal() {
        if (!formAsignar) return;
        formAsignar.reset();
        if (hiddenServicioId) hiddenServicioId.value = '';
        if (resultadosCliente) { resultadosCliente.innerHTML = ''; resultadosCliente.classList.add('hidden'); }
        if (wrapServicios) wrapServicios.classList.add('hidden');
        if (listaServicios) listaServicios.innerHTML = '';
        if (msgAsignar) { msgAsignar.textContent = ''; msgAsignar.classList.add('hidden'); }
        if (btnSubmitAsignar) btnSubmitAsignar.disabled = true;
        clienteSeleccionadoId = null;
    }

    function abrirAsignar(btn) {
        if (!modalAsignar || !formAsignar || !tituloAsignar) return;
        resetAsignarModal();
        formAsignar.action = btn.getAttribute('data-asignar-url') || '#';
        tituloAsignar.textContent = 'Asignar puerto ' + (btn.getAttribute('data-numero') || '');
        mostrarModal(modalAsignar);
        setTimeout(function() { inputBuscar && inputBuscar.focus(); }, 50);
    }

    function abrirVer(btn) {
        if (!modalVer) return;
        var num = btn.getAttribute('data-numero') || '';
        var nombre = btn.getAttribute('data-cliente-nombre') || '—';
        var cedula = btn.getAttribute('data-cedula') || '—';
        var sid = btn.getAttribute('data-servicio-id') || '';
        var pot = btn.getAttribute('data-potencia') || '';
        tituloVer.textContent = 'Puerto ' + num;
        elVerCliente.textContent = nombre;
        elVerCedula.textContent = cedula || '—';
        elVerServicio.textContent = sid ? ('#' + sid) : '—';
        elVerPotencia.textContent = pot ? pot : '—';
        if (puedeVerServicio && sid && linkVerServicio) {
            linkVerServicio.href = urlServicioEditBase + '/' + encodeURIComponent(sid) + '/edit';
            linkVerServicio.classList.remove('hidden');
        } else if (linkVerServicio) {
            linkVerServicio.classList.add('hidden');
        }
        if (puedeEditar && formLiberar) {
            formLiberar.action = btn.getAttribute('data-liberar-url') || '#';
            formLiberar.classList.remove('hidden');
        } else if (formLiberar) {
            formLiberar.classList.add('hidden');
        }
        mostrarModal(modalVer);
    }

    root.querySelectorAll('.nap-puerto-celda').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var libre = btn.getAttribute('data-libre') === '1';
            if (libre) {
                if (!puedeEditar) return;
                abrirAsignar(btn);
            } else {
                abrirVer(btn);
            }
        });
    });

    function mostrarMsgAsignar(texto) {
        if (!msgAsignar) return;
        msgAsignar.textContent = texto;
        msgAsignar.classList.remove('hidden');
    }

    function cargarServicios(clienteId) {
        if (!wrapServicios || !listaServicios || !hiddenServicioId || !btnSubmitAsignar) return;
        hiddenServicioId.value = '';
        btnSubmitAsignar.disabled = true;
        listaServicios.innerHTML = '<p class="text-sm text-gray-500">Cargando…</p>';
        wrapServicios.classList.remove('hidden');
        fetchJsonSeguro(urlServicios + '?cliente_id=' + encodeURIComponent(clienteId)).then(function(items) {
            listaServicios.innerHTML = '';
            if (!items || items.length === 0) {
                var p0 = document.createElement('p');
                p0.className = 'text-sm text-gray-600 dark:text-gray-400';
                p0.textContent = 'No hay servicios disponibles para asignar (cancelados o ya enlazados a otro puerto NAP).';
                listaServicios.appendChild(p0);
                return;
            }
            items.forEach(function(s) {
                var id = 'nap-srv-' + s.servicio_id;
                var label = document.createElement('label');
                label.className = 'flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50';
                label.setAttribute('for', id);
                var radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'nap_srv_pick';
                radio.className = 'nap-srv-radio';
                radio.value = String(s.servicio_id);
                radio.id = id;
                var span = document.createElement('span');
                span.className = 'text-sm text-gray-900 dark:text-gray-100';
                span.appendChild(document.createTextNode(s.label || ('#' + s.servicio_id)));
                var spanEst = document.createElement('span');
                spanEst.className = 'text-gray-500';
                spanEst.textContent = ' (' + (s.estado || '') + ')';
                span.appendChild(spanEst);
                label.appendChild(radio);
                label.appendChild(span);
                listaServicios.appendChild(label);
                radio.addEventListener('change', function() {
                    hiddenServicioId.value = radio.value;
                    btnSubmitAsignar.disabled = false;
                });
            });
        }).catch(function(err) {
            var m = err && err.esHtml
                ? 'Sesión vencida o sin permiso (sistema.editar). Recargá la página.'
                : (err && err.message ? err.message : 'No se pudo cargar la lista de servicios.');
            listaServicios.innerHTML = '';
            var pe = document.createElement('p');
            pe.className = 'text-sm text-red-600 dark:text-red-400';
            pe.textContent = m;
            listaServicios.appendChild(pe);
        });
    }

    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var q = inputBuscar.value.trim();
            if (q.length < 2) {
                resultadosCliente.innerHTML = '';
                resultadosCliente.classList.add('hidden');
                wrapServicios.classList.add('hidden');
                return;
            }
            debounceTimer = setTimeout(function() {
                fetchJsonSeguro(urlBuscarCliente + '?q=' + encodeURIComponent(q)).then(function(items) {
                    resultadosCliente.innerHTML = '';
                    if (!items || items.length === 0) {
                        resultadosCliente.classList.add('hidden');
                        return;
                    }
                    items.forEach(function(c) {
                        var label = ((c.nombre || '') + ' ' + (c.apellido || '')).trim() + (c.cedula ? ' (' + c.cedula + ')' : '');
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'w-full text-left px-3 py-2 text-sm hover:bg-purple-50 dark:hover:bg-purple-900/30 text-gray-900 dark:text-gray-100';
                        b.textContent = label || ('Cliente #' + c.cliente_id);
                        b.addEventListener('click', function() {
                            clienteSeleccionadoId = c.cliente_id;
                            inputBuscar.value = label || ('Cliente #' + c.cliente_id);
                            resultadosCliente.innerHTML = '';
                            resultadosCliente.classList.add('hidden');
                            cargarServicios(c.cliente_id);
                        });
                        resultadosCliente.appendChild(b);
                    });
                    resultadosCliente.classList.remove('hidden');
                }).catch(function(err) {
                    resultadosCliente.innerHTML = '';
                    resultadosCliente.classList.add('hidden');
                    if (err && err.esHtml && msgAsignar) {
                        msgAsignar.textContent = 'No se pudo buscar clientes (sesión o permiso clientes.ver). Recargá la página.';
                        msgAsignar.classList.remove('hidden');
                    }
                });
            }, 280);
        });
    }

    if (formAsignar) {
        formAsignar.addEventListener('submit', function(e) {
            if (!hiddenServicioId || !hiddenServicioId.value) {
                e.preventDefault();
                mostrarMsgAsignar('Elegí un servicio de la lista.');
                return;
            }
        });
    }
})();
</script>
@endpush
@endif
@endsection
