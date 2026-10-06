@extends('layouts.app')

@section('title', 'Solicitud #'.$solicitud->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <a href="{{ route('solicitudes-acceso.index', ['estado' => $solicitud->estado]) }}"
               class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Volver al listado</a>
            <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">Solicitud #{{ $solicitud->id }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $solicitud->nombre }} · {{ $solicitud->cedula }}</p>
        </div>
        @php
            $estadoClasses = match ($solicitud->estado) {
                'pendiente_verificacion' => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200 dark:bg-violet-950/40 dark:text-violet-200 dark:ring-violet-800',
                'pendiente' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800',
                'aprobada' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-800',
                default => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200 dark:bg-rose-950/40 dark:text-rose-200 dark:ring-rose-800',
            };
            $esAdmin = $esAdmin ?? (auth()->user()?->esAdministrador() ?? false);
            $puedeEditar = auth()->user()?->tienePermiso('solicitudes-acceso.editar') ?? false;
        @endphp
        <div class="flex flex-col items-stretch sm:items-end gap-2">
            <span class="inline-flex self-start sm:self-end rounded-full px-3 py-1 text-sm font-medium {{ $estadoClasses }}">
                {{ App\Models\SolicitudAcceso::estados()[$solicitud->estado] ?? $solicitud->estado }}
            </span>
            @include('solicitudes-acceso._acciones', [
                'solicitud' => $solicitud,
                'esAdmin' => $esAdmin,
                'puedeEditar' => $puedeEditar,
                'usuarioPortal' => $usuarioPortal ?? null,
                'hideVer' => true,
            ])
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('clave_portal'))
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-4 dark:border-blue-800 dark:bg-blue-900/30">
            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">Clave de acceso para la app (mostrarla una sola vez):</p>
            <p class="mt-2 font-mono text-2xl font-bold tracking-wider text-blue-700 dark:text-blue-300">{{ session('clave_portal') }}</p>
            <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">Usuario: documento {{ $solicitud->cedula }} · Contraseña: la clave de arriba</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Datos de la solicitud</h2>
                @if(in_array($solicitud->estado, ['pendiente', 'pendiente_verificacion'], true) && $puedeEditar)
                    <form action="{{ route('solicitudes-acceso.actualizar', $solicitud) }}" method="POST" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="block text-gray-500 dark:text-gray-400 mb-1">Nombre</label>
                                <input type="text" name="nombre" required maxlength="200"
                                       value="{{ old('nombre', $solicitud->nombre) }}"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="block text-gray-500 dark:text-gray-400 mb-1">Documento</label>
                                <input type="text" name="cedula" required maxlength="20"
                                       value="{{ old('cedula', $solicitud->cedula) }}"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="block text-gray-500 dark:text-gray-400 mb-1">WhatsApp</label>
                                <input type="text" name="whatsapp" maxlength="30"
                                       value="{{ old('whatsapp', $solicitud->whatsapp) }}"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                @if($solicitud->telefono_verificado)
                                    <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">Verificado por OTP · Meta from: {{ $solicitud->whatsapp_from ?: '—' }}</p>
                                @elseif($solicitud->estado === 'pendiente_verificacion')
                                    <p class="mt-1 text-xs text-violet-600 dark:text-violet-400">Esperando verificación WhatsApp</p>
                                @endif
                            </div>
                            <div>
                                <label class="block text-gray-500 dark:text-gray-400 mb-1">Fecha</label>
                                <p class="mt-1.5 font-medium text-gray-900 dark:text-gray-100">{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-gray-500 dark:text-gray-400 mb-1">Dirección</label>
                                <input type="text" name="direccion" maxlength="500"
                                       value="{{ old('direccion', $solicitud->direccion) }}"
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                            </div>
                            @if($solicitud->latitud && $solicitud->longitud)
                                <div class="sm:col-span-2">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ubicación GPS:
                                        <a href="https://www.google.com/maps?q={{ $solicitud->latitud }},{{ $solicitud->longitud }}"
                                           target="_blank" rel="noopener"
                                           class="text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ $solicitud->latitud }}, {{ $solicitud->longitud }}
                                        </a>
                                    </p>
                                </div>
                            @endif
                        </div>
                        <button type="submit"
                                class="rounded-lg bg-slate-800 dark:bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900">
                            Guardar correcciones
                        </button>
                    </form>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Nombre</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->nombre }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Documento</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->cedula }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">WhatsApp</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">
                                @if($solicitud->whatsapp)
                                    <a href="https://wa.me/595{{ ltrim(preg_replace('/\D+/', '', $solicitud->whatsapp), '0') }}"
                                       target="_blank" rel="noopener"
                                       class="text-blue-600 dark:text-blue-400 hover:underline">{{ $solicitud->whatsapp }}</a>
                                    @if($solicitud->telefono_verificado)
                                        <span class="ml-2 inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-200">Verificado</span>
                                    @elseif($solicitud->estado === 'pendiente_verificacion')
                                        <span class="ml-2 inline-flex rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900/40 dark:text-purple-200">Esperando WA</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        @if($solicitud->codigo_verificacion)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">OTP usado</dt>
                                <dd class="mt-0.5 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->codigo_verificacion }}</dd>
                            </div>
                        @endif
                        @if($solicitud->whatsapp_from)
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">WhatsApp (Meta from)</dt>
                                <dd class="mt-0.5 font-mono text-sm text-gray-900 dark:text-gray-100">{{ $solicitud->whatsapp_from }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Fecha</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Dirección</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->direccion ?: '—' }}</dd>
                        </div>
                        @if($solicitud->latitud && $solicitud->longitud)
                            <div class="sm:col-span-2">
                                <dt class="text-gray-500 dark:text-gray-400">Ubicación</dt>
                                <dd class="mt-0.5">
                                    <a href="https://www.google.com/maps?q={{ $solicitud->latitud }},{{ $solicitud->longitud }}"
                                       target="_blank" rel="noopener"
                                       class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                        {{ $solicitud->latitud }}, {{ $solicitud->longitud }} (abrir mapa)
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cruce con base de clientes</h2>
                @if($coincideBd)
                    <p class="text-sm text-green-700 dark:text-green-300">
                        Coincide con cliente existente
                        @if($clienteExistente)
                            <a href="{{ route('clientes.detalle', $clienteExistente) }}" class="font-medium underline">
                                #{{ $clienteExistente->cliente_id }} — {{ $clienteExistente->nombre }} {{ $clienteExistente->apellido }}
                            </a>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Teléfono/ubicación del cliente solo se actualizan si lo confirmás abajo al aprobar.</p>
                @else
                    <p class="text-sm text-amber-700 dark:text-amber-300">No hay cliente con este documento. Al aprobar sin vínculo manual se creará uno nuevo.</p>
                @endif

                @if(in_array($solicitud->estado, ['pendiente', 'pendiente_verificacion'], true) && $puedeEditar)
                    <div class="mt-4 border-t border-gray-100 dark:border-gray-700 pt-4" id="vinculacion-manual">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">Vincular manualmente a un cliente</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Buscá por nombre, cédula o #ID si el documento de la solicitud no coincide (OCR / typo).</p>
                        <input type="search" id="buscar-cliente-solicitud" autocomplete="off" placeholder="Escribí al menos 2 caracteres…"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                        <ul id="buscar-cliente-resultados" class="mt-2 hidden max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800 text-sm"></ul>
                        <div id="cliente-vinculado-box" class="mt-3 {{ old('cliente_id_vinculacion') ? '' : 'hidden' }} rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/80 dark:bg-emerald-900/20 px-3 py-2 text-sm">
                            <p class="font-medium text-emerald-900 dark:text-emerald-200">
                                Cliente elegido:
                                <span id="cliente-vinculado-label">
                                    @if(old('cliente_id_vinculacion') && $clienteExistente && (int) old('cliente_id_vinculacion') === (int) $clienteExistente->cliente_id)
                                        #{{ $clienteExistente->cliente_id }} — {{ $clienteExistente->nombre }} {{ $clienteExistente->apellido }}
                                    @elseif(old('cliente_id_vinculacion'))
                                        #{{ old('cliente_id_vinculacion') }}
                                    @endif
                                </span>
                            </p>
                            <button type="button" id="cliente-vinculado-quitar" class="mt-1 text-xs text-rose-600 dark:text-rose-300 hover:underline">Quitar vínculo manual</button>
                        </div>
                    </div>
                @endif
            </div>

            @if($solicitud->estado === 'aprobada')
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Acceso portal / App</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Aprobada el {{ optional($solicitud->aprobado_at)->format('d/m/Y H:i') }}
                        @if($solicitud->aprobador)
                            por {{ $solicitud->aprobador->name }}
                        @endif
                        @if($solicitud->cliente_id)
                            · Cliente
                            <a href="{{ route('clientes.detalle', $solicitud->cliente_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">#{{ $solicitud->cliente_id }}</a>
                        @endif
                    </p>
                    @if($clienteExistente)
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">App activa</dt>
                                <dd class="mt-0.5 font-medium {{ $clienteExistente->app_activa ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-200' }}">
                                    {{ $clienteExistente->app_activa ? 'Sí' : 'No (aún no ingresó)' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Último ingreso</dt>
                                <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $clienteExistente->ultimo_ingreso ? $clienteExistente->ultimo_ingreso->format('d/m/Y H:i') : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Dispositivo</dt>
                                <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $clienteExistente->dispositivo ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Versión app</dt>
                                <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ $clienteExistente->app_version ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Usuario app</dt>
                                <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">Documento {{ $solicitud->cedula }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Estado acceso</dt>
                                <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">
                                    @if($usuarioPortal ?? null)
                                        {{ ucfirst(str_replace('_', ' ', $usuarioPortal->estado)) }}
                                        @if($usuarioPortal->push_token)
                                            · FCM
                                        @endif
                                    @else
                                        Sin usuario portal
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Clave</dt>
                                <dd class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    No se guarda en texto plano. Se mostró al aprobar / al reenviar. El cliente la recibe por WhatsApp.
                                </dd>
                            </div>
                        </dl>
                    @endif

                    @if(isset($avisosWhatsapp) && $avisosWhatsapp->isNotEmpty())
                        <div class="border-t border-gray-100 dark:border-gray-700 pt-3">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Avisos WhatsApp enviados</p>
                            <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                @foreach($avisosWhatsapp as $wa)
                                    <li>
                                        <span class="font-medium">{{ $wa->contexto_tipo }}</span>
                                        · {{ $wa->estado }}
                                        · {{ $wa->telefono }}
                                        · {{ optional($wa->created_at)->format('d/m/Y H:i') }}
                                        @if($wa->error_message)
                                            <span class="text-red-600 dark:text-red-300"> — {{ $wa->error_message }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($esAdmin) && ($usuarioPortal ?? null))
                        <div class="border-t border-gray-100 dark:border-gray-700 pt-4 space-y-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Editar acceso (admin)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Baja, reactivar, clave y eliminar están en el menú ⋮ de arriba.</p>
                            <form action="{{ route('solicitudes-acceso.actualizar-acceso', $solicitud) }}" method="POST" class="space-y-2">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Nombre en app</label>
                                    <input type="text" name="name" value="{{ old('name', $usuarioPortal->name) }}" required
                                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-0.5">Estado acceso</label>
                                    <select name="estado" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                                        <option value="activo" @selected($usuarioPortal->estado === 'activo')>Activo</option>
                                        <option value="suspendido" @selected($usuarioPortal->estado === 'suspendido')>Suspendido</option>
                                        <option value="pendiente_aprobacion" @selected($usuarioPortal->estado === 'pendiente_aprobacion')>Pendiente</option>
                                    </select>
                                </div>
                                <button type="submit" class="rounded-lg bg-gray-800 dark:bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Guardar</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Foto cédula (frente)</h2>
                @if($solicitud->frente_url)
                    <a href="{{ $solicitud->frente_url }}" target="_blank" rel="noopener">
                        <img src="{{ $solicitud->frente_url }}" alt="Cédula frente"
                             class="w-full rounded-lg border border-gray-200 dark:border-gray-600 object-contain max-h-80 bg-gray-50 dark:bg-gray-900">
                    </a>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin imagen.</p>
                @endif
            </div>

            @if(in_array($solicitud->estado, ['pendiente', 'pendiente_verificacion'], true) && auth()->user()?->tienePermiso('solicitudes-acceso.editar'))
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-3">
                    @if($solicitud->estado === 'pendiente_verificacion')
                        <p class="text-xs text-violet-700 dark:text-violet-300">
                            Solicitud legacy: el cliente aún no verificó WhatsApp. Con el flujo OTP invertido las nuevas llegan ya verificadas.
                        </p>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Corregí datos arriba si hace falta, vinculá un cliente en “Cruce”, y después aprobá.
                        </p>
                        <form action="{{ route('solicitudes-acceso.aprobar', $solicitud) }}" method="POST"
                              id="form-aprobar-solicitud"
                              onsubmit="return confirm('¿Aprobar y generar clave PLUS para la app?');"
                              class="space-y-3">
                            @csrf
                            <input type="hidden" name="cliente_id_vinculacion" id="input-cliente-vinculacion"
                                   value="{{ old('cliente_id_vinculacion', $coincideBd && $clienteExistente ? $clienteExistente->cliente_id : '') }}">
                            <input type="hidden" name="documento_corregido" id="input-documento-corregido" value="">
                            <input type="hidden" name="nombre_corregido" id="input-nombre-corregido" value="">
                            <input type="hidden" name="whatsapp_corregido" id="input-whatsapp-corregido" value="">
                            <input type="hidden" name="direccion_corregida" id="input-direccion-corregida" value="">

                            <div id="aprobar-vinculo-aviso" class="text-xs rounded-lg px-3 py-2 {{ $coincideBd ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200' }}">
                                @if($coincideBd && $clienteExistente)
                                    Se vinculará a #{{ $clienteExistente->cliente_id }} (match por documento). Podés cambiarlo en el buscador de la izquierda.
                                @else
                                    Sin vínculo manual se creará un cliente nuevo.
                                @endif
                            </div>

                            <div id="bloque-actualizar-cliente" class="{{ $coincideBd && $clienteExistente ? '' : 'hidden' }} rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-900/20 p-3 space-y-2 text-sm">
                                <p class="font-medium text-amber-900 dark:text-amber-200">Pre-aprobación: actualizar datos del cliente</p>
                                <label class="flex items-start gap-2 text-amber-900 dark:text-amber-100">
                                    <input type="checkbox" name="actualizar_telefono" value="1" class="mt-1 rounded border-amber-400 text-green-600 focus:ring-green-500">
                                    <span>Actualizar teléfono<br>
                                        <span class="text-xs opacity-80">Actual: <span id="tel-cliente-actual">{{ $clienteExistente->telefono ?? '—' }}</span> → Solicitud: {{ $solicitud->whatsapp ?: '—' }}</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 text-amber-900 dark:text-amber-100">
                                    <input type="checkbox" name="actualizar_ubicacion" value="1" class="mt-1 rounded border-amber-400 text-green-600 focus:ring-green-500">
                                    <span>Actualizar dirección / ubicación<br>
                                        <span class="text-xs opacity-80">Solo si marcás esta casilla.</span>
                                    </span>
                                </label>
                            </div>
                            <button type="submit"
                                    class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                                Aprobar y generar clave
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('solicitudes-acceso.rechazar', $solicitud) }}" method="POST"
                          onsubmit="return confirm('¿Rechazar esta solicitud?');"
                          class="space-y-2">
                        @csrf
                        <input type="text" name="motivo" maxlength="500" placeholder="Motivo (opcional, se envía por WhatsApp)"
                               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm">
                        <button type="submit"
                                class="w-full rounded-lg border border-rose-300 px-4 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/30">
                            Rechazar
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

@include('solicitudes-acceso._menu_script')

@if(in_array($solicitud->estado, ['pendiente', 'pendiente_verificacion'], true) && $puedeEditar)
<script>
(function () {
    var urlBuscar = @json(route('solicitudes-acceso.buscar-clientes'));
    var inputBuscar = document.getElementById('buscar-cliente-solicitud');
    var lista = document.getElementById('buscar-cliente-resultados');
    var inputId = document.getElementById('input-cliente-vinculacion');
    var box = document.getElementById('cliente-vinculado-box');
    var label = document.getElementById('cliente-vinculado-label');
    var quitar = document.getElementById('cliente-vinculado-quitar');
    var aviso = document.getElementById('aprobar-vinculo-aviso');
    var bloqueAct = document.getElementById('bloque-actualizar-cliente');
    var formAprobar = document.getElementById('form-aprobar-solicitud');
    var timer = null;
    var matchAutoId = @json($coincideBd && $clienteExistente ? (int) $clienteExistente->cliente_id : null);

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setVinculo(id, texto, telefono) {
        if (!inputId) return;
        inputId.value = id ? String(id) : '';
        if (box && label) {
            if (id) {
                label.textContent = texto || ('#' + id);
                box.classList.remove('hidden');
            } else {
                label.textContent = '';
                box.classList.add('hidden');
            }
        }
        if (aviso) {
            if (id) {
                aviso.className = 'text-xs rounded-lg px-3 py-2 bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200';
                aviso.textContent = 'Se vinculará al cliente ' + (texto || ('#' + id)) + '.';
            } else {
                aviso.className = 'text-xs rounded-lg px-3 py-2 bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
                aviso.textContent = 'Sin vínculo manual se creará un cliente nuevo.';
            }
        }
        if (bloqueAct) {
            if (id) bloqueAct.classList.remove('hidden');
            else bloqueAct.classList.add('hidden');
        }
        var telEl = document.getElementById('tel-cliente-actual');
        if (telEl && telefono != null) telEl.textContent = telefono || '—';
    }

    if (quitar) {
        quitar.addEventListener('click', function () {
            setVinculo(null, '', null);
            if (lista) { lista.innerHTML = ''; lista.classList.add('hidden'); }
        });
    }

    if (inputBuscar && lista) {
        inputBuscar.addEventListener('input', function () {
            clearTimeout(timer);
            var q = inputBuscar.value.trim();
            if (q.length < 2) {
                lista.innerHTML = '';
                lista.classList.add('hidden');
                return;
            }
            timer = setTimeout(function () {
                fetch(urlBuscar + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json(); }).then(function (rows) {
                    lista.innerHTML = '';
                    if (!Array.isArray(rows) || !rows.length) {
                        lista.innerHTML = '<li class="px-3 py-2 text-gray-500">Sin resultados</li>';
                        lista.classList.remove('hidden');
                        return;
                    }
                    rows.forEach(function (c) {
                        var li = document.createElement('li');
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-100';
                        btn.innerHTML = '<span class="font-medium">' + escapeHtml(c.label) + '</span>' +
                            (c.estado ? '<span class="block text-xs text-gray-500">' + escapeHtml(c.estado) + '</span>' : '');
                        btn.addEventListener('click', function () {
                            var nombreCompleto = ((c.nombre || '') + ' ' + (c.apellido || '')).trim();
                            setVinculo(c.cliente_id, '#' + c.cliente_id + ' — ' + nombreCompleto + ' (CI ' + (c.cedula || '—') + ')', c.telefono);
                            // Sugerir documento/nombre del cliente elegido en los campos de corrección
                            var nombreInput = document.querySelector('form[action*="solicitudes-acceso"] input[name="nombre"]');
                            var cedulaInput = document.querySelector('form[action*="solicitudes-acceso"] input[name="cedula"]');
                            if (cedulaInput && c.cedula) cedulaInput.value = c.cedula;
                            if (nombreInput && nombreCompleto) nombreInput.value = nombreCompleto;
                            lista.innerHTML = '';
                            lista.classList.add('hidden');
                            inputBuscar.value = '';
                        });
                        li.appendChild(btn);
                        lista.appendChild(li);
                    });
                    lista.classList.remove('hidden');
                }).catch(function () {
                    lista.innerHTML = '<li class="px-3 py-2 text-rose-600">Error al buscar</li>';
                    lista.classList.remove('hidden');
                });
            }, 250);
        });
    }

    if (formAprobar) {
        formAprobar.addEventListener('submit', function () {
            var nombreInput = document.querySelector('input[name="nombre"]');
            var cedulaInput = document.querySelector('input[name="cedula"]');
            var waInput = document.querySelector('input[name="whatsapp"]');
            var dirInput = document.querySelector('input[name="direccion"]');
            var docHid = document.getElementById('input-documento-corregido');
            var nomHid = document.getElementById('input-nombre-corregido');
            var waHid = document.getElementById('input-whatsapp-corregido');
            var dirHid = document.getElementById('input-direccion-corregida');
            if (docHid && cedulaInput) docHid.value = cedulaInput.value.trim();
            if (nomHid && nombreInput) nomHid.value = nombreInput.value.trim();
            if (waHid && waInput) waHid.value = waInput.value.trim();
            if (dirHid && dirInput) dirHid.value = dirInput.value.trim();
            // Si quedó el match auto por documento y el usuario no eligió otro, enviar ese id
            if (inputId && !inputId.value && matchAutoId) {
                inputId.value = String(matchAutoId);
            }
        });
    }
})();
</script>
@endif
@endsection
