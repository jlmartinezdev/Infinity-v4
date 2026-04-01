@extends('layouts.app')

@section('title', 'Agenda')

@php
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $diasSemana = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
    $diaSemanaLargo = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
    $fechaAnterior = $fecha->copy()->subDay()->format('Y-m-d');
    $fechaSiguiente = $fecha->copy()->addDay()->format('Y-m-d');
    $mesAnterior = $mesInicio->copy()->subMonth()->format('Y-m-d');
    $mesSiguiente = $mesInicio->copy()->addMonth()->format('Y-m-d');
@endphp

@section('content')
<div class="max-w-7xl mx-auto">
    {{-- Barra superior --}}
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-t-xl bg-gray-700 dark:bg-gray-800 text-white">
        <div class="flex items-center gap-2">
            <a href="{{ route('agenda.index', array_merge(request()->query(), ['fecha' => $fechaAnterior])) }}"
                class="p-2 rounded-lg hover:bg-gray-600 dark:hover:bg-gray-700 transition-colors" title="Día anterior" aria-label="Día anterior">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route('agenda.index', array_merge(request()->query(), ['fecha' => $hoy])) }}"
                class="px-3 py-1.5 rounded-lg border border-gray-500 hover:bg-gray-600 dark:hover:bg-gray-700 transition-colors text-sm font-medium">
                Hoy
            </a>
            <a href="{{ route('agenda.index', array_merge(request()->query(), ['fecha' => $fechaSiguiente])) }}"
                class="p-2 rounded-lg hover:bg-gray-600 dark:hover:bg-gray-700 transition-colors" title="Día siguiente" aria-label="Día siguiente">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-lg border border-gray-500 text-sm font-medium">Vista día</span>
            <a href="{{ route('agenda.create') }}?fecha={{ $fechaStr }}"
                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-400 transition-colors text-sm">
                Nueva cita
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-b-xl shadow border border-t-0 border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col md:flex-row">
        {{-- Panel izquierdo: vista día --}}
        <div class="flex-1 min-w-0 border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
            <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $fecha->day }} de {{ $meses[$fecha->month - 1] }} de {{ $fecha->year }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $diaSemanaLargo[$fecha->dayOfWeekIso - 1] }}</p>
            </div>
            <div class="overflow-y-auto max-h-[calc(100vh-16rem)]" style="min-height: 420px;">
                @for ($h = 0; $h < 24; $h++)
                    @php
                        $horaStr = sprintf('%02d:00', $h);
                        $citasEnHora = $citasDia->filter(function ($cita) use ($h) {
                            $inicio = $cita->hora_inicio ? \Carbon\Carbon::parse($cita->hora_inicio)->hour : 0;
                            return $inicio == $h;
                        });
                    @endphp
                    <div class="flex border-b border-gray-100 dark:border-gray-700 min-h-[52px]">
                        <div class="w-16 flex-shrink-0 py-1.5 pr-2 text-right text-xs text-gray-500 dark:text-gray-400 font-medium">
                            {{ $h <= 12 ? ($h == 0 ? '12' : $h) . 'AM' : ($h == 12 ? '12' : $h - 12) . 'PM' }}
                        </div>
                        <div class="flex-1 py-0.5 pl-2">
                            @foreach ($citasEnHora as $cita)
                                @php $estados = App\Models\Agenda::estados(); $mapsUrl = $cita->ubicacion_maps_url; @endphp
                                <div class="flex items-start gap-2 mb-1 px-3 py-2 rounded-lg border-l-4 bg-purple-50 dark:bg-purple-900/20 border-purple-500 dark:border-purple-400 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors group">
                                    <a href="{{ route('agenda.edit', $cita) }}" class="flex-1 min-w-0 text-left">
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $cita->descripcion_corta }}
                                        </span>
                                        <span class="text-xs text-gray-600 dark:text-gray-400 block mt-0.5">
                                            {{ $cita->hora_inicio ? \Carbon\Carbon::parse($cita->hora_inicio)->format('H:i') : '' }}
                                            @if($cita->hora_fin)
                                                - {{ \Carbon\Carbon::parse($cita->hora_fin)->format('H:i') }}
                                            @endif
                                            @if($cita->usuario)
                                                · {{ $cita->usuario->name }}
                                            @endif
                                        </span>
                                        <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded text-xs font-medium
                                            @if($cita->estado === 'completado') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                            @elseif($cita->estado === 'cancelado' || $cita->estado === 'no_asistio') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                            @elseif($cita->estado === 'en_progreso') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 @endif">
                                            {{ $estados[$cita->estado] ?? $cita->estado }}
                                        </span>
                                    </a>
                                    <div class="flex items-center gap-0.5 flex-shrink-0">
                                        @if($mapsUrl)
                                            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer" class="p-1.5 rounded text-gray-500 dark:text-gray-400 hover:bg-purple-200 dark:hover:bg-purple-800 hover:text-blue-700 dark:hover:text-blue-400 transition-colors" title="Abrir ubicación en mapa" aria-label="Ubicación">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            </a>
                                        @endif
                                        <form action="{{ route('agenda.destroy', $cita) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta cita de la agenda?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded text-gray-500 dark:text-gray-400 hover:bg-red-100 dark:hover:bg-red-900/30 hover:text-red-700 dark:hover:text-red-400 transition-colors" title="Eliminar" aria-label="Eliminar">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Panel derecho: mini calendario --}}
        <div class="w-full md:w-80 flex-shrink-0 p-4 md:border-l border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center justify-between mb-3">
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ ucfirst($meses[$mesInicio->month - 1]) }} {{ $mesInicio->year }}</span>
                <div class="flex gap-1">
                    <a href="{{ route('agenda.index', array_merge(request()->query(), ['fecha' => $mesAnterior])) }}"
                        class="p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" title="Mes anterior" aria-label="Mes anterior">
                        <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <a href="{{ route('agenda.index', array_merge(request()->query(), ['fecha' => $mesSiguiente])) }}"
                        class="p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" title="Mes siguiente" aria-label="Mes siguiente">
                        <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
            <table class="w-full text-center border-collapse" role="grid" aria-label="Calendario del mes">
                <thead>
                    <tr class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        @foreach ($diasSemana as $d)
                            <th class="w-9 py-1" scope="col">{{ $d }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
            @php
                $inicio = $mesInicio->copy()->startOfMonth();
                $fin = $mesFin->copy()->endOfMonth();
                $primerLunes = $inicio->copy();
                if ($primerLunes->dayOfWeekIso !== 1) {
                    $primerLunes->subDays($primerLunes->dayOfWeekIso - 1);
                }
                $ultimoDomingo = $fin->copy();
                if ($ultimoDomingo->dayOfWeekIso !== 7) {
                    $ultimoDomingo->addDays(7 - $ultimoDomingo->dayOfWeekIso);
                }
                $celdas = $primerLunes->diffInDays($ultimoDomingo) + 1;
            @endphp
                @for ($i = 0; $i < $celdas; $i++)
                    @if ($i % 7 === 0)
                    <tr>
                    @endif
                    @php
                        $dia = $primerLunes->copy()->addDays($i);
                        $dayStr = $dia->format('Y-m-d');
                        $esMesActual = $dia->month === $mesInicio->month;
                        $esSeleccionado = $dayStr === $fechaStr;
                        $esHoy = $dayStr === $hoy;
                        $tieneCitas = isset($fechasConCitas[$dayStr]);
                    @endphp
                    <td class="w-9 py-0.5 align-middle">
                        <a href="{{ route('agenda.index', array_merge(request()->query(), ['fecha' => $dayStr])) }}"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm no-underline
                                @if($esSeleccionado) bg-gray-900 dark:bg-purple-600 text-white font-semibold
                                @elseif($esHoy && !$esSeleccionado) text-purple-600 dark:text-purple-400 font-semibold ring-1 ring-purple-400 dark:ring-purple-500
                                @elseif($esMesActual) text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600
                                @else text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-600 @endif
                                @if($tieneCitas && !$esSeleccionado) ring-1 ring-inset ring-purple-300 dark:ring-purple-600 @endif">
                            {{ $dia->day }}
                        </a>
                    </td>
                    @if ($i % 7 === 6 || $i === $celdas - 1)
                        @php $restantes = 7 - (($i % 7) + 1); @endphp
                        @for ($k = 0; $k < $restantes; $k++)
                            <td class="w-9 py-0.5">&nbsp;</td>
                        @endfor
                    </tr>
                    @endif
                @endfor
                </tbody>
            </table>

            {{-- Filtros opcionales (colapsables o compactos) --}}
            <form method="GET" action="{{ route('agenda.index') }}" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <input type="hidden" name="fecha" value="{{ $fechaStr }}">
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Estado</label>
                    <select name="estado" class="w-full px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white dark:bg-gray-700 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach (App\Models\Agenda::estados() as $key => $label)
                            <option value="{{ $key }}" {{ request('estado') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Técnico</label>
                    <select name="usuario_id" class="w-full px-2 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 bg-white dark:bg-gray-700 dark:text-gray-100">
                        <option value="">Todos</option>
                        @foreach ($tecnicos as $t)
                            <option value="{{ $t->usuario_id }}" {{ request('usuario_id') == $t->usuario_id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="w-full mt-2 px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-gray-500">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
