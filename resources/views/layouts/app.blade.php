<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Infinity ISP') - {{ config('app.name', 'Infinity ISP') }}</title>
    <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">
    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = stored === 'dark' || (!stored && prefersDark);
            if (isDark) document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        })();
    </script>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors">
    @auth
    {{-- Sidebar: oculto al imprimir --}}
    <div class="print:hidden">
        @include('partials.sidebar')
    </div>
    @endauth

    <div class="{{ auth()->check() ? 'lg:ml-64' : '' }} flex flex-col min-h-screen transition-all duration-300 print:ml-0 bg-gray-50 dark:bg-gray-900">
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
                        <button type="button" id="theme-toggle" class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800 transition-colors" aria-label="Cambiar tema">
                            <svg id="theme-icon-light" class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <svg id="theme-icon-dark" class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                        </button>
                        @auth
                            @include('partials.notifications')
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

        <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8 print:py-0 print:px-0">
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
            @yield('content')
        </main>
    </div>

    <script src="{{ asset(mix('js/app.js')) }}" defer></script>
    <script>
        document.getElementById('theme-toggle')?.addEventListener('click', function() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

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
