@extends('layouts.app')

@section('title', 'Configuración - Facturación y servicios')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Configuración de facturación y servicios</h1>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('configuracion.facturacion.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- Facturación interna --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Facturación interna</h2>
 
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="dia_creacion_factura_automatica" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Día de facturación</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Día del mes (1-31) en que se generan automáticamente las facturas internas.</p>
                    <select name="dia_creacion_factura_automatica" id="dia_creacion_factura_automatica"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" {{ old('dia_creacion_factura_automatica', $params['dia_creacion_factura_automatica'] ?? 1) == $d ? 'selected' : '' }}>
                                Día {{ $d }} del mes
                            </option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="dia_fecha_cobro" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Día de fecha de cobro</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Día del mes para la fecha de cobro de la factura.</p>
                    <select name="dia_fecha_cobro" id="dia_fecha_cobro"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" {{ old('dia_fecha_cobro', $params['dia_fecha_cobro'] ?? 1) == $d ? 'selected' : '' }}>
                                Día {{ $d }} del mes
                            </option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="dia_vencimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Día de vencimiento</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Día del mes de vencimiento de la factura interna.</p>
                    <select name="dia_vencimiento" id="dia_vencimiento"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" {{ old('dia_vencimiento', $params['dia_vencimiento'] ?? 5) == $d ? 'selected' : '' }}>
                                Día {{ $d }} del mes
                            </option>
                        @endfor
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="dia_corte" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Día de corte automático</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Día del mes en que se ejecuta el corte por falta de pago.</p>
                        <select name="dia_corte" id="dia_corte"
                                class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                            @for($d = 1; $d <= 31; $d++)
                                <option value="{{ $d }}" {{ old('dia_corte', $params['dia_corte'] ?? 6) == $d ? 'selected' : '' }}>
                                    Día {{ $d }} del mes
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label for="hora_corte_automatico" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hora de corte automático</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Hora del día en que se ejecuta el corte.</p>
                        <input type="time" name="hora_corte_automatico" id="hora_corte_automatico"
                               value="{{ old('hora_corte_automatico', $params['hora_corte_automatico'] ?? '00:01') }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
            </div>
        </div>

        {{-- Notificaciones --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Notificaciones</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Tipo de plataforma y momento de envío de recordatorios.</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="notificacion_tipo_plataforma" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de plataforma</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Dónde se enviarán las notificaciones de recordatorio de pago.</p>
                    <select name="notificacion_tipo_plataforma" id="notificacion_tipo_plataforma"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                        <option value="web" {{ old('notificacion_tipo_plataforma', $params['notificacion_tipo_plataforma'] ?? 'web') == 'web' ? 'selected' : '' }}>Web (dentro de la plataforma)</option>
                        <option value="email" {{ old('notificacion_tipo_plataforma', $params['notificacion_tipo_plataforma'] ?? 'web') == 'email' ? 'selected' : '' }}>Correo electrónico</option>
                        <option value="ambas" {{ old('notificacion_tipo_plataforma', $params['notificacion_tipo_plataforma'] ?? 'web') == 'ambas' ? 'selected' : '' }}>Web y correo</option>
                    </select>
                </div>
                <div>
                    <label for="notificacion_dias_antes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Días antes del vencimiento</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Días antes del vencimiento de la factura para enviar recordatorio de pago.</p>
                    <input type="number" name="notificacion_dias_antes" id="notificacion_dias_antes"
                           value="{{ old('notificacion_dias_antes', $params['notificacion_dias_antes'] ?? 3) }}"
                           min="0" max="30"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                Guardar configuración
            </button>
            <a href="{{ route('configuracion.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
