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
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
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
                        <a href="{{ auth()->user()?->tienePermiso('dashboard.ver') ? url('/') : route('inicio') }}" class="ml-2 lg:ml-0 text-xl font-bold text-gray-900 dark:text-gray-100">Infinity ISP</a>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-4">
                        @include('partials.theme-toggle')
                        @auth
                            @if(auth()->user()->esAdministrador())
                                @include('partials.notifications')
                            @endif
                            <span class="hidden sm:inline text-sm text-gray-600 dark:text-gray-400">{{ auth()->user()->name }}</span>
                            <form action="{{ url('/api/logout') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="bg-gray-900 dark:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800 dark:hover:bg-gray-600 transition-colors">
                                    Cerrar sesión
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 min-w-0 overflow-x-hidden py-8 px-4 sm:px-6 lg:px-8 print:py-0 print:px-0">
            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-200 print:hidden break-words text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-red-800 dark:text-red-200 print:hidden">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('warning'))
                <div class="mb-6 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-amber-900 dark:text-amber-200 print:hidden break-words text-sm">
                    {{ session('warning') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="{{ asset(mix('js/theme.js')) }}"></script>
    <script src="{{ asset(mix('js/app.js')) }}" defer></script>
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
