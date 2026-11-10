@extends('layouts.app')

@section('title', 'Gastos')

@section('content')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
    $cls = 'w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none';
    $lbl = 'block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1';

    $resumen = $resumenEgresos ?? [
        'cantidad_mes' => $filas->count(),
        'total_mes' => (float) $filas->sum('importe'),
        'opex_mes' => 0.0,
        'capex_mes' => 0.0,
        'plantillas_count' => $plantillas->count(),
        'plantillas_total' => (float) $plantillas->sum('monto'),
    ];
@endphp

<div class="max-w-7xl mx-auto">
    @include('finanzas._nav')

    {{-- Header Principal --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                Gastos e inversiones
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                    {{ $fmt($resumen['cantidad_mes']) }} en el mes
                </span>
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-3xl">
                Elegí un gasto habitual para precargar el pago. Editar la lista no registra un pago: el movimiento se contabiliza en el formulario, con el monto que salió, el documento y la factura.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="abrir-habituales"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors">
                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Editar gastos habituales</span>
            </button>
            <a href="{{ route('finanzas.libro', ['tipo' => 'egreso']) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Ver egresos en el libro</span>
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-6 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm flex items-center gap-2.5">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{-- Deck de KPIs del Mes --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Egresos del mes</span>
                <span class="p-1.5 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 font-mono">{{ $fmt($resumen['total_mes']) }} <span class="text-xs font-semibold text-gray-500">PYG</span></p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 capitalize">{{ now()->translatedFormat('F Y') }} · {{ $fmt($resumen['cantidad_mes']) }} pago{{ $resumen['cantidad_mes'] === 1 ? '' : 's' }}</p>
        </div>

        <div class="rounded-xl border border-sky-200/80 dark:border-sky-800/50 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-400">OPEX del mes</span>
                <span class="p-1.5 rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-sky-700 dark:text-sky-400 mt-2 font-mono">{{ $fmt($resumen['opex_mes']) }} <span class="text-xs font-semibold">PYG</span></p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Gastos operativos contabilizados</p>
        </div>

        <div class="rounded-xl border border-purple-200/80 dark:border-purple-800/50 bg-white dark:bg-gray-800 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-purple-700 dark:text-purple-400">CAPEX del mes</span>
                <span class="p-1.5 rounded-lg bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-purple-700 dark:text-purple-400 mt-2 font-mono">{{ $fmt($resumen['capex_mes']) }} <span class="text-xs font-semibold">PYG</span></p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Inversiones y equipamiento</p>
        </div>

        <div class="rounded-xl border border-emerald-200/80 dark:border-emerald-800/50 bg-emerald-50/40 dark:bg-emerald-950/20 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Gastos habituales</span>
                <span class="p-1.5 rounded-lg bg-emerald-200/70 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-emerald-800 dark:text-emerald-200 mt-2 font-mono">{{ $fmt($resumen['plantillas_total']) }} <span class="text-xs font-semibold">PYG</span></p>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $fmt($resumen['plantillas_count']) }} plantilla{{ $resumen['plantillas_count'] === 1 ? '' : 's' }} activa{{ $resumen['plantillas_count'] === 1 ? '' : 's' }} de referencia</p>
        </div>
    </div>

    <div class="mb-8 max-w-3xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden" id="bloque-pago">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/30 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Registrar pago (egreso)</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Descuenta el saldo de la cuenta y asienta en el libro</p>
                    </div>
                </div>
            </div>

            <form method="post" action="{{ route('finanzas.egresos.store') }}" enctype="multipart/form-data" class="p-5 space-y-4">
                @csrf

                {{-- 1. Origen del gasto: Plantilla o Libre --}}
                <div>
                    <label class="{{ $lbl }}" for="plantilla">Gasto habitual</label>
                    <select name="plantilla_id" id="plantilla" class="{{ $cls }}">
                        <option value="">Otro gasto, sin plantilla</option>
                        @foreach($plantillas as $plantilla)
                            <option value="{{ $plantilla->id }}"
                                data-monto="{{ $fmt($plantilla->monto) }}"
                                data-staff="{{ $plantilla->usuario_staff_id }}"
                                data-sueldo="{{ $plantilla->es_sueldo ? '1' : '0' }}"
                                @selected(old('plantilla_id') == $plantilla->id)>
                                {{ $plantilla->nombre }} ({{ $fmt($plantilla->monto) }} Gs)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="descripcion-libre">
                    <label class="{{ $lbl }}" for="descripcion">Descripción *</label>
                    <input id="descripcion" name="descripcion" value="{{ old('descripcion') }}" maxlength="160" class="{{ $cls }}" placeholder="Qué se pagó (ej. Compra de conectores, Viático...)" required>
                </div>

                <div id="staff-pago" class="hidden">
                    <label class="{{ $lbl }}" for="staff">Staff del sueldo *</label>
                    <select name="usuario_staff_id" id="staff" class="{{ $cls }}">
                        <option value="">Elegir persona</option>
                        @foreach($staff as $persona)
                            <option value="{{ $persona->usuario_id }}" @selected(old('usuario_staff_id') == $persona->usuario_id)>{{ $persona->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 2. Importe y Fecha --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $lbl }}" for="importe">Importe que salió *</label>
                        <input name="importe" id="importe" value="{{ old('importe') }}" required class="{{ $cls }} font-mono font-semibold" placeholder="Se puede cambiar respecto al monto habitual">
                    </div>
                    <div>
                        <label class="{{ $lbl }}" for="fecha-pago">Fecha de pago *</label>
                        <input id="fecha-pago" type="date" name="fecha" required value="{{ old('fecha', now()->toDateString()) }}" class="{{ $cls }}">
                    </div>
                </div>

                {{-- 3. Cuenta y Categoría --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $lbl }}" for="cuenta-pago">Cuenta *</label>
                        <select id="cuenta-pago" name="cuenta_id" class="{{ $cls }}" required>
                            @foreach($cuentas as $c)
                                <option value="{{ $c->id }}" @selected(old('cuenta_id') == $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}" for="categoria-pago">Categoría *</label>
                        <select id="categoria-pago" name="categoria_id" class="{{ $cls }}" required>
                            @foreach($categorias as $c)
                                <option value="{{ $c->id }}" @selected(old('categoria_id') ? old('categoria_id') == $c->id : $c->nombre === 'Gasto operativo')>
                                    {{ strtoupper($c->clase) }} — {{ $c->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Crear nueva categoría opcional (colapsable limpio) --}}
                <details class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 px-3 py-2 bg-gray-50/50 dark:bg-gray-700/20" @if(old('nueva_categoria')) open @endif>
                    <summary class="cursor-pointer text-xs font-medium text-emerald-700 dark:text-emerald-400 select-none">
                        + Crear nueva categoría al guardar (opcional)
                    </summary>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 mt-2.5">
                        <div class="sm:col-span-2">
                            <label class="{{ $lbl }}">Nueva categoría</label>
                            <input name="nueva_categoria" value="{{ old('nueva_categoria') }}" class="{{ $cls }}" placeholder="Nombre de la categoría">
                        </div>
                        <div>
                            <label class="{{ $lbl }}">Clase</label>
                            <select name="nueva_clase" class="{{ $cls }}">
                                <option value="opex" @selected(old('nueva_clase') === 'opex')>OPEX</option>
                                <option value="capex" @selected(old('nueva_clase') === 'capex')>CAPEX</option>
                            </select>
                        </div>
                    </div>
                </details>

                {{-- 4. Proveedor y Comprobante --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-gray-100 dark:border-gray-700/60">
                    <div>
                        <label class="{{ $lbl }}" for="proveedor-pago">Proveedor</label>
                        <select id="proveedor-pago" name="proveedor_id" class="{{ $cls }}">
                            <option value="">Sin proveedor</option>
                            @foreach($proveedores as $p)
                                <option value="{{ $p->id }}" @selected(old('proveedor_id') == $p->id)>{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $lbl }}" for="comprobante-pago">Número de comprobante</label>
                        <input id="comprobante-pago" name="comprobante" value="{{ old('comprobante') }}" class="{{ $cls }}" placeholder="Recibo / Factura Nº">
                    </div>
                </div>

                {{-- 5. Adjuntos: Documento y Factura --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $lbl }}" for="archivo-documento">Documento</label>
                        <input id="archivo-documento" type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="w-full text-xs text-gray-600 dark:text-gray-300 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-1">
                    </div>
                    <div>
                        <label class="{{ $lbl }}" for="archivo-factura">Factura</label>
                        <input id="archivo-factura" type="file" name="factura_archivo" accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="w-full text-xs text-gray-600 dark:text-gray-300 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-1">
                    </div>
                </div>

                {{-- 6. Observaciones --}}
                <div>
                    <label class="{{ $lbl }}" for="obs-pago">Observaciones</label>
                    <textarea id="obs-pago" name="observaciones" class="{{ $cls }}" rows="2" placeholder="Detalle adicional opcional...">{{ old('observaciones') }}</textarea>
                </div>

                <div class="pt-1">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Registrar egreso</span>
                    </button>
                </div>
            </form>
        </div>

</div>

@php
    $abrirHabituales = $errors->has('nombre') || $errors->has('monto');
@endphp
<div id="modal-habituales" class="{{ $abrirHabituales ? '' : 'hidden' }} fixed inset-x-0 bottom-0 top-16 z-[60] overflow-y-auto bg-black/50 backdrop-blur-sm p-4 sm:p-6">
    <div class="mx-auto max-w-5xl overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            Gastos habituales
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                {{ $plantillas->count() }}
                            </span>
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cambiar nombre o monto actualiza la lista y no registra un pago. «Usar en el pago» precarga el formulario.</p>
                    </div>
                </div>
                <button type="button" id="cerrar-habituales" class="shrink-0 rounded-lg bg-gray-200 hover:bg-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 transition-colors">Cerrar</button>
            </div>

            {{-- Barra superior para agregar nuevo gasto habitual --}}
            <form method="post" action="{{ route('finanzas.egresos.plantillas.store') }}" class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/20">
                @csrf
                <div class="flex flex-wrap items-end gap-2.5">
                    <div class="flex-1 min-w-[12rem]">
                        <label class="{{ $lbl }}" for="nuevo-nombre">Nueva plantilla habitual</label>
                        <input id="nuevo-nombre" name="nombre" value="{{ old('nombre') }}" required maxlength="160" class="{{ $cls }}" placeholder="Descripción del gasto habitual">
                    </div>
                    <div class="w-36">
                        <label class="{{ $lbl }}" for="nuevo-monto">Monto (Gs)</label>
                        <input id="nuevo-monto" name="monto" value="{{ old('monto') }}" required class="{{ $cls }} font-mono" placeholder="0">
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300 px-2.5 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 cursor-pointer select-none">
                        <input type="checkbox" name="es_sueldo" id="nuevo-sueldo" value="1" class="rounded text-emerald-600 focus:ring-emerald-500" @checked(old('es_sueldo'))>
                        <span>Sueldo</span>
                    </label>
                    <div id="nuevo-staff" class="{{ old('es_sueldo') ? '' : 'hidden' }} min-w-[11rem]">
                        <label class="{{ $lbl }}" for="nuevo-staff-select">Staff</label>
                        <select id="nuevo-staff-select" name="usuario_staff_id" class="{{ $cls }}">
                            <option value="">Elegir persona</option>
                            @foreach($staff as $persona)
                                <option value="{{ $persona->usuario_id }}" @selected(old('usuario_staff_id') == $persona->usuario_id)>{{ $persona->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Agregar gasto habitual</span>
                    </button>
                </div>
            </form>

            {{-- Tabla editable de plantillas --}}
            <div class="overflow-x-auto max-h-[34rem] overflow-y-auto">
                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50/90 dark:bg-gray-700/60 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Gasto habitual</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monto / Staff / Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @foreach($plantillas as $plantilla)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors" data-fila-plantilla="{{ $plantilla->id }}">
                                <td class="px-4 py-2.5 align-middle">
                                    <div class="flex items-center gap-2">
                                        <input form="plantilla-{{ $plantilla->id }}" name="nombre" value="{{ $plantilla->nombre }}" required maxlength="160"
                                            aria-label="Descripción de {{ $plantilla->nombre }}"
                                            class="w-full min-w-[11rem] px-2.5 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:outline-none">
                                        @if($plantilla->es_sueldo)
                                            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                                Sueldo
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 align-middle">
                                    <form id="plantilla-{{ $plantilla->id }}" method="post" action="{{ route('finanzas.egresos.plantilla', $plantilla) }}" class="flex flex-wrap items-center justify-end gap-2">
                                        @csrf
                                        <input name="monto" value="{{ $fmt($plantilla->monto) }}" required
                                            class="w-32 px-2.5 py-1.5 text-xs font-mono font-semibold text-right rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:outline-none">
                                        @if($plantilla->es_sueldo)
                                            <select name="usuario_staff_id" class="px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-emerald-500 focus:outline-none">
                                                <option value="">Sin persona</option>
                                                @foreach($staff as $persona)
                                                    <option value="{{ $persona->usuario_id }}" @selected($plantilla->usuario_staff_id == $persona->usuario_id)>{{ $persona->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-medium transition-colors">
                                            Guardar
                                        </button>
                                        <button type="button"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-colors"
                                            data-cargar="{{ $plantilla->id }}"
                                            data-monto="{{ $fmt($plantilla->monto) }}"
                                            data-staff="{{ $plantilla->usuario_staff_id }}"
                                            data-sueldo="{{ $plantilla->es_sueldo ? '1' : '0' }}">
                                            Usar en el pago
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
    </div>
</div>

    {{-- Historial de Egresos Contabilizados --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-700/30 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Últimos egresos registrados</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Los 30 pagos más recientes contabilizados con sus respaldos adjuntos</p>
            </div>
            <a href="{{ route('finanzas.libro', ['tipo' => 'egreso']) }}" class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline">
                Buscar o exportar todos en el Libro →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50/80 dark:bg-gray-700/50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Fecha</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Concepto / Detalle</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Categoría</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cuenta</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Adjuntos</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-36">Importe</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-28">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                    @forelse($filas as $fila)
                        @php
                            $modoArchivo = function (?string $path): ?string {
                                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                                if ($ext === 'pdf') {
                                    return 'pdf';
                                }
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                                    return 'imagen';
                                }

                                return null;
                            };
                            $archivos = [];
                            foreach (['documento' => 'Documento', 'factura' => 'Factura'] as $tipoArchivo => $etiquetaArchivo) {
                                $rutaArchivo = $fila->{$tipoArchivo.'_path'};
                                if (! $rutaArchivo) {
                                    continue;
                                }
                                $modo = $modoArchivo($rutaArchivo);
                                $archivos[] = [
                                    'etiqueta' => $etiquetaArchivo,
                                    'descargar' => route('finanzas.egresos.archivo', [$fila, $tipoArchivo]),
                                    'ver' => $modo ? route('finanzas.egresos.archivo', [$fila, $tipoArchivo, 'ver' => 1]) : null,
                                    'modo' => $modo,
                                ];
                            }
                            $nombreGasto = $fila->plantilla?->nombre ?: ($fila->movimiento?->concepto ?: $fila->categoria?->nombre);
                            $detalle = [
                                'titulo' => $nombreGasto ?? 'Gasto',
                                'campos' => array_values(array_filter([
                                    ['Fecha', $fila->fecha?->format('d/m/Y')],
                                    ['Concepto', $nombreGasto],
                                    ['Categoría', $fila->categoria ? strtoupper($fila->categoria->clase).' — '.$fila->categoria->nombre : null],
                                    ['Cuenta', $fila->cuenta?->nombre],
                                    ['Importe', $fmt($fila->importe).' Gs'],
                                    ['Staff', $fila->staff?->name],
                                    ['Proveedor', $fila->proveedor?->nombre],
                                    ['Comprobante', $fila->comprobante],
                                    ['Registró', $fila->usuario?->name],
                                    ['Observaciones', $fila->observaciones],
                                ], fn ($par) => filled($par[1]))),
                                'archivos' => $archivos,
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            {{-- Fecha --}}
                            <td class="px-4 py-3 text-xs font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                {{ $fila->fecha?->format('d/m/Y') }}
                            </td>

                            {{-- Concepto + Staff / Proveedor / Comprobante --}}
                            <td class="px-4 py-3 text-xs text-gray-900 dark:text-gray-100">
                                <div class="font-semibold">{{ $nombreGasto }}</div>
                                @if($fila->staff || $fila->proveedor || $fila->comprobante)
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                        @if($fila->staff)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 font-medium">
                                                {{ $fila->staff->name }}
                                            </span>
                                        @endif
                                        @if($fila->proveedor)
                                            <span>Prov: {{ $fila->proveedor->nombre }}</span>
                                        @endif
                                        @if($fila->comprobante)
                                            <span class="font-mono">Comp: {{ $fila->comprobante }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            {{-- Categoría --}}
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @if($fila->categoria)
                                    <span class="inline-flex items-center gap-1">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase {{ $fila->categoria->clase === 'capex' ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300' }}">
                                            {{ $fila->categoria->clase }}
                                        </span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ $fila->categoria->nombre }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Cuenta --}}
                            <td class="px-4 py-3 text-xs font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $fila->cuenta?->nombre ?? '—' }}
                            </td>

                            {{-- Adjuntos --}}
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                @if(count($archivos) > 0)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @foreach($archivos as $archivo)
                                            <a class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:border-emerald-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                                href="{{ $archivo['descargar'] }}"
                                                @if($archivo['ver']) data-ver="{{ $archivo['ver'] }}" data-modo="{{ $archivo['modo'] }}" data-titulo="{{ $archivo['etiqueta'] }}" @endif>
                                                <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                <span class="underline">{{ $archivo['etiqueta'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>

                            {{-- Importe --}}
                            <td class="px-4 py-3 text-sm text-right font-mono font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap">
                                {{ $fmt($fila->importe) }}
                            </td>

                            {{-- Ver Detalle --}}
                            <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
                                <button type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 transition-colors"
                                    data-detalle="{{ json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
                                    Ver detalle
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                Todavía no hay egresos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal de Detalle del Gasto --}}
<div id="detalle-gasto" class="hidden fixed inset-x-0 bottom-0 top-16 z-[70] overflow-y-auto bg-black/70 backdrop-blur-sm p-4 sm:p-6">
    <div class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3.5 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/60">
            <h2 id="detalle-titulo" class="text-base font-bold text-gray-900 dark:text-gray-100">Detalle del gasto</h2>
            <button type="button" id="detalle-cerrar" class="rounded-lg bg-gray-200 hover:bg-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 transition-colors">Cerrar</button>
        </div>
        <dl id="detalle-campos" class="grid gap-3.5 p-5 text-sm sm:grid-cols-2"></dl>
        <div id="detalle-archivos" class="space-y-4 px-5 pb-5"></div>
    </div>
</div>

{{-- Visor de Documento / Factura (PDF o Imagen) --}}
<div id="visor-factura" class="hidden fixed inset-x-0 bottom-0 top-16 z-[80] bg-black/75 backdrop-blur-sm p-4 sm:p-6">
    <div class="mx-auto flex h-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/60">
            <span id="visor-titulo" class="text-sm font-bold text-gray-900 dark:text-gray-100">Archivo</span>
            <div class="flex items-center gap-4 text-sm">
                <a id="visor-descargar" class="font-semibold text-emerald-600 dark:text-emerald-400 underline" href="#">Descargar</a>
                <button type="button" id="visor-cerrar" class="rounded-lg bg-gray-200 hover:bg-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 transition-colors">Cerrar</button>
            </div>
        </div>
        <iframe id="visor-frame" class="min-h-0 w-full flex-1 bg-gray-100" title="Archivo"></iframe>
        <img id="visor-imagen" alt="" class="hidden min-h-0 w-full flex-1 object-contain bg-gray-100 dark:bg-gray-950">
    </div>
</div>

<script>
(function () {
    const plantilla = document.getElementById('plantilla');
    const importe = document.getElementById('importe');
    const descripcionLibre = document.getElementById('descripcion-libre');
    const descripcion = document.getElementById('descripcion');
    const bloquePago = document.getElementById('bloque-pago');

    function mostrarDescripcion() {
        const libre = !plantilla.value;
        descripcionLibre.classList.toggle('hidden', !libre);
        descripcion.required = libre;
    }

    const staffPago = document.getElementById('staff-pago');
    const staff = document.getElementById('staff');

    function resaltarPlantillaActiva(id) {
        document.querySelectorAll('[data-fila-plantilla]').forEach(function (tr) {
            const activa = id && tr.dataset.filaPlantilla === String(id);
            tr.classList.toggle('bg-emerald-50/70', !!activa);
            tr.classList.toggle('dark:bg-emerald-950/30', !!activa);
        });
    }

    function aplicar(origen) {
        if (!origen) return;
        const sueldo = origen.dataset.sueldo === '1';
        if (origen.dataset.monto) importe.value = origen.dataset.monto;
        staff.value = origen.dataset.staff || '';
        staffPago.classList.toggle('hidden', !sueldo);
        staff.required = sueldo;
        mostrarDescripcion();
        resaltarPlantillaActiva(plantilla.value);
    }

    mostrarDescripcion();
    if (plantilla.value) {
        aplicar(plantilla.selectedOptions[0]);
    }

    plantilla.addEventListener('change', function () {
        aplicar(plantilla.selectedOptions[0]);
    });

    const modalHabituales = document.getElementById('modal-habituales');
    function cerrarHabituales() {
        modalHabituales.classList.add('hidden');
    }
    document.getElementById('abrir-habituales').addEventListener('click', function () {
        modalHabituales.classList.remove('hidden');
    });
    document.getElementById('cerrar-habituales').addEventListener('click', cerrarHabituales);
    modalHabituales.addEventListener('click', function (evento) {
        if (evento.target === modalHabituales) cerrarHabituales();
    });

    document.querySelectorAll('[data-cargar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            plantilla.value = boton.dataset.cargar;
            aplicar(boton);
            cerrarHabituales();
            bloquePago.scrollIntoView({ behavior: 'smooth', block: 'start' });
            importe.focus();
        });
    });

    const nuevoSueldo = document.getElementById('nuevo-sueldo');
    const nuevoStaff = document.getElementById('nuevo-staff');
    const nuevoStaffSelect = document.getElementById('nuevo-staff-select');
    nuevoSueldo.addEventListener('change', function () {
        nuevoStaff.classList.toggle('hidden', !nuevoSueldo.checked);
        nuevoStaffSelect.required = nuevoSueldo.checked;
    });

    const visor = document.getElementById('visor-factura');
    const visorFrame = document.getElementById('visor-frame');
    const visorImagen = document.getElementById('visor-imagen');
    const visorTitulo = document.getElementById('visor-titulo');
    const visorDescargar = document.getElementById('visor-descargar');
    const detalle = document.getElementById('detalle-gasto');
    const detalleTitulo = document.getElementById('detalle-titulo');
    const detalleCampos = document.getElementById('detalle-campos');
    const detalleArchivos = document.getElementById('detalle-archivos');

    function cerrarVisor() {
        visor.classList.add('hidden');
        visorFrame.src = 'about:blank';
        visorImagen.removeAttribute('src');
    }

    function abrirVisor(urlVer, urlDescarga, modo, titulo) {
        visorTitulo.textContent = titulo || 'Archivo';
        visorDescargar.href = urlDescarga;
        const esImagen = modo === 'imagen';
        visorFrame.classList.toggle('hidden', esImagen);
        visorImagen.classList.toggle('hidden', !esImagen);
        if (esImagen) {
            visorFrame.src = 'about:blank';
            visorImagen.src = urlVer;
        } else {
            visorImagen.removeAttribute('src');
            visorFrame.src = urlVer;
        }
        visor.classList.remove('hidden');
    }

    document.querySelectorAll('[data-ver]').forEach(function (enlace) {
        enlace.addEventListener('click', function (evento) {
            evento.preventDefault();
            abrirVisor(enlace.dataset.ver, enlace.href, enlace.dataset.modo, enlace.dataset.titulo);
        });
    });

    function abrirDetalle(datos) {
        detalleTitulo.textContent = datos.titulo || 'Detalle del gasto';
        detalleCampos.replaceChildren();
        (datos.campos || []).forEach(function (par) {
            const bloque = document.createElement('div');
            bloque.className = 'rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-800/40 px-3 py-2';
            if (par[0] === 'Observaciones') bloque.className += ' sm:col-span-2';
            const etiqueta = document.createElement('dt');
            etiqueta.className = 'text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400';
            etiqueta.textContent = par[0];
            const valor = document.createElement('dd');
            valor.className = 'mt-0.5 font-medium whitespace-pre-wrap text-gray-900 dark:text-gray-100';
            valor.textContent = par[1];
            bloque.append(etiqueta, valor);
            detalleCampos.append(bloque);
        });
        detalleArchivos.replaceChildren();
        (datos.archivos || []).forEach(function (archivo) {
            const bloque = document.createElement('div');
            const cabeza = document.createElement('div');
            cabeza.className = 'mb-2 flex items-center justify-between text-sm';
            const nombre = document.createElement('span');
            nombre.className = 'font-semibold text-gray-900 dark:text-gray-100';
            nombre.textContent = archivo.etiqueta;
            const descarga = document.createElement('a');
            descarga.className = 'font-semibold text-emerald-600 dark:text-emerald-400 underline';
            descarga.href = archivo.descargar;
            descarga.textContent = 'Descargar';
            cabeza.append(nombre, descarga);
            bloque.append(cabeza);
            if (archivo.ver && archivo.modo === 'imagen') {
                const img = document.createElement('img');
                img.src = archivo.ver;
                img.alt = archivo.etiqueta;
                img.className = 'max-h-[28rem] w-full rounded-lg border border-gray-200 object-contain bg-gray-100 dark:border-gray-700 dark:bg-gray-800';
                bloque.append(img);
            } else if (archivo.ver) {
                const frame = document.createElement('iframe');
                frame.src = archivo.ver;
                frame.title = archivo.etiqueta;
                frame.className = 'h-[28rem] w-full rounded-lg border border-gray-200 bg-gray-100';
                bloque.append(frame);
            }
            detalleArchivos.append(bloque);
        });
        detalle.classList.remove('hidden');
    }

    document.querySelectorAll('[data-detalle]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            abrirDetalle(JSON.parse(boton.dataset.detalle));
        });
    });

    function cerrarDetalle() {
        detalle.classList.add('hidden');
        detalleArchivos.replaceChildren();
    }

    document.getElementById('detalle-cerrar').addEventListener('click', cerrarDetalle);
    detalle.addEventListener('click', function (evento) {
        if (evento.target === detalle) cerrarDetalle();
    });
    document.getElementById('visor-cerrar').addEventListener('click', cerrarVisor);
    visor.addEventListener('click', function (evento) {
        if (evento.target === visor) cerrarVisor();
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') return;
        if (!visor.classList.contains('hidden')) cerrarVisor();
        else if (!detalle.classList.contains('hidden')) cerrarDetalle();
        else if (!modalHabituales.classList.contains('hidden')) cerrarHabituales();
    });
})();
</script>
@endsection
