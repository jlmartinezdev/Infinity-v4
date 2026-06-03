<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Infinity ISP') - {{ config('app.name', 'Infinity ISP') }}</title>
    <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">
    @include('partials.theme-init')
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors">
    <div class="fixed top-4 right-4 z-50">
        @include('partials.theme-toggle')
    </div>
    @yield('content')
    <script src="{{ asset(mix('js/theme.js')) }}"></script>
    @stack('scripts')
</body>
</html>
