<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Infinity ISP') - {{ config('app.name', 'Infinity ISP') }}</title>
    <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">
    @stack('styles')
    @include('partials.theme-init')
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors">
    @auth
    {{-- Sidebar: oculto al imprimir --}}
    <div class="print:hidden">
        @include('partials.sidebar')
    </div>
    @endauth

    {{-- Margen desktop: expandido 16rem (w-64) / colapsado 5rem (w-20) — sync con Sidebar.vue --}}
    <style>
        #app-main-shell { transition: margin-left 0.3s ease; }
        @media (min-width: 1024px) {
            body.auth-layout #app-main-shell { margin-left: 16rem; }
            body.auth-layout #app-main-shell[data-sidebar="collapsed"] { margin-left: 5rem; }
        }
        @media print {
            #app-main-shell { margin-left: 0 !important; }
        }
    </style>
    <div id="app-main-shell" class="flex flex-col min-h-screen min-w-0 bg-gray-50 dark:bg-gray-900">
        <script>
            (function () {
                try {
                    var auth = {{ auth()->check() ? 'true' : 'false' }};
                    if (auth) document.body.classList.add('auth-layout');
                    var el = document.getElementById('app-main-shell');
                    if (!el || !auth) return;
                    var expanded = localStorage.getItem('infinity_sidebar_desktop_expanded') !== 'false';
                    el.setAttribute('data-sidebar', expanded ? 'expanded' : 'collapsed');
                    window.addEventListener('sidebar-desktop-change', function (e) {
                        var isExpanded = !!(e.detail && e.detail.expanded);
                        el.setAttribute('data-sidebar', isExpanded ? 'expanded' : 'collapsed');
                    });
                } catch (_) {}
            })();
        </script>
        <header class="bg-white dark:bg-gray-900 shadow-sm border-b border-gray-200 dark:border-gray-800 sticky top-0 z-40 print:hidden transition-colors">
            <div class="px-4 sm:px-6 lg:px-8">
                <div id="header-bar" class="relative flex justify-between items-center h-16 gap-2 sm:gap-4">
                    <div class="flex items-center shrink-0">
                        @auth
                        <button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('toggle-sidebar'))"
                            class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 dark:hover:text-gray-400"
                            aria-label="Abrir menú"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        @endauth
                        <a href="{{ url('/') }}" class="ml-2 lg:ml-0 text-xl font-bold text-gray-900 dark:text-gray-100">Infinity ISP</a>
                    </div>
                    @auth
                        @if(auth()->user()->esStaff() && auth()->user()->tienePermiso('clientes.ver') && ! request()->routeIs('hotspot.index') && ! (request()->routeIs('hotspot.dashboard') && request('vista') === 'usuarios'))
                            @include('partials.header-buscar-cliente')
                        @endif
                    @endauth
                    <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                        <style>
                            @media (max-width: 639px) {
                                #notifications-wrapper { display: none !important; }
                                .header-logout-btn {
                                    padding: 0.5rem;
                                    background: transparent;
                                    color: inherit;
                                }
                                .header-logout-btn:hover {
                                    background: rgb(243 244 246);
                                    color: rgb(55 65 81);
                                }
                                html.dark .header-logout-btn:hover {
                                    background: rgb(31 41 55);
                                    color: rgb(229 231 235);
                                }
                            }
                            @media (min-width: 640px) {
                                .header-logout-icon { display: none !important; }
                            }
                        </style>
                        @include('partials.theme-toggle')
                        @auth
                            @if(auth()->user()->esStaff() && auth()->user()->tienePermiso('chat-interno.ver'))
                                <a href="{{ route('chat.index') }}" class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200" aria-label="Chat interno" title="Chat interno">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v8a2 2 0 01-2 2h-2v2l-3-2H9a2 2 0 01-2-2v-1M15 6a2 2 0 012 2v6a2 2 0 01-2 2H7l-3 2v-2H4a2 2 0 01-2-2V8a2 2 0 012-2h11z"/>
                                    </svg>
                                    <span id="chat-interno-badge" class="absolute -right-0.5 -top-0.5 hidden h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-medium text-white flex">0</span>
                                </a>
                            @endif
                            @if(auth()->user()->esAdministrador())
                                @include('partials.notifications')
                            @endif
                            <span class="hidden sm:inline text-sm text-gray-600 dark:text-gray-400">{{ auth()->user()->name }}</span>
                            <form action="{{ url('/api/logout') }}" method="POST" class="inline" onsubmit="try{localStorage.removeItem('infinity_web_sesion')}catch(e){}">
                                @csrf
                                <button type="submit"
                                        class="header-logout-btn inline-flex items-center justify-center bg-gray-900 dark:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-600 transition-colors"
                                        title="Cerrar sesión" aria-label="Cerrar sesión">
                                    <svg class="header-logout-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    <span class="hidden sm:inline">Cerrar sesión</span>
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        @auth
            @php $discoAlerta = app(\App\Services\Sistema\DiscoService::class)->estado(); @endphp
            @if($discoAlerta['alerta'])
                <a href="{{ route('sistema.disco') }}" class="block bg-red-600 text-white text-sm px-4 py-2 print:hidden">
                    Disco casi lleno: {{ $discoAlerta['texto'] }}. Abrir el detalle.
                </a>
            @endif
        @endauth
        <main class="flex-1 min-w-0 overflow-x-hidden py-8 px-4 sm:px-6 lg:px-8 print:py-0 print:px-0">
            @if (session('success'))
                <div data-app-flash role="status" class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-200 print:hidden break-words text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div data-app-flash class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-red-800 dark:text-red-200 print:hidden break-words text-sm whitespace-pre-wrap">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('warning'))
                <div data-app-flash class="mb-6 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-amber-900 dark:text-amber-200 print:hidden break-words text-sm">
                    {{ session('warning') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="{{ asset(mix('js/theme.js')) }}"></script>
    <script src="{{ asset(mix('js/app.js')) }}" defer></script>
    @auth
    @if(auth()->user()->esStaff())
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" crossorigin="anonymous">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js" crossorigin="anonymous"></script>
        <style>
            #toast-container { z-index: 100000; }
            #toast-container.toast-top-right { top: 4.5rem; right: 1rem; }
            #toast-container > .toast-success.toast-wa {
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%23fff' d='M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z'/%3E%3C/svg%3E") !important;
                background-size: 22px 22px;
            }
        </style>
        <script>
            if (window.toastr) {
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: 8000,
                    extendedTimeOut: 3000,
                    newestOnTop: true,
                    escapeHtml: true
                };
            }
        </script>
    @endif
    @if(auth()->user()->esStaff() && auth()->user()->tienePermiso('chat-interno.ver'))
        @include('partials.chat-interno-realtime')
        <script>
            (function () {
                var url = @json(route('chat.avisos', [], false));
                function vistos() {
                    try { return JSON.parse(sessionStorage.getItem('chat_toast_vistos') || '{}'); } catch (e) { return {}; }
                }
                function marcarVisto(id) {
                    if (!id) return false;
                    var mapa = vistos();
                    if (mapa[id]) return true;
                    mapa[id] = 1;
                    var claves = Object.keys(mapa);
                    if (claves.length > 80) {
                        var recorte = {};
                        claves.slice(-40).forEach(function (clave) { recorte[clave] = 1; });
                        mapa = recorte;
                    }
                    sessionStorage.setItem('chat_toast_vistos', JSON.stringify(mapa));
                    return false;
                }
                function chatEnPantalla(conversacionId) {
                    var path = (window.location.pathname || '').replace(/\/+$/, '');
                    if (path !== '/chat' || !window.InfinityChat) return false;
                    var abierta = window.InfinityChat.conversacionAbierta;
                    if (abierta) return Number(abierta) === Number(conversacionId);
                    var query = new URLSearchParams(window.location.search).get('c');
                    return !!query && Number(query) === Number(conversacionId);
                }
                function toastChat(mensaje) {
                    if (!window.toastr || !window.InfinityChat || !mensaje) return;
                    if (Number(mensaje.usuario_id) === Number(window.InfinityChat.usuarioId)) return;
                    if (marcarVisto(mensaje.id)) return;
                    if (chatEnPantalla(mensaje.conversacion_id)) return;
                    var texto = mensaje.cuerpo || (mensaje.adjunto && mensaje.adjunto.nombre ? 'Archivo: ' + mensaje.adjunto.nombre : 'Nuevo mensaje');
                    toastr.info(texto, mensaje.autor || mensaje.titulo || 'Chat interno', {
                        onclick: function () {
                            if (mensaje.conversacion_id) {
                                window.location.href = window.InfinityChat.chatUrl + '?c=' + mensaje.conversacion_id;
                            }
                        }
                    });
                }
                window.addEventListener('chat:mensaje', function (evento) {
                    toastChat(evento.detail || {});
                });
                function cargar() {
                    var desde = 0;
                    try { desde = Number(sessionStorage.getItem('chat_toast_desde') || 0); } catch (e) {}
                    fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'desde=' + encodeURIComponent(desde), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    }).then(function (res) {
                        return res.ok ? res.json() : null;
                    }).then(function (data) {
                        if (!data) return;
                        if (desde > 0) (data.nuevos || []).forEach(toastChat);
                        if (data.ultimo_id) sessionStorage.setItem('chat_toast_desde', String(data.ultimo_id));
                    }).catch(function () {});
                }
                cargar();
                setInterval(cargar, 15000);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') cargar();
                });
            })();
        </script>
    @endif
    @if(auth()->user()->esStaff() && auth()->user()->tienePermiso('whatsapp.ver'))
        <script>
            (function () {
                var url = @json(route('whatsapp.sin-leer', [], false));
                var mensajesUrl = @json(route('whatsapp.mensajes', [], false));
                function toastWhatsapp(item) {
                    if (!window.toastr || !item) return;
                    var abierto = (new URLSearchParams(window.location.search).get('tel') || '').replace(/\D/g, '');
                    var tel = String(item.telefono || '').replace(/\D/g, '');
                    if (abierto && tel && abierto === tel) return;
                    toastr.success(item.cuerpo || 'Nuevo mensaje', item.nombre || 'WhatsApp', {
                        iconClass: 'toast-success toast-wa',
                        onclick: function () {
                            if (tel) window.location.href = mensajesUrl + '?tel=' + encodeURIComponent(item.telefono);
                        }
                    });
                }
                function toastTransferencia(item) {
                    if (!window.toastr || !item) return;
                    toastr.success(item.mensaje || 'Te pasaron un chat de WhatsApp.', 'WhatsApp', {
                        iconClass: 'toast-success toast-wa',
                        onclick: function () {
                            if (item.url) window.location.href = item.url;
                        }
                    });
                }
                function aplicar(chats) {
                    var el = document.getElementById('sidebar-app');
                    var vm = el && el.__vue_app__ && el.__vue_app__._instance && el.__vue_app__._instance.proxy;
                    if (!vm || !Array.isArray(vm.menu)) return false;
                    var texto = chats > 0 ? String(chats) : '';
                    function marcar(item) {
                        if (!item || typeof item !== 'object') return;
                        if (item.name === 'whatsapp' || item.name === 'rapido-whatsapp') {
                            item.badge = texto;
                        }
                        if (Array.isArray(item.submenu)) item.submenu.forEach(marcar);
                    }
                    vm.menu.forEach(marcar);
                    return true;
                }
                function cargar() {
                    var desde = 0;
                    var vistas = {};
                    var listo = false;
                    try {
                        desde = Number(sessionStorage.getItem('wa_toast_desde') || 0);
                        vistas = JSON.parse(sessionStorage.getItem('wa_toast_transf') || '{}');
                        listo = sessionStorage.getItem('wa_toast_transf_listo') === '1';
                    } catch (e) {}
                    var consulta = url + (url.indexOf('?') === -1 ? '?' : '&') + 'desde=' + encodeURIComponent(desde);
                    fetch(consulta, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    }).then(function (res) {
                        return res.ok ? res.json() : null;
                    }).then(function (data) {
                        if (!data || typeof data.chats !== 'number') return;
                        if (!aplicar(data.chats)) setTimeout(function () { aplicar(data.chats); }, 500);
                        if (desde > 0) (data.nuevos || []).forEach(toastWhatsapp);
                        if (data.ultimo_id) sessionStorage.setItem('wa_toast_desde', String(data.ultimo_id));
                        (data.transferencias || []).forEach(function (item) {
                            if (listo && item.id && !vistas[item.id]) toastTransferencia(item);
                            if (item.id) vistas[item.id] = 1;
                        });
                        sessionStorage.setItem('wa_toast_transf', JSON.stringify(vistas));
                        sessionStorage.setItem('wa_toast_transf_listo', '1');
                    }).catch(function () {});
                }
                cargar();
                setInterval(cargar, 15000);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') cargar();
                });
            })();
        </script>
    @endif
    <script>
        (function () {
            var url = @json(route('sesion.ping', [], false));
            function ping() {
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).catch(function () {});
            }
            setInterval(ping, 8 * 60 * 1000);
        })();
    </script>
    @endauth
    <script>
        (function() {
            var SCROLL_KEY = 'infinity_scroll';
            function getScrollKey() { return SCROLL_KEY + '_' + (window.location.pathname || '/'); }
            function saveScroll() {
                try { localStorage.setItem(getScrollKey(), String(window.scrollY || 0)); } catch(_) {}
            }
            function restoreScroll() {
                try {
                    var y = parseInt(localStorage.getItem(getScrollKey()), 10);
                    if (!isNaN(y) && y > 0) {
                        requestAnimationFrame(function() { window.scrollTo(0, y); });
                    }
                } catch(_) {}
            }
            window.addEventListener('beforeunload', saveScroll);
            window.addEventListener('pagehide', saveScroll);
            if (document.readyState === 'complete') restoreScroll();
            else window.addEventListener('load', function() { setTimeout(restoreScroll, 50); });
        })();
    </script>
    @stack('scripts')
</body>
</html>
