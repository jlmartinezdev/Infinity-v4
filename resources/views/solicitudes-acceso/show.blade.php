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
            $puedeEditar = auth()->user()?->tienePermiso('clientes.editar') ?? false;
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
                    <p class="text-sm text-amber-700 dark:text-amber-300">No hay cliente con este documento. Al aprobar se creará uno nuevo.</p>
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

            @if(in_array($solicitud->estado, ['pendiente', 'pendiente_verificacion'], true) && auth()->user()?->tienePermiso('clientes.editar'))
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-3">
                    @if($solicitud->estado === 'pendiente_verificacion')
                        <p class="text-xs text-violet-700 dark:text-violet-300">
                            Solicitud legacy: el cliente aún no verificó WhatsApp. Con el flujo OTP invertido las nuevas llegan ya verificadas.
                        </p>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">También podés aprobar/rechazar desde el menú ⋮. Acá podés confirmar actualización de datos.</p>
                        <form action="{{ route('solicitudes-acceso.aprobar', $solicitud) }}" method="POST"
                              onsubmit="return confirm('¿Aprobar y generar clave PLUS para la app?');"
                              class="space-y-3">
                            @csrf
                            @if($coincideBd && $clienteExistente)
                                <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-900/20 p-3 space-y-2 text-sm">
                                    <p class="font-medium text-amber-900 dark:text-amber-200">Pre-aprobación: actualizar datos del cliente</p>
                                    <label class="flex items-start gap-2 text-amber-900 dark:text-amber-100">
                                        <input type="checkbox" name="actualizar_telefono" value="1" class="mt-1 rounded border-amber-400 text-green-600 focus:ring-green-500">
                                        <span>Actualizar teléfono<br>
                                            <span class="text-xs opacity-80">Actual: {{ $clienteExistente->telefono ?: '—' }} → Solicitud: {{ $solicitud->whatsapp ?: '—' }}</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2 text-amber-900 dark:text-amber-100">
                                        <input type="checkbox" name="actualizar_ubicacion" value="1" class="mt-1 rounded border-amber-400 text-green-600 focus:ring-green-500">
                                        <span>Actualizar dirección / ubicación<br>
                                            <span class="text-xs opacity-80">Solo si marcás esta casilla.</span>
                                        </span>
                                    </label>
                                </div>
                            @endif
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
@endsection
