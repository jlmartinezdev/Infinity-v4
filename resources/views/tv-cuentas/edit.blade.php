@extends('layouts.app')

@section('title', 'Cuenta TV · ' . ($tv_cuenta->nombre ?: $tv_cuenta->usuario_app))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Navegación superior y Encabezado Ejecutivo --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('tv-cuentas.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Volver al listado de Cuentas TV
            </a>
            <div class="flex flex-wrap items-center gap-2.5 mt-2">
                <h1 class="text-2xl font-black text-gray-900 dark:text-gray-100 tracking-tight">
                    {{ $tv_cuenta->nombre ?: $tv_cuenta->usuario_app }}
                </h1>
                {{-- Badge de la Aplicación --}}
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold
                    {{ $tv_cuenta->esLumix() ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300 border border-sky-200 dark:border-sky-800' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    {{ $tv_cuenta->esLumix() ? 'Lumix TV (4 Pantallas)' : 'Nebula TV (3 Perfiles)' }}
                </span>
                {{-- Badge Estado Vencimiento --}}
                @php
                    $estadoVenc = $tv_cuenta->estadoVencimiento();
                @endphp
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold
                    @if($estadoVenc === 'vencido') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800
                    @elseif($estadoVenc === 'por_vencer') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800
                    @else bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 @endif">
                    <span class="w-1.5 h-1.5 rounded-full {{ $estadoVenc === 'vencido' ? 'bg-rose-500' : ($estadoVenc === 'por_vencer' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                    {{ $tv_cuenta->etiquetaEstadoVencimiento() }}
                </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Vence el <strong class="text-gray-700 dark:text-gray-300">{{ $tv_cuenta->fechaVencimientoReferencia()->format('d/m/Y') }}</strong>
                · Ciclo mensual: día {{ $tv_cuenta->diaVencimientoMensual() }} de cada mes
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(auth()->user()?->tienePermiso('tv.editar'))
                <form action="{{ route('tv-cuentas.renovar', $tv_cuenta) }}" method="POST"
                      onsubmit="return confirm('¿Renovar esta cuenta por 1 mes adelante?');" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-all"
                            title="Extender vencimiento mensual por 1 mes">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Renovar +1 mes
                    </button>
                </form>
            @endif

            <button type="button" id="btn-toggle-config"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-all">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span id="btn-toggle-config-text">Configurar cuenta</span>
            </button>
        </div>
    </div>

    {{-- Notificaciones de sesión --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800 flex items-center gap-3 text-sm">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800 flex items-center gap-3 text-sm">
            <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- PAQUETE A: Banner de Credenciales Rápidas & Estado de Ocupación --}}
    @php
        $maxSlots = $tv_cuenta->maxAsignaciones();
        $ocupadas = $tv_cuenta->asignaciones->count();
        $libres = max(0, $maxSlots - $ocupadas);
        $pctOcupacion = round(($ocupadas / max(1, $maxSlots)) * 100);
        $textoPlantilla = "📺 *DATOS DE ACCESO APP TV (" . strtoupper($tv_cuenta->aplicacion) . ")*\n"
            . "📲 *Aplicación:* " . ucfirst($tv_cuenta->aplicacion) . "\n"
            . "👤 *Usuario:* " . $tv_cuenta->usuario_app . "\n"
            . "🔑 *Contraseña:* " . $tv_cuenta->password . "\n\n"
            . "_Ingresá con estos datos y elegí tu pantalla asignada._";
    @endphp

    <div class="bg-gradient-to-r from-purple-50 via-white to-sky-50 dark:from-gray-800 dark:via-gray-800 dark:to-gray-800/80 rounded-2xl border border-purple-200/80 dark:border-gray-700 p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
            {{-- Usuario App --}}
            <div class="bg-white/80 dark:bg-gray-700/60 p-3.5 rounded-xl border border-gray-200/60 dark:border-gray-600/60 shadow-2xs">
                <span class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Usuario de la App</span>
                <div class="flex items-center justify-between gap-2 mt-1">
                    <span class="font-mono text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-100 truncate" id="txt-usuario-app">{{ $tv_cuenta->usuario_app }}</span>
                    <button type="button" class="js-copiar-dato p-1.5 text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                            data-texto="{{ $tv_cuenta->usuario_app }}" title="Copiar usuario">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>
            </div>

            {{-- Contraseña App --}}
            <div class="bg-white/80 dark:bg-gray-700/60 p-3.5 rounded-xl border border-gray-200/60 dark:border-gray-600/60 shadow-2xs">
                <span class="block text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contraseña</span>
                <div class="flex items-center justify-between gap-2 mt-1">
                    <span class="font-mono text-xs sm:text-sm font-bold text-gray-900 dark:text-gray-100 select-all" id="txt-password-app" data-password="{{ $tv_cuenta->password }}">••••••••</span>
                    <div class="flex items-center gap-1">
                        <button type="button" id="btn-toggle-password" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors" title="Ver / Ocultar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                        <button type="button" class="js-copiar-dato p-1.5 text-gray-400 hover:text-purple-600 dark:hover:text-purple-400 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                data-texto="{{ $tv_cuenta->password }}" title="Copiar contraseña">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Ocupación de Pantallas / Slots --}}
            <div class="bg-white/80 dark:bg-gray-700/60 p-3.5 rounded-xl border border-gray-200/60 dark:border-gray-600/60 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Capacidad</span>
                    <span class="text-xs font-black {{ $libres === 0 ? 'text-rose-600' : 'text-purple-700 dark:text-purple-300' }}">
                        {{ $ocupadas }} / {{ $maxSlots }}
                    </span>
                </div>
                <div class="w-full h-2 rounded-full bg-gray-200 dark:bg-gray-600 overflow-hidden mt-2">
                    <div style="width: {{ $pctOcupacion }}%;"
                         class="h-full rounded-full transition-all duration-500 {{ $libres === 0 ? 'bg-rose-500' : 'bg-purple-600' }}"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 mt-1.5">
                    <span>{{ $libres > 0 ? $libres . ' libre(s)' : 'Capacidad máxima' }}</span>
                    <span>{{ $pctOcupacion }}%</span>
                </div>
            </div>

            {{-- Botón rápido: Copiar plantilla WhatsApp --}}
            <div class="flex flex-col justify-center">
                <button type="button" class="js-copiar-dato w-full flex items-center justify-center gap-2 px-3.5 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition-all"
                        data-texto="{{ $textoPlantilla }}" title="Copiar plantilla completa para WhatsApp">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                    <span>Copiar mensaje para WhatsApp</span>
                </button>
            </div>
        </div>
    </div>

    {{-- PAQUETE A: HERO SECTION - Rack Visual de Pantallas / Slots --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <span>📺</span>
                    <span>{{ $tv_cuenta->esLumix() ? 'Pantallas Asignadas' : 'Perfiles Asignados' }}</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                        {{ $ocupadas }} de {{ $maxSlots }} ocupadas
                    </span>
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Mapa en tiempo real de cada pantalla. Hacé clic en un slot disponible para asignar un cliente.
                </p>
            </div>
        </div>

        {{-- Grid de Slots --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-{{ $maxSlots }} gap-4">
            @for($slot = 1; $slot <= $maxSlots; $slot++)
                @php
                    $asig = $tv_cuenta->asignaciones->firstWhere('perfil_numero', $slot);
                    $slotNombre = $tv_cuenta->nombreSlot($slot);
                    $slotPrecio = $tv_cuenta->precioSlot($slot);
                @endphp

                @if($asig)
                    {{-- SLOT OCUPADO --}}
                    @php
                        $cliente = $asig->servicio?->cliente;
                        $servicio = $asig->servicio;
                        $plan = $servicio?->plan;

                        // Construcción de link WhatsApp
                        $telWa = null;
                        if ($cliente?->telefono) {
                            $norm = \App\Helpers\TelefonoParaguayHelper::normalize($cliente->telefono);
                            if ($norm && str_starts_with($norm, '09') && strlen($norm) === 10) {
                                $telWa = '595' . substr($norm, 1);
                            } elseif ($norm && str_starts_with($norm, '595')) {
                                $telWa = $norm;
                            }
                        }
                        $msgWa = "📺 *DATOS DE ACCESO APP TV (" . strtoupper($tv_cuenta->aplicacion) . ")*\n"
                               . "Hola " . trim($cliente?->nombre ?? 'Cliente') . ", te enviamos los accesos para ingresar a la App TV:\n\n"
                                . "📲 *Aplicación:* " . ucfirst($tv_cuenta->aplicacion) . "\n"
                                . "👤 *Usuario:* " . $tv_cuenta->usuario_app . "\n"
                                . "🔑 *Contraseña:* " . $tv_cuenta->password . "\n"
                                . "📺 *Tu Pantalla:* " . $slotNombre . "\n\n"
                                . "_¡Que disfrutes del mejor entretenimiento!_";
                        $urlWa = $telWa ? 'https://wa.me/' . $telWa . '?text=' . rawurlencode($msgWa) : null;
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border-2 border-purple-200 dark:border-purple-800/60 shadow-sm p-4 flex flex-col justify-between hover:shadow-md transition-all relative overflow-hidden group">
                        {{-- Indicador lateral de color --}}
                        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 to-indigo-500"></div>

                        <div>
                            {{-- Header del Slot --}}
                            <div class="flex items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-700/60 pb-2.5">
                                <div class="flex items-center gap-1.5 font-black text-sm text-gray-900 dark:text-gray-100">
                                    <span class="text-base">📺</span>
                                    <span>{{ $slotNombre }}</span>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Ocupada
                                </span>
                            </div>

                            {{-- Datos del Cliente --}}
                            <div class="mt-3 space-y-1.5">
                                <div>
                                    <a href="{{ $cliente ? route('clientes.edit', $cliente) : '#' }}"
                                       class="font-bold text-sm text-purple-700 dark:text-purple-400 hover:underline line-clamp-1"
                                       title="{{ $cliente?->nombre }} {{ $cliente?->apellido }}">
                                        {{ $cliente?->nombre }} {{ $cliente?->apellido }}
                                    </a>
                                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                        CI: {{ $cliente?->cedula ?? 'Sin doc' }}
                                    </p>
                                </div>

                                {{-- Servicio y Plan --}}
                                <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                    @if($plan)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300"
                                              title="{{ $plan->nombre }}">{{ $plan->iniciales() }}</span>
                                    @endif
                                    <span class="text-gray-500 font-mono text-[11px]">Servicio #{{ $asig->servicio_id }}</span>
                                </div>

                                {{-- Badges de Estado y Condiciones --}}
                                <div class="flex flex-wrap gap-1 pt-1">
                                    @if($asig->es_promo)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            🏷️ Promo (sin costo)
                                        </span>
                                    @elseif((float) $asig->precio_aplicado > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                            💰 Gs. {{ number_format((float) $asig->precio_aplicado, 0, ',', '.') }}/mes
                                        </span>
                                    @endif

                                    @if($asig->tvbox_comodato)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
                                            📦 TV Box comodato
                                        </span>
                                    @endif
                                </div>

                                {{-- Fecha de Activación --}}
                                @if($asig->fecha_activacion)
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500 pt-1">
                                        Activado: {{ $asig->fecha_activacion->format('d/m/Y') }}
                                        <span class="text-gray-400">({{ $asig->fecha_activacion->diffForHumans() }})</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Footer de Acciones del Slot --}}
                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-1">
                            <div class="flex items-center gap-1">
                                {{-- Enviar WhatsApp --}}
                                @if($urlWa)
                                    <a href="{{ $urlWa }}" target="_blank"
                                       class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                                       title="Enviar datos de acceso por WhatsApp">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 0 0 .917.917l4.458-1.495A11.953 11.953 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.387 0-4.584-.832-6.314-2.222l-.447-.372-2.627.882.882-2.627-.372-.447A9.96 9.96 0 0 1 2 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
                                    </a>
                                @endif

                                {{-- Historial de Pagos --}}
                                <button type="button"
                                        data-tv-historial-pago="{{ route('tv-cuentas.asignaciones.historial-pago', [$tv_cuenta, $asig]) }}"
                                        data-tv-historial-titulo="Pagos — {{ trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? '')) }}"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors"
                                        title="Ver historial de cobros TV">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <span>Pagos</span>
                                </button>
                            </div>

                            {{-- Desvincular Pantalla --}}
                            @if(auth()->user()?->tienePermiso('tv.editar'))
                                <form action="{{ route('tv-cuentas.asignaciones.destroy', [$tv_cuenta, $asig]) }}" method="POST"
                                      onsubmit="return confirm('¿Quitar a {{ $cliente?->nombre }} de la {{ $slotNombre }}?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors text-xs font-semibold"
                                            title="Desvincular esta pantalla">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @else
                    {{-- SLOT LIBRE / DISPONIBLE --}}
                    <div class="js-slot-libre rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-purple-500 dark:hover:border-purple-400 bg-gray-50/50 dark:bg-gray-800/40 p-4 flex flex-col justify-between items-center text-center cursor-pointer group transition-all min-h-[220px]"
                         data-slot="{{ $slot }}" title="Hacer clic para asignar a esta pantalla">
                        <div class="w-full flex items-center justify-between border-b border-gray-200/50 dark:border-gray-700/50 pb-2">
                            <span class="font-bold text-xs text-gray-500 dark:text-gray-400">📺 {{ $slotNombre }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                Libre
                            </span>
                        </div>

                        <div class="my-auto py-4">
                            <div class="w-12 h-12 rounded-full bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center mx-auto group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white transition-all shadow-2xs">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <p class="font-bold text-sm text-gray-800 dark:text-gray-200 mt-2">Disponible</p>
                            @if($slotPrecio !== null && $slotPrecio > 0)
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Catálogo: Gs. {{ number_format((float) $slotPrecio, 0, ',', '.') }}</p>
                            @endif
                        </div>

                        <button type="button" class="w-full py-1.5 px-3 rounded-xl bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-xs font-bold text-purple-600 dark:text-purple-300 group-hover:bg-purple-600 group-hover:text-white transition-colors">
                            Asignar cliente aquí
                        </button>
                    </div>
                @endif
            @endfor
        </div>
    </div>

    {{-- PAQUETE B: Formulario de Asignación Guiado y Asistido --}}
    @if($libres > 0 && auth()->user()?->tienePermiso('tv.editar'))
        @php
            $clientePrefill = $clientePrefill ?? null;
            $clientePrefillId = (int) ($clientePrefill?->cliente_id ?? 0);
            $clientePrefillLabel = $clientePrefill
                ? trim($clientePrefill->nombre.' '.$clientePrefill->apellido).($clientePrefill->cedula ? ' ('.$clientePrefill->cedula.')' : '')
                : '';
            $estadosServicio = $estadosServicio ?? \App\Models\Servicio::estadosDisponibles();
            $perfilesEnUso = $tv_cuenta->asignaciones->pluck('perfil_numero')->filter()->map(fn($p) => (int) $p)->all();
        @endphp

        <div id="seccion-asignar" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-purple-200/80 dark:border-gray-700 p-6 space-y-5 scroll-mt-20">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </span>
                        Asignar nueva pantalla a un cliente
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Seleccioná el cliente y servicio que utilizarán esta cuenta de TV.
                    </p>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                    {{ $libres }} pantalla(s) libre(s)
                </span>
            </div>

            <form action="{{ route('tv-cuentas.asignaciones.store', $tv_cuenta) }}" method="POST" id="form-asignar-tv" class="space-y-4">
                @csrf

                {{-- Paso 1: Búsqueda del Cliente y Selector de Servicio --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Buscador de Cliente --}}
                    <div id="tv-cliente-buscar-wrap" class="relative" data-url="{{ route('clientes.buscar') }}">
                        <label for="tv_cliente_q" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            1. Buscar Cliente *
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                            </span>
                            <input type="text" id="tv_cliente_q" value="{{ $clientePrefillLabel }}" maxlength="80" autocomplete="off"
                                   placeholder="Escribí nombre, apellido o cédula…"
                                   class="w-full pl-9 pr-9 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all"
                                   aria-autocomplete="list" aria-controls="tv_cliente_resultados" aria-expanded="false"
                                   @if($clientePrefillId) readonly @endif>

                            <input type="hidden" name="cliente_id" id="cliente_id" value="{{ $clientePrefillId ?: '' }}" required>

                            <button type="button" id="tv_cliente_limpiar" title="Quitar cliente seleccionado" aria-label="Quitar cliente"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 {{ $clientePrefillId ? '' : 'hidden' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>

                            <div id="tv_cliente_resultados" role="listbox"
                                 class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl py-1 hidden"></div>
                        </div>
                        @error('cliente_id')<p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>@enderror
                    </div>

                    {{-- Servicio del Cliente --}}
                    <div id="tv-servicio-campo">
                        <label for="servicio_id" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            2. Servicio de Internet *
                        </label>

                        <div class="relative">
                            <select name="servicio_id" id="servicio_id" class="hidden w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                                <option value="">Seleccionar servicio…</option>
                                @foreach($servicios as $srv)
                                    <option value="{{ $srv->servicio_id }}"
                                            data-cliente-id="{{ $srv->cliente_id }}"
                                            data-plan="{{ $srv->plan?->nombre ?? '' }}"
                                            @selected((int) old('servicio_id') === (int) $srv->servicio_id)>
                                        Servicio #{{ $srv->servicio_id }}{{ $srv->plan?->nombre ? ' · '.$srv->plan->nombre : '' }} · {{ $estadosServicio[$srv->estado] ?? $srv->estado }}
                                    </option>
                                @endforeach
                            </select>

                            {{-- Mensaje de servicio único auto-seleccionado --}}
                            <div id="tv-servicio-unico" class="hidden p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 flex items-center gap-2 text-xs font-bold text-emerald-800 dark:text-emerald-200">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span id="tv-servicio-unico-texto">Servicio único asignado automáticamente.</span>
                            </div>

                            {{-- Mensaje si el cliente no tiene servicios --}}
                            <div id="tv-servicio-vacio" class="hidden p-2.5 rounded-xl bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-xs font-medium text-amber-800 dark:text-amber-200">
                                ⚠️ Este cliente no tiene servicios activos o suspendidos.
                            </div>

                            {{-- Mensaje de ayuda inicial --}}
                            <div id="tv-servicio-ayuda" class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400">
                                Buscá un cliente a la izquierda. Si tiene un solo servicio, se seleccionará automáticamente.
                            </div>
                        </div>
                        @error('servicio_id')<p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Paso 2: Selección de Pantalla y Fecha de Activación --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="perfil_numero" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            3. {{ $tv_cuenta->etiquetaTipoSlot() }} a Asignar *
                        </label>
                        <select name="perfil_numero" id="perfil_numero" required
                                class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-semibold focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                            <option value="">Seleccionar pantalla libre…</option>
                            @for($i = 1; $i <= $maxSlots; $i++)
                                @if(!in_array($i, $perfilesEnUso, true))
                                    @php $precio = $tv_cuenta->precioSlot($i); @endphp
                                    <option value="{{ $i }}" data-precio="{{ $precio !== null ? (float) $precio : 0 }}"
                                            @selected((int) old('perfil_numero', request('perfil')) === $i)>
                                        📺 {{ $tv_cuenta->nombreSlot($i) }}{{ $precio !== null && $precio > 0 ? ' (Catálogo: Gs. ' . number_format((float) $precio, 0, ',', '.') . ')' : '' }}
                                    </option>
                                @endif
                            @endfor
                        </select>
                        @error('perfil_numero')<p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="fecha_activacion" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                            4. Fecha de Activación *
                        </label>
                        <input type="date" name="fecha_activacion" id="fecha_activacion" required
                               value="{{ old('fecha_activacion', now()->format('Y-m-d')) }}"
                               class="w-full px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                        @error('fecha_activacion')<p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Paso 3: Condiciones Comerciales e Indicador de Precio --}}
                <div class="p-4 rounded-xl bg-gray-50/70 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-600 space-y-3">
                    <span class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        5. Condiciones de la Pantalla
                    </span>

                    <div class="flex flex-wrap items-center gap-6">
                        <label class="inline-flex items-center gap-2.5 text-xs font-semibold text-gray-800 dark:text-gray-200 cursor-pointer">
                            <input type="checkbox" name="es_promo" id="es_promo" value="1" @checked(old('es_promo'))
                                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                            <span>🏷️ Promo (no cargar costo en la factura del servicio)</span>
                        </label>

                        <label class="inline-flex items-center gap-2.5 text-xs font-semibold text-gray-800 dark:text-gray-200 cursor-pointer">
                            <input type="checkbox" name="tvbox_comodato" id="tvbox_comodato" value="1" @checked(old('tvbox_comodato'))
                                   class="w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                            <span>📦 Se entrega TV Box en comodato</span>
                        </label>
                    </div>

                    {{-- Banner de impacto en la factura --}}
                    <div id="tv-aviso-precio" class="p-2.5 rounded-lg bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800 text-xs text-purple-800 dark:text-purple-200 flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span id="tv-aviso-precio-texto">Se sincronizará el precio en la tabla de servicios del cliente.</span>
                    </div>
                </div>

                {{-- Botón Enviar --}}
                <div class="flex justify-end pt-2">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Asignar Pantalla a Cliente</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- PAQUETE C: Sección Colapsable de Configuración de Cuenta y Precios --}}
    @php
        $tieneErroresConfig = isset($errors) && $errors->hasAny([
            'aplicacion', 'nombre', 'usuario_app', 'password', 'dia_aviso_vencimiento',
            'perfil_1', 'perfil_2', 'perfil_3', 'precio_perfil_1', 'precio_perfil_2', 'precio_perfil_3',
            'precio_pantalla_1', 'precio_pantalla_2', 'precio_pantalla_3', 'precio_pantalla_4', 'notas'
        ]);
    @endphp

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <button type="button" id="btn-toggle-config-accordion"
                class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
            <div class="flex items-center gap-3">
                <span class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        Configuración de la Cuenta TV & Precios de Catálogo
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Modificar credenciales de la app, precios por pantalla/perfil y día de corte mensual.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-purple-600 dark:text-purple-400" id="accordion-label">
                    {{ $tieneErroresConfig ? 'Ocultar' : 'Editar datos' }}
                </span>
                <svg id="accordion-arrow" class="w-4 h-4 text-gray-400 transform transition-transform {{ $tieneErroresConfig ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </button>

        {{-- Formulario de Configuración --}}
        <div id="seccion-config-body" class="{{ $tieneErroresConfig ? '' : 'hidden' }} px-6 pb-6 pt-2 border-t border-gray-100 dark:border-gray-700">
            <form action="{{ route('tv-cuentas.update', $tv_cuenta) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="aplicacion" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Aplicación *</label>
                        <select name="aplicacion" id="aplicacion" required
                                class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                            @foreach($aplicaciones as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('aplicacion', $tv_cuenta->aplicacion ?? \App\Models\TvCuenta::APP_NEBULA) === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        @error('aplicacion')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="nombre" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Nombre Interno (opcional)</label>
                        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $tv_cuenta->nombre) }}" maxlength="120"
                               placeholder="Ej: Cuenta Lumix #05"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        @error('nombre')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="usuario_app" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Usuario de la App *</label>
                        <input type="text" name="usuario_app" id="usuario_app" value="{{ old('usuario_app', $tv_cuenta->usuario_app) }}" required maxlength="255"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono" autocomplete="off">
                        @error('usuario_app')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Contraseña de la App *</label>
                        <input type="text" name="password" id="password" value="{{ old('password', $tv_cuenta->password) }}" required
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm font-mono" autocomplete="new-password">
                        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="dia_aviso_vencimiento" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Día de aviso de vencimiento mensual *</label>
                        <input type="number" name="dia_aviso_vencimiento" id="dia_aviso_vencimiento" value="{{ old('dia_aviso_vencimiento', $tv_cuenta->dia_aviso_vencimiento ?? $tv_cuenta->vencimiento_pago?->day) }}" min="1" max="31" required
                               class="w-full sm:w-48 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <span class="text-xs text-gray-500 ml-2">Día del mes (1 al 31) en que se renovará el ciclo.</span>
                        @error('dia_aviso_vencimiento')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Precios Nebula --}}
                <div id="bloque-nebula" class="space-y-2">
                    <p class="text-xs font-bold uppercase text-gray-500 tracking-wider">Perfiles y Precios Nebula (3 Perfiles)</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach([1, 2, 3] as $i)
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                                <label for="perfil_{{ $i }}" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Nombre Perfil {{ $i }}</label>
                                <input type="text" name="perfil_{{ $i }}" id="perfil_{{ $i }}" value="{{ old('perfil_'.$i, $tv_cuenta->{'perfil_'.$i} ?: 'Perfil '.$i) }}" maxlength="120"
                                       class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs campo-nebula">

                                <label for="precio_perfil_{{ $i }}" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-2 mb-1">Precio Gs.</label>
                                <input type="number" name="precio_perfil_{{ $i }}" id="precio_perfil_{{ $i }}" value="{{ old('precio_perfil_'.$i, $tv_cuenta->{'precio_perfil_'.$i}) }}" min="0" step="0.01"
                                       class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs">
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Precios Lumix --}}
                <div id="bloque-lumix" class="hidden space-y-2">
                    <p class="text-xs font-bold uppercase text-gray-500 tracking-wider">Precios de Catálogo Lumix (4 Pantallas)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        @foreach([1, 2, 3, 4] as $i)
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                                <label for="precio_pantalla_{{ $i }}" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Precio Pantalla {{ $i }}</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs text-gray-400">Gs.</span>
                                    <input type="number" name="precio_pantalla_{{ $i }}" id="precio_pantalla_{{ $i }}" value="{{ old('precio_pantalla_'.$i, $tv_cuenta->{'precio_pantalla_'.$i}) }}" min="0" step="0.01"
                                           class="w-full pl-9 pr-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Notas --}}
                <div>
                    <label for="notas" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Notas Internas</label>
                    <textarea name="notas" id="notas" rows="2" maxlength="2000" placeholder="Observaciones adicionales sobre esta cuenta..."
                              class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-xs">{{ old('notas', $tv_cuenta->notas) }}</textarea>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                    <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-xl font-bold text-xs hover:bg-purple-700 transition-colors shadow-sm">
                        Guardar Cambios de Cuenta
                    </button>

                    @if(auth()->user()?->tienePermiso('tv.editar'))
                        <button type="button" onclick="if(confirm('¿Eliminar esta cuenta TV y todas sus asignaciones?')) document.getElementById('form-destroy-cuenta').submit();"
                                class="px-3.5 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-xl text-xs font-semibold border border-rose-200 dark:border-rose-800 transition-colors">
                            Eliminar Cuenta TV
                        </button>
                    @endif
                </div>
            </form>

            @if(auth()->user()?->tienePermiso('tv.editar'))
                <form id="form-destroy-cuenta" action="{{ route('tv-cuentas.destroy', $tv_cuenta) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ----------------------------------------------------
    // 1. Alternancia de Aplicación (Nebula vs Lumix)
    // ----------------------------------------------------
    const selectApp = document.getElementById('aplicacion');
    const bloqueNebula = document.getElementById('bloque-nebula');
    const bloqueLumix = document.getElementById('bloque-lumix');
    const camposNebula = document.querySelectorAll('.campo-nebula');

    if (selectApp && bloqueNebula && bloqueLumix) {
        const actualizarApp = () => {
            const esLumix = selectApp.value === '{{ \App\Models\TvCuenta::APP_LUMIX }}';
            bloqueNebula.classList.toggle('hidden', esLumix);
            bloqueLumix.classList.toggle('hidden', !esLumix);
            camposNebula.forEach((input) => {
                input.required = !esLumix;
            });
        };
        selectApp.addEventListener('change', actualizarApp);
        actualizarApp();
    }

    // ----------------------------------------------------
    // 2. Acordeón de Configuración
    // ----------------------------------------------------
    const btnToggleConfig = document.getElementById('btn-toggle-config');
    const btnAccordion = document.getElementById('btn-toggle-config-accordion');
    const configBody = document.getElementById('seccion-config-body');
    const accordionArrow = document.getElementById('accordion-arrow');
    const accordionLabel = document.getElementById('accordion-label');

    function toggleConfig() {
        if (!configBody) return;
        const abierto = !configBody.classList.contains('hidden');
        configBody.classList.toggle('hidden', abierto);
        if (accordionArrow) accordionArrow.classList.toggle('rotate-180', !abierto);
        if (accordionLabel) accordionLabel.textContent = !abierto ? 'Ocultar' : 'Editar datos';
        if (!abierto) {
            configBody.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    if (btnToggleConfig) btnToggleConfig.addEventListener('click', toggleConfig);
    if (btnAccordion) btnAccordion.addEventListener('click', toggleConfig);

    // ----------------------------------------------------
    // 3. Ver / Ocultar Contraseña y Copiado al Portapapeles
    // ----------------------------------------------------
    const txtPass = document.getElementById('txt-password-app');
    const btnTogglePass = document.getElementById('btn-toggle-password');
    let passVisible = false;

    if (txtPass && btnTogglePass) {
        const realPass = txtPass.getAttribute('data-password') || '';
        btnTogglePass.addEventListener('click', function () {
            passVisible = !passVisible;
            txtPass.textContent = passVisible ? realPass : '••••••••';
        });
    }

    function toast(msg) {
        const t = document.createElement('div');
        t.className = 'fixed bottom-5 right-5 z-50 px-4 py-2 rounded-xl bg-purple-700 text-white text-xs font-bold shadow-xl flex items-center gap-2 transition-all transform translate-y-0 opacity-100';
        t.innerHTML = '<svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
                      '<span>' + (msg || 'Copiado al portapapeles') + '</span>';
        document.body.appendChild(t);
        setTimeout(() => {
            t.style.opacity = '0';
            t.style.transform = 'translateY(10px)';
            setTimeout(() => t.remove(), 300);
        }, 2200);
    }

    document.querySelectorAll('.js-copiar-dato').forEach(btn => {
        btn.addEventListener('click', function () {
            const texto = this.getAttribute('data-texto');
            if (!texto) return;
            navigator.clipboard.writeText(texto).then(() => {
                toast('¡Copiado al portapapeles!');
            }).catch(() => {
                prompt('Copiar texto:', texto);
            });
        });
    });

    // ----------------------------------------------------
    // 4. Pre-selección desde el Rack de Slots Disponibles
    // ----------------------------------------------------
    const selectPerfil = document.getElementById('perfil_numero');
    const inputCliente = document.getElementById('tv_cliente_q');
    const seccionAsignar = document.getElementById('seccion-asignar');

    document.querySelectorAll('.js-slot-libre').forEach(card => {
        card.addEventListener('click', function () {
            const slotNum = this.getAttribute('data-slot');
            if (selectPerfil && slotNum) {
                selectPerfil.value = slotNum;
                actualizarAvisoPrecio();
            }
            if (seccionAsignar) {
                seccionAsignar.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (inputCliente && !inputCliente.readOnly) {
                setTimeout(() => inputCliente.focus(), 300);
            }
        });
    });

    // ----------------------------------------------------
    // 5. Búsqueda de Cliente y Asignación de Servicio
    // ----------------------------------------------------
    const wrap = document.getElementById('tv-cliente-buscar-wrap');
    const clienteIdInput = document.getElementById('cliente_id');
    const servicioSelect = document.getElementById('servicio_id');
    const resultados = document.getElementById('tv_cliente_resultados');
    const btnLimpiar = document.getElementById('tv_cliente_limpiar');
    const msgUnico = document.getElementById('tv-servicio-unico');
    const msgUnicoTexto = document.getElementById('tv-servicio-unico-texto');
    const msgVacio = document.getElementById('tv-servicio-vacio');
    const msgAyuda = document.getElementById('tv-servicio-ayuda');
    const avisoPrecioTexto = document.getElementById('tv-aviso-precio-texto');
    const chkPromo = document.getElementById('es_promo');

    function actualizarAvisoPrecio() {
        if (!avisoPrecioTexto || !selectPerfil) return;
        const opt = selectPerfil.options[selectPerfil.selectedIndex];
        const precio = opt ? parseFloat(opt.getAttribute('data-precio') || 0) : 0;
        const esPromo = chkPromo ? chkPromo.checked : false;

        if (esPromo) {
            avisoPrecioTexto.textContent = '🏷️ Promoción activa: no se sumará ningún cargo a la factura mensual del cliente.';
        } else if (precio > 0) {
            avisoPrecioTexto.textContent = '💰 Se facturarán Gs. ' + precio.toLocaleString('es-PY') + ' / mes adicionales en el servicio seleccionado.';
        } else {
            avisoPrecioTexto.textContent = 'ℹ️ Sin precio de catálogo configurado para este slot.';
        }
    }

    if (selectPerfil) selectPerfil.addEventListener('change', actualizarAvisoPrecio);
    if (chkPromo) chkPromo.addEventListener('change', actualizarAvisoPrecio);
    actualizarAvisoPrecio();

    if (!wrap || !inputCliente || !clienteIdInput || !servicioSelect) return;

    const options = Array.from(servicioSelect.querySelectorAll('option[data-cliente-id]'));
    const urlBuscar = wrap.dataset.url || '';
    let debounceTimer = null;

    const actualizarServicios = () => {
        const clienteId = String(clienteIdInput.value || '');
        const visibles = [];

        options.forEach((opt) => {
            const visible = clienteId !== '' && opt.dataset.clienteId === clienteId;
            opt.hidden = !visible;
            opt.disabled = !visible;
            if (visible) visibles.push(opt);
        });

        const selectedVisible = visibles.some((opt) => opt.value === servicioSelect.value);
        if (!selectedVisible) servicioSelect.value = '';

        if (msgUnico) msgUnico.classList.add('hidden');
        if (msgVacio) msgVacio.classList.add('hidden');
        if (msgAyuda) msgAyuda.classList.add('hidden');
        servicioSelect.classList.add('hidden');
        servicioSelect.required = false;

        if (!clienteId) {
            if (msgAyuda) msgAyuda.classList.remove('hidden');
            return;
        }

        if (visibles.length === 0) {
            if (msgVacio) msgVacio.classList.remove('hidden');
            return;
        }

        if (visibles.length === 1) {
            servicioSelect.value = visibles[0].value;
            servicioSelect.required = true;
            if (msgUnico && msgUnicoTexto) {
                msgUnicoTexto.textContent = visibles[0].textContent.trim() + ' (Asignado automáticamente)';
                msgUnico.classList.remove('hidden');
            }
            return;
        }

        servicioSelect.classList.remove('hidden');
        servicioSelect.required = true;
    };

    const ocultarResultados = () => {
        if (!resultados) return;
        resultados.classList.add('hidden');
        resultados.innerHTML = '';
        inputCliente.setAttribute('aria-expanded', 'false');
    };

    const elegirCliente = (id, label) => {
        clienteIdInput.value = String(id);
        inputCliente.value = label;
        inputCliente.readOnly = true;
        if (btnLimpiar) btnLimpiar.classList.remove('hidden');
        ocultarResultados();
        actualizarServicios();
    };

    const mostrarResultados = (items) => {
        if (!resultados) return;
        resultados.innerHTML = '';
        if (!items || items.length === 0) {
            resultados.innerHTML = '<div class="px-4 py-2.5 text-xs text-gray-500">No se encontraron clientes coincidentes.</div>';
            resultados.classList.remove('hidden');
            return;
        }
        items.forEach((c) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-4 py-2.5 hover:bg-purple-50 dark:hover:bg-purple-900/30 text-gray-900 dark:text-gray-100 text-xs border-b border-gray-100 dark:border-gray-700/50 last:border-none flex items-center justify-between gap-2';

            const nombre = ((c.nombre || '') + ' ' + (c.apellido || '')).trim();
            const doc = c.cedula ? 'CI: ' + c.cedula : 'Sin CI';
            const tel = c.telefono ? ' · Tel: ' + c.telefono : '';

            btn.innerHTML = '<div><strong class="font-bold text-sm block">' + nombre + '</strong>' +
                            '<span class="text-gray-500 font-mono text-[11px]">' + doc + tel + '</span></div>' +
                            '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 dark:bg-gray-700">' + (c.estado || 'cliente') + '</span>';

            btn.addEventListener('click', () => elegirCliente(c.cliente_id, nombre + ' (' + doc + ')'));
            resultados.appendChild(btn);
        });
        resultados.classList.remove('hidden');
        inputCliente.setAttribute('aria-expanded', 'true');
    };

    const buscar = () => {
        const q = inputCliente.value.trim();
        if (q.length < 2 || !urlBuscar) {
            ocultarResultados();
            return;
        }
        fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then((r) => r.json()).then((data) => {
            mostrarResultados(Array.isArray(data) ? data : []);
        }).catch(() => mostrarResultados([]));
    };

    inputCliente.addEventListener('input', () => {
        if (inputCliente.readOnly) return;
        clienteIdInput.value = '';
        if (btnLimpiar) btnLimpiar.classList.add('hidden');
        actualizarServicios();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(buscar, 200);
    });

    inputCliente.addEventListener('focus', () => {
        if (inputCliente.readOnly) return;
        if (inputCliente.value.trim().length >= 2) buscar();
    });

    inputCliente.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') ocultarResultados();
    });

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            clienteIdInput.value = '';
            inputCliente.value = '';
            inputCliente.readOnly = false;
            btnLimpiar.classList.add('hidden');
            ocultarResultados();
            actualizarServicios();
            inputCliente.focus();
        });
    }

    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) ocultarResultados();
    });

    actualizarServicios();
});
</script>
@endpush

@include('tv-cuentas._historial-pago-modal')
@endsection
