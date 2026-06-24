@extends('layouts.app')

@section('title', 'Configuración - SIFEN e-Kuatia')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
        <a href="{{ route('configuracion.sifen.prueba') }}" class="text-sm font-medium text-green-600 dark:text-green-400 hover:underline">Ir a laboratorio de prueba →</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Facturación electrónica (SIFEN)</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Datos del emisor, timbrado, certificado digital y CSC para e-Kuatia.</p>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-amber-100 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 text-sm border border-amber-200 dark:border-amber-800">{{ session('error') }}</div>
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

    @if(config('sifen.api.enabled'))
        <div class="mb-4 p-4 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 text-sm text-purple-900 dark:text-purple-200">
            <strong>Modo API activo.</strong> La firma y el envío a SIFEN se realizan en <strong>sifen-api</strong>.
            El certificado P12 de esta pantalla no se usa para emitir; configúrelo en el panel del microservicio.
        </div>
    @endif

    {{-- Estado del sistema --}}
    <div class="mb-6 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Estado actual</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Ambiente SIFEN</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">
                    @if($estado['ambiente'] === 'production')
                        <span class="text-red-600 dark:text-red-400">Producción</span>
                    @else
                        <span class="text-blue-600 dark:text-blue-400">Prueba (test)</span>
                    @endif
                    <span class="text-xs text-gray-400 block">Definido en SIFEN_AMBIENTE (.env)</span>
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Certificado digital</dt>
                <dd class="font-medium">
                    @if($estado['certificado_configurado'])
                        <span class="text-green-600 dark:text-green-400">Listo para firmar</span>
                    @elseif($estado['certificado_existe'])
                        <span class="text-amber-600 dark:text-amber-400">Archivo presente, falta contraseña</span>
                    @else
                        <span class="text-gray-500">Sin certificado</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">CSC (código QR)</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">
                    @if($config->csc_token || $estado['csc_env'])
                        Configurado
                    @else
                        <span class="text-amber-600">Pendiente</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Último número emitido</dt>
                <dd class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($config->ultimo_numero_factura) }}</dd>
            </div>
        </dl>
    </div>

    <form action="{{ route('configuracion.sifen.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Emisor --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Datos del emisor</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="ruc" class="block text-sm font-medium text-gray-700 dark:text-gray-300">RUC (sin DV)</label>
                        <input type="text" name="ruc" id="ruc" maxlength="8" required
                               value="{{ old('ruc', $config->ruc) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="dv_ruc" class="block text-sm font-medium text-gray-700 dark:text-gray-300">DV</label>
                        <input type="number" name="dv_ruc" id="dv_ruc" min="0" max="9" required
                               value="{{ old('dv_ruc', $config->dv_ruc) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div>
                    <label for="tipo_contribuyente" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de contribuyente</label>
                    <select name="tipo_contribuyente" id="tipo_contribuyente"
                            class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                        <option value="1" {{ old('tipo_contribuyente', $config->tipo_contribuyente) == 1 ? 'selected' : '' }}>Persona física</option>
                        <option value="2" {{ old('tipo_contribuyente', $config->tipo_contribuyente) == 2 ? 'selected' : '' }}>Persona jurídica</option>
                    </select>
                </div>
                <div>
                    <label for="razon_social" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Razón social</label>
                    <input type="text" name="razon_social" id="razon_social" required maxlength="255"
                           value="{{ old('razon_social', $config->razon_social) }}"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div>
                    <label for="nombre_fantasia" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre de fantasía</label>
                    <input type="text" name="nombre_fantasia" id="nombre_fantasia" maxlength="255"
                           value="{{ old('nombre_fantasia', $config->nombre_fantasia) }}"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="codigo_actividad_economica" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código actividad económica</label>
                        <input type="text" name="codigo_actividad_economica" id="codigo_actividad_economica" maxlength="10"
                               value="{{ old('codigo_actividad_economica', $config->codigo_actividad_economica) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="descripcion_actividad_economica" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción actividad</label>
                        <input type="text" name="descripcion_actividad_economica" id="descripcion_actividad_economica" maxlength="255"
                               value="{{ old('descripcion_actividad_economica', $config->descripcion_actividad_economica) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                        <input type="text" name="direccion" id="direccion" required maxlength="255"
                               value="{{ old('direccion', $config->direccion) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="numero_casa" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nº casa</label>
                        <input type="text" name="numero_casa" id="numero_casa" maxlength="10"
                               value="{{ old('numero_casa', $config->numero_casa ?? '0') }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="departamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cód. departamento</label>
                        <input type="number" name="departamento" id="departamento" min="1" required
                               value="{{ old('departamento', $config->departamento) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="departamento_descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Departamento</label>
                        <input type="text" name="departamento_descripcion" id="departamento_descripcion" required maxlength="50"
                               value="{{ old('departamento_descripcion', $config->departamento_descripcion) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="distrito" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cód. distrito</label>
                        <input type="number" name="distrito" id="distrito" min="1" required
                               value="{{ old('distrito', $config->distrito) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="distrito_descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Distrito</label>
                        <input type="text" name="distrito_descripcion" id="distrito_descripcion" required maxlength="50"
                               value="{{ old('distrito_descripcion', $config->distrito_descripcion) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="ciudad" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cód. ciudad</label>
                        <input type="number" name="ciudad" id="ciudad" min="1" required
                               value="{{ old('ciudad', $config->ciudad) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="ciudad_descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ciudad</label>
                        <input type="text" name="ciudad_descripcion" id="ciudad_descripcion" required maxlength="50"
                               value="{{ old('ciudad_descripcion', $config->ciudad_descripcion) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" required maxlength="20"
                               value="{{ old('telefono', $config->telefono) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo electrónico</label>
                        <input type="email" name="email" id="email" required maxlength="100"
                               value="{{ old('email', $config->email) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
            </div>
        </div>

        {{-- Timbrado --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Timbrado y numeración</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="numero_timbrado" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de timbrado</label>
                    <input type="text" name="numero_timbrado" id="numero_timbrado" required maxlength="8" pattern="\d{8}"
                           value="{{ old('numero_timbrado', $config->numero_timbrado) }}"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="establecimiento" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Establecimiento</label>
                        <input type="number" name="establecimiento" id="establecimiento" min="1" max="999" required
                               value="{{ old('establecimiento', $config->establecimiento) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="punto_expedicion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Punto de expedición</label>
                        <input type="number" name="punto_expedicion" id="punto_expedicion" min="1" max="999" required
                               value="{{ old('punto_expedicion', $config->punto_expedicion) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="serie_actual" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Serie (2 letras)</label>
                        <input type="text" name="serie_actual" id="serie_actual" maxlength="2" pattern="[A-Z]{2}"
                               value="{{ old('serie_actual', $config->serie_actual) }}"
                               placeholder="AA"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100 uppercase">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="timbrado_vigencia_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vigencia desde</label>
                        <input type="date" name="timbrado_vigencia_desde" id="timbrado_vigencia_desde" required
                               value="{{ old('timbrado_vigencia_desde', $config->timbrado_vigencia_desde?->format('Y-m-d')) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label for="timbrado_vigencia_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vigencia hasta</label>
                        <input type="date" name="timbrado_vigencia_hasta" id="timbrado_vigencia_hasta"
                               value="{{ old('timbrado_vigencia_hasta', $config->timbrado_vigencia_hasta?->format('Y-m-d')) }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
            </div>
        </div>

        {{-- CSC --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">CSC (código de seguridad del contribuyente)</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Requerido para generar el código QR del KuDE.</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="csc_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Id CSC</label>
                        <input type="text" name="csc_id" id="csc_id" maxlength="4"
                               value="{{ old('csc_id', $config->csc_id) }}"
                               placeholder="0001"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="csc_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Token CSC (32 caracteres)</label>
                        <input type="password" name="csc_token" id="csc_token" maxlength="32" autocomplete="new-password"
                               placeholder="{{ ($config->csc_token || $estado['csc_env']) ? '•••••••• (dejar vacío para no cambiar)' : 'Ingrese el token CSC' }}"
                               class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                        @if($estado['csc_env'] && !$config->csc_token)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">También hay un CSC definido en .env (SIFEN_CSC_TOKEN).</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Certificado --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Certificado digital (.p12 / .pfx)</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Firma electrónica del documento. Se guarda en {{ $estado['certificado_ruta'] }}.</p>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label for="certificado_p12" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Archivo certificado</label>
                    <input type="file" name="certificado_p12" id="certificado_p12" accept=".p12,.pfx"
                           class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-green-50 file:text-green-700 dark:file:bg-green-900/30 dark:file:text-green-300 hover:file:bg-green-100">
                    @if($estado['certificado_existe'])
                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">Certificado actual cargado en el servidor.</p>
                    @endif
                </div>
                <div>
                    <label for="certificado_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contraseña del certificado</label>
                    <input type="password" name="certificado_password" id="certificado_password" autocomplete="new-password"
                           placeholder="{{ ($estado['password_db'] || $estado['password_env']) ? '•••••••• (dejar vacío para no cambiar)' : 'Contraseña del archivo P12' }}"
                           class="mt-1 w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 bg-white dark:bg-gray-700 dark:text-gray-100">
                    @if($estado['password_env'] && !$estado['password_db'])
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">También puede definirse en .env (SIFEN_CERT_PASSWORD).</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Activo --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $config->activo) ? 'checked' : '' }}
                       class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 dark:bg-gray-700">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Configuración activa para emisión</span>
            </label>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                Guardar configuración SIFEN
            </button>
            <a href="{{ route('configuracion.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancelar</a>
        </div>
    </form>
</div>
@endsection
