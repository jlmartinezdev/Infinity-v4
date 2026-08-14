@extends('layouts.app')
@section('title', 'App clientes — Configuración')
@php
    $pagoMeta = [
        'pago_online' => [
            'title' => 'Tarjeta / TPago',
            'hint' => 'El cliente confirma el monto y abre el checkout en la app.',
            'icon_default' => 'card',
            'color' => 'indigo',
        ],
        'pago_tigo_money' => [
            'title' => 'Tigo Money',
            'hint' => 'Muestra número/alias y botón para avisar por WhatsApp.',
            'icon_default' => 'tigo',
            'color' => 'sky',
        ],
        'pago_transferencia' => [
            'title' => 'Transferencia bancaria',
            'hint' => 'Muestra datos de cuenta (banco, n° cuenta, RUC, etc.).',
            'icon_default' => 'transfer',
            'color' => 'emerald',
        ],
        'pago_qr' => [
            'title' => 'Pago con QR',
            'hint' => 'Cuando tengas QR o link, el cliente lo ve en un modal.',
            'icon_default' => 'qr',
            'color' => 'violet',
        ],
    ];
    $autoKeys = array_keys($pagoMeta);
    $stateLabels = [
        'enabled' => 'Visible en la app',
        'coming_soon' => 'Pronto (chip “Pronto”)',
        'hidden' => 'Oculto',
        'auto' => 'Automático',
    ];
    $stateChip = [
        'enabled' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200',
        'coming_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'hidden' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-200',
        'auto' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
    ];
    $flagUi = [
        'plan_card' => ['Plan del cliente', 'Tarjeta con plan y estado en el Home'],
        'dark_mode' => ['Modo oscuro', 'Permite cambiar tema claro/oscuro'],
        'tickets' => ['Tickets / soporte', 'Crear y ver reclamos'],
        'push_notifications' => ['Notificaciones push', 'Avisos en el celular'],
        'interplus_ia' => ['Interplus IA', 'Insights y tips en Home'],
        'referidos' => ['Referidos', 'Código y puntos por traer amigos'],
        'speed_test_screen' => ['Test de velocidad', 'Pantalla de speed test'],
        'chat_ia' => ['Chat IA', 'Asistente conversacional'],
        'iptv' => ['IPTV', 'Módulo TV en app'],
        'camaras' => ['Cámaras', 'Módulo cámaras'],
        'control_parental' => ['Control parental', 'Filtros familiares'],
        'vpn' => ['VPN', 'Módulo VPN'],
        'tecnico_geolocation' => ['Ubicación técnico', 'Ver técnico en camino'],
        'firma_digital' => ['Firma digital', 'Firmar documentos en app'],
        'router_realtime_monitoring' => ['Router en vivo', 'Monitoreo del router'],
        'coverage_map' => ['Mapa de cobertura', 'Ver cobertura'],
    ];
    $previewByKey = collect($previewFlags)->keyBy('key');
    $labelCls = 'block text-xs font-medium text-gray-600 dark:text-gray-300';
    $inputCls = 'mt-1.5 block w-full rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 text-sm px-3 py-2 shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition';
    $selectCls = $inputCls.' appearance-none pr-9';
    $textareaCls = $inputCls.' leading-relaxed resize-y min-h-[5.5rem]';
    $fileCls = 'mt-1.5 block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:text-sm file:font-medium file:shadow-sm hover:file:bg-indigo-500 file:cursor-pointer cursor-pointer';
    $checkCls = 'rounded-md border-gray-300 dark:border-gray-500 dark:bg-gray-800 text-indigo-600 focus:ring-indigo-500/40 focus:ring-offset-0';

    $errorBag = fn (string $prefix) => collect($errors->keys())->contains(fn ($k) => str_starts_with((string) $k, $prefix));
    $openPagos = $errorBag('metodos_pago.') || $errorBag('flags.pago_') || old('_open') === 'pagos';
    $openCheckout = $errorBag('pago_online.') || old('_open') === 'checkout';
    $openFlags = collect($flagKeys)->contains(fn ($k) => ! in_array($k, $autoKeys, true) && $errors->has('flags.'.$k)) || old('_open') === 'flags';
    $openWa = $errorBag('whatsapp.') || old('_open') === 'whatsapp';
    $openRef = $errorBag('referidos.') || old('_open') === 'referidos';
    $openResumen = $errorBag('resumen.') || old('_open') === 'resumen';
    $openFaqs = $errors->has('faqs_json') || old('_open') === 'faqs';

    $pagoPreviewSorted = collect($previewFlags)->whereIn('key', $pagoKeys)->sortBy(fn ($f) => $f['metadata']['sort_order'] ?? 99);
    $modulosActivos = collect($flagKeys)
        ->reject(fn ($k) => in_array($k, $autoKeys, true))
        ->filter(fn ($k) => ($flagsRaw[$k] ?? '') === 'enabled')
        ->count();
@endphp
@section('content')
<div class="max-w-5xl mx-auto space-y-5 pb-36 sm:pb-40 app-clientes-config">
    <style>
        .app-clientes-config select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
        }
        .app-clientes-config details > summary { list-style: none; }
        .app-clientes-config details > summary::-webkit-details-marker { display: none; }
        .app-clientes-config details[open] > summary .acc-chevron { transform: rotate(180deg); }
    </style>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                <a href="{{ route('loyalty.dashboard') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Loyalty</a>
                <span>/</span>
                <span>App config</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">App clientes</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">
                Configurá pagos, módulos y textos que ve Interplus. Abrí solo la sección que necesitás; un solo Guardar aplica todo.
            </p>
        </div>
        <a href="{{ route('loyalty.dashboard') }}" class="text-sm px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 dark:text-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">← Loyalty</a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-200">
            <p class="font-medium mb-1">Revisá estos campos:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Resumen rápido --}}
    <section class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Vista rápida</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Estado efectivo que recibe la app ahora.</p>
            </div>
            <span class="text-[11px] px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                {{ $modulosActivos }} módulos visibles
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach($pagoPreviewSorted as $f)
                @php
                    $st = $f['state'] ?? 'hidden';
                    $meta = $f['metadata'] ?? [];
                @endphp
                <div class="rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50/70 dark:bg-gray-900/40 px-3 py-2.5 {{ $st === 'hidden' ? 'opacity-55' : '' }}">
                    <div class="flex items-center gap-2 mb-1.5">
                        <div class="h-8 w-8 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 flex items-center justify-center overflow-hidden shrink-0">
                            @if(!empty($meta['icon_url']))
                                <img src="{{ $meta['icon_url'] }}" alt="" class="h-full w-full object-contain p-0.5">
                            @else
                                <span class="text-[9px] font-semibold uppercase text-gray-500">{{ $meta['icon'] ?? '?' }}</span>
                            @endif
                        </div>
                        <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $meta['title'] ?? $f['key'] }}</p>
                    </div>
                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full {{ $stateChip[$st] ?? $stateChip['hidden'] }}">
                        {{ $stateLabels[$st] ?? $st }}
                    </span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Atajos --}}
    <nav class="flex flex-wrap gap-2">
        @foreach([
            'pagos' => 'Métodos de pago',
            'checkout' => 'Checkout TPago',
            'flags' => 'Módulos app',
            'whatsapp' => 'WhatsApp',
            'referidos' => 'Referidos',
            'resumen' => 'Resumen Home',
            'faqs' => 'FAQs',
        ] as $id => $label)
            <button type="button"
                    onclick="document.getElementById('sec-{{ $id }}')?.scrollIntoView({behavior:'smooth', block:'start'}); document.getElementById('sec-{{ $id }}')?.setAttribute('open','');"
                    class="text-xs px-3 py-1.5 rounded-full border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:border-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition">
                {{ $label }}
            </button>
        @endforeach
        <button type="button" id="btn-expand-all"
                class="text-xs px-3 py-1.5 rounded-full border border-indigo-200 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-200">
            Expandir todo
        </button>
        <button type="button" id="btn-collapse-all"
                class="text-xs px-3 py-1.5 rounded-full border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
            Colapsar todo
        </button>
    </nav>

    <form method="POST" action="{{ route('loyalty.app-config.update') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        @method('PUT')

        {{-- Métodos de pago --}}
        <details id="sec-pagos" class="group rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openPagos) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Métodos de pago</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tarjeta, Tigo, transferencia y QR — logo, textos y visibilidad.</p>
                </div>
                <span class="text-[11px] font-medium px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ count($pagoKeys) }} métodos</span>
            </summary>
            <div class="px-4 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 px-1">
                    Subí el logo real (Tigo, banco, QR, tarjeta). Si no hay imagen, la app usa el ícono genérico.
                </p>

                @foreach($pagoKeys as $key)
                    @php
                        $info = $pagoMeta[$key];
                        $m = old('metodos_pago.'.$key, $metodosPago[$key] ?? []);
                        $fieldsJson = old('metodos_pago.'.$key.'.fields_json',
                            !empty($m['fields']) ? json_encode($m['fields'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''
                        );
                        $showWa = old('metodos_pago.'.$key.'.show_whatsapp_cta', $m['show_whatsapp_cta'] ?? ($key !== 'pago_online'));
                        $estado = old('flags.'.$key, $flagsRaw[$key] ?? 'hidden');
                        $iconPreview = $iconUrls[$key] ?? null;
                        $eff = $previewByKey[$key]['state'] ?? $estado;
                        $openMetodo = $errorBag('metodos_pago.'.$key) || $errors->has('flags.'.$key);
                    @endphp
                    <details class="rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50/40 dark:bg-gray-900/30 overflow-hidden" @if($openMetodo) open @endif>
                        <summary class="cursor-pointer select-none px-4 py-3 flex flex-wrap items-center gap-3 hover:bg-white/70 dark:hover:bg-gray-800/50 transition">
                            <span class="acc-chevron inline-flex text-gray-400 transition-transform">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                            <div class="h-11 w-11 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 flex items-center justify-center overflow-hidden shrink-0">
                                @if($iconPreview)
                                    <img src="{{ $iconPreview }}" alt="" class="h-full w-full object-contain p-1">
                                @else
                                    <span class="text-[10px] font-bold uppercase text-gray-500">{{ $m['icon'] ?? $info['icon_default'] }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $info['title'] }}</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $info['hint'] }}</p>
                            </div>
                            <span class="text-[10px] font-medium px-2 py-0.5 rounded-full {{ $stateChip[$eff] ?? $stateChip['hidden'] }}">
                                {{ $stateLabels[$eff] ?? $eff }}
                            </span>
                        </summary>

                        <div class="px-4 pb-4 space-y-4 border-t border-gray-200/80 dark:border-gray-700 bg-white dark:bg-gray-800">
                            <div class="pt-4">
                                <label class="block text-sm max-w-xs">
                                    <span class="{{ $labelCls }}">Visibilidad</span>
                                    <select name="flags[{{ $key }}]" class="{{ $selectCls }}">
                                        @foreach($stateLabels as $val => $label)
                                            <option value="{{ $val }}" @selected($estado === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Ahora en API: <strong class="text-gray-800 dark:text-gray-100">{{ $stateLabels[$eff] ?? $eff }}</strong></span>
                                </label>
                            </div>

                            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-900/50">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-100 mb-1">Ícono / logo</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">PNG o WebP cuadrado (recomendado 256×256).</p>
                                <div class="flex flex-wrap items-end gap-4">
                                    <label class="block text-sm flex-1 min-w-[14rem]">
                                        <span class="{{ $labelCls }}">Subir imagen</span>
                                        <input type="file" name="metodos_pago[{{ $key }}][icon_file]" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                                               class="{{ $fileCls }}">
                                    </label>
                                    <label class="block text-sm w-36">
                                        <span class="{{ $labelCls }}">Ícono genérico</span>
                                        <select name="metodos_pago[{{ $key }}][icon]" class="{{ $selectCls }}">
                                            @foreach(['card' => 'Tarjeta', 'tigo' => 'Tigo', 'qr' => 'QR', 'transfer' => 'Transferencia'] as $iconVal => $iconLabel)
                                                <option value="{{ $iconVal }}" @selected(($m['icon'] ?? $info['icon_default']) === $iconVal)>{{ $iconLabel }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm flex-1 min-w-[14rem]">
                                        <span class="{{ $labelCls }}">O pegar URL</span>
                                        <input type="url" name="metodos_pago[{{ $key }}][icon_url]" value="{{ old('metodos_pago.'.$key.'.icon_url', ($m['icon_path'] ?? null) ? '' : ($m['icon_url'] ?? '')) }}"
                                               placeholder="https://…/logo.png"
                                               class="{{ $inputCls }}">
                                    </label>
                                </div>
                                @if($iconPreview)
                                    <label class="inline-flex items-center gap-2 mt-3 text-sm text-red-600 dark:text-red-300">
                                        <input type="checkbox" name="metodos_pago[{{ $key }}][eliminar_icono]" value="1" class="{{ $checkCls }}">
                                        Quitar imagen actual
                                    </label>
                                @endif
                            </div>

                            <div class="grid sm:grid-cols-2 gap-3">
                                <label class="block text-sm">
                                    <span class="{{ $labelCls }}">Título en la casilla</span>
                                    <input type="text" name="metodos_pago[{{ $key }}][title]" value="{{ $m['title'] ?? '' }}"
                                           placeholder="Ej. Tigo Money" class="{{ $inputCls }}">
                                </label>
                                <label class="block text-sm">
                                    <span class="{{ $labelCls }}">Subtítulo</span>
                                    <input type="text" name="metodos_pago[{{ $key }}][subtitle]" value="{{ $m['subtitle'] ?? '' }}"
                                           placeholder="Texto corto bajo el título" class="{{ $inputCls }}">
                                </label>
                                <label class="block text-sm">
                                    <span class="{{ $labelCls }}">Chip / badge</span>
                                    <input type="text" name="metodos_pago[{{ $key }}][badge]" value="{{ $m['badge'] ?? '' }}"
                                           placeholder="Datos, Pronto…" class="{{ $inputCls }}">
                                </label>
                                <label class="block text-sm">
                                    <span class="{{ $labelCls }}">Orden (menor = primero)</span>
                                    <input type="number" name="metodos_pago[{{ $key }}][sort_order]" value="{{ $m['sort_order'] ?? '' }}"
                                           class="{{ $inputCls }}">
                                </label>
                            </div>

                            <label class="block text-sm">
                                <span class="{{ $labelCls }}">Instrucciones del modal</span>
                                <textarea name="metodos_pago[{{ $key }}][instructions]" rows="3"
                                          placeholder="Pasos que lee el cliente…"
                                          class="{{ $textareaCls }}">{{ $m['instructions'] ?? '' }}</textarea>
                            </label>

                            @if($key === 'pago_online')
                                <div class="grid sm:grid-cols-2 gap-3 rounded-lg bg-indigo-50 dark:bg-indigo-900/50 border border-indigo-200 dark:border-indigo-700 p-3">
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Proveedor (ej. TPago)</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][provider]" value="{{ $m['provider'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Nota extra</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][note]" value="{{ $m['note'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                </div>
                            @endif

                            @if($key === 'pago_tigo_money')
                                <div class="grid sm:grid-cols-3 gap-3 rounded-lg bg-sky-50 dark:bg-sky-900/50 border border-sky-200 dark:border-sky-700 p-3">
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Número Tigo Money</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][tigo_phone]" value="{{ $m['tigo_phone'] ?? '' }}" placeholder="0981…" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Alias</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][tigo_alias]" value="{{ $m['tigo_alias'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">CI / RUC</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][tigo_ci]" value="{{ $m['tigo_ci'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                </div>
                            @endif

                            @if($key === 'pago_transferencia')
                                <div class="grid sm:grid-cols-2 gap-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-700 p-3">
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Banco</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][bank]" value="{{ $m['bank'] ?? '' }}" placeholder="Ej. Banco Itaú" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Tipo de cuenta</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][account_type]" value="{{ $m['account_type'] ?? '' }}" placeholder="Caja de ahorro" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Nº de cuenta</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][account_number]" value="{{ $m['account_number'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Titular</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][account_holder]" value="{{ $m['account_holder'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">RUC / CI</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][account_ci_ruc]" value="{{ $m['account_ci_ruc'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Alias bancario</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][bank_alias]" value="{{ $m['bank_alias'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                </div>
                            @endif

                            @if($key === 'pago_qr')
                                <div class="grid sm:grid-cols-3 gap-3 rounded-lg bg-violet-50 dark:bg-violet-900/50 border border-violet-200 dark:border-violet-700 p-3">
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">Alias QR</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][qr_alias]" value="{{ $m['qr_alias'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm sm:col-span-2">
                                        <span class="{{ $labelCls }}">Link / imagen QR</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][qr_link]" value="{{ $m['qr_link'] ?? '' }}" placeholder="https://…" class="{{ $inputCls }}">
                                    </label>
                                    <label class="block text-sm">
                                        <span class="{{ $labelCls }}">ID QR</span>
                                        <input type="text" name="metodos_pago[{{ $key }}][qr_id]" value="{{ $m['qr_id'] ?? '' }}" class="{{ $inputCls }}">
                                    </label>
                                </div>
                            @endif

                            <div class="grid sm:grid-cols-2 gap-3">
                                <label class="block text-sm">
                                    <span class="{{ $labelCls }}">WhatsApp de este método</span>
                                    <input type="text" name="metodos_pago[{{ $key }}][whatsapp]" value="{{ $m['whatsapp'] ?? '' }}"
                                           placeholder="Vacío = usa cobranzas general" class="{{ $inputCls }}">
                                </label>
                                <label class="block text-sm">
                                    <span class="{{ $labelCls }}">Texto del botón WhatsApp</span>
                                    <input type="text" name="metodos_pago[{{ $key }}][whatsapp_cta_label]" value="{{ $m['whatsapp_cta_label'] ?? '' }}"
                                           placeholder="Avisar por WhatsApp" class="{{ $inputCls }}">
                                </label>
                                <label class="block text-sm sm:col-span-2">
                                    <span class="{{ $labelCls }}">Mensaje prellenado (usá {monto})</span>
                                    <input type="text" name="metodos_pago[{{ $key }}][whatsapp_template]" value="{{ $m['whatsapp_template'] ?? '' }}" class="{{ $inputCls }}">
                                </label>
                            </div>

                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="hidden" name="metodos_pago[{{ $key }}][show_whatsapp_cta]" value="0">
                                <input type="checkbox" name="metodos_pago[{{ $key }}][show_whatsapp_cta]" value="1"
                                       @checked(filter_var($showWa, FILTER_VALIDATE_BOOLEAN))
                                       class="{{ $checkCls }}">
                                Mostrar botón de WhatsApp en el modal
                            </label>

                            <details class="text-sm rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2">
                                <summary class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Opciones avanzadas (lista fields JSON)</summary>
                                <div class="mt-2 pb-1">
                                    <textarea name="metodos_pago[{{ $key }}][fields_json]" rows="4"
                                              placeholder='[{"label":"Banco","value":"Itaú","copyable":true}]'
                                              class="{{ $textareaCls }} font-mono text-xs">{{ $fieldsJson }}</textarea>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Si lo dejás vacío, la app arma la lista desde los campos de arriba.</p>
                                </div>
                            </details>
                        </div>
                    </details>
                @endforeach
            </div>
        </details>

        {{-- Checkout TPago --}}
        <details id="sec-checkout" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openCheckout) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Checkout con tarjeta (TPago)</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">URL de respaldo si TPago del servidor no está listo.</p>
                </div>
            </summary>
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Lo normal es configurar TPago en el servidor (<code class="font-mono px-1 py-0.5 rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100">TPAGO_*</code>).
                    Callback: <code class="font-mono break-all px-1 py-0.5 rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100">{{ rtrim(config('app.url'), '/') }}/api/v1/webhooks/tpago</code>.
                </p>
                <label class="block text-sm">
                    <span class="{{ $labelCls }}">URL de checkout (respaldo)</span>
                    <input type="url" name="pago_online[checkout_url]" value="{{ old('pago_online.checkout_url', $pago['checkout_url']) }}"
                           placeholder="https://checkout…?cliente={cliente_id}" class="{{ $inputCls }}">
                </label>
                <label class="block text-sm max-w-xs">
                    <span class="{{ $labelCls }}">Proveedor (respaldo)</span>
                    <input type="text" name="pago_online[provider]" value="{{ old('pago_online.provider', $pago['provider']) }}" class="{{ $inputCls }}">
                </label>
            </div>
        </details>

        {{-- Otras funciones --}}
        <details id="sec-flags" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openFlags) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Módulos de la app</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Home, tickets, IA, IPTV, referidos y más.</p>
                </div>
                <span class="text-[11px] font-medium px-2 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-200">{{ $modulosActivos }} on</span>
            </summary>
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">“Pronto” muestra el aviso sin abrir la función.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach($flagKeys as $key)
                        @if(in_array($key, $autoKeys, true))
                            @continue
                        @endif
                        @php
                            $ui = $flagUi[$key] ?? [str_replace('_', ' ', $key), $key];
                            $stFlag = $flagsRaw[$key] ?? 'hidden';
                        @endphp
                        <label class="block text-sm rounded-xl border border-gray-200 dark:border-gray-600 p-3 bg-gray-50/40 dark:bg-gray-900/40 hover:border-indigo-300 dark:hover:border-indigo-500 transition">
                            <span class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $ui[0] }}</span>
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full {{ $stateChip[$stFlag] ?? $stateChip['hidden'] }}">{{ ['enabled'=>'On','coming_soon'=>'Pronto','hidden'=>'Off'][$stFlag] ?? $stFlag }}</span>
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1.5 mt-0.5">{{ $ui[1] }}</span>
                            <select name="flags[{{ $key }}]" class="{{ $selectCls }}">
                                @foreach(['enabled' => 'Visible', 'coming_soon' => 'Pronto', 'hidden' => 'Oculto'] as $val => $label)
                                    <option value="{{ $val }}" @selected($stFlag === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </div>
        </details>

        {{-- WhatsApp --}}
        <details id="sec-whatsapp" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openWa) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">WhatsApp general</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Números de cobranzas y soporte.</p>
                </div>
            </summary>
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">El de cobranzas se usa en los métodos de pago si no cargás uno propio en cada método.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-sm">
                        <span class="{{ $labelCls }}">Cobranzas / pagos</span>
                        <input type="text" name="whatsapp[pagos]" value="{{ old('whatsapp.pagos', $whatsapp['pagos']) }}"
                               placeholder="59597…" class="{{ $inputCls }}">
                    </label>
                    <label class="block text-sm">
                        <span class="{{ $labelCls }}">Soporte</span>
                        <input type="text" name="whatsapp[soporte]" value="{{ old('whatsapp.soporte', $whatsapp['soporte']) }}" class="{{ $inputCls }}">
                    </label>
                </div>
            </div>
        </details>

        {{-- Referidos --}}
        <details id="sec-referidos" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openRef) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/40 text-amber-600 dark:text-amber-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Referidos</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Puntos por alta y link para compartir.</p>
                </div>
            </summary>
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-sm">
                        <span class="{{ $labelCls }}">Puntos por alta</span>
                        <input type="number" min="0" name="referidos[puntos_por_alta]" value="{{ old('referidos.puntos_por_alta', $referidos['puntos_por_alta']) }}" class="{{ $inputCls }}">
                    </label>
                    <label class="block text-sm">
                        <span class="{{ $labelCls }}">Link base para compartir</span>
                        <input type="text" name="referidos[link_base]" value="{{ old('referidos.link_base', $referidos['link_base']) }}"
                               placeholder="https://infinityisppro.net/r" class="{{ $inputCls }}">
                    </label>
                </div>
            </div>
        </details>

        {{-- Resumen --}}
        <details id="sec-resumen" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openResumen) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Resumen del Home</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Disponibilidad % opcional.</p>
                </div>
            </summary>
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <label class="block text-sm max-w-xs">
                    <span class="{{ $labelCls }}">Disponibilidad % (opcional)</span>
                    <input type="number" step="0.1" min="0" max="100" name="resumen[disponibilidad_pct]"
                           value="{{ old('resumen.disponibilidad_pct', $resumen['disponibilidad_pct']) }}"
                           placeholder="Automático si vacío" class="{{ $inputCls }}">
                </label>
            </div>
        </details>

        {{-- FAQs --}}
        <details id="sec-faqs" class="rounded-2xl border border-gray-200 bg-white dark:bg-gray-800 dark:border-gray-700 overflow-hidden" @if($openFaqs) open @endif>
            <summary class="cursor-pointer select-none px-5 py-4 flex items-center gap-3 hover:bg-gray-50/80 dark:hover:bg-gray-900/40 transition">
                <span class="acc-chevron inline-flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-900/40 text-rose-600 dark:text-rose-300 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Preguntas frecuentes</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">JSON de topics para soporte en la app.</p>
                </div>
            </summary>
            <div class="px-5 pb-5 space-y-3 border-t border-gray-100 dark:border-gray-700/80 pt-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">Endpoint aparte; la app puede aún no usarlo.</p>
                <textarea name="faqs_json" rows="10"
                          class="{{ $textareaCls }} font-mono text-xs min-h-[14rem]"
                          placeholder='[{"topic":"sin_internet","label":"Sin Internet","items":[...]}]'>{{ old('faqs_json', json_encode($faqs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
            </div>
        </details>

        <div class="h-20 sm:h-24" aria-hidden="true"></div>
        <div class="fixed bottom-0 inset-x-0 z-20 border-t border-gray-200 bg-white/95 dark:bg-gray-900/95 dark:border-gray-700 backdrop-blur pb-[env(safe-area-inset-bottom)]">
            <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
                <p class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">Los cambios se reflejan al instante en la API de la app.</p>
                <button type="submit" class="ml-auto px-6 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium shadow-lg shadow-blue-600/20">
                    Guardar configuración
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const root = document.querySelector('.app-clientes-config');
    if (!root) return;
    const topSections = () => root.querySelectorAll('form > details');

    document.getElementById('btn-expand-all')?.addEventListener('click', () => {
        root.querySelectorAll('details').forEach((el) => { el.open = true; });
    });
    document.getElementById('btn-collapse-all')?.addEventListener('click', () => {
        root.querySelectorAll('details').forEach((el) => { el.open = false; });
    });
})();
</script>
@endsection
