<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Infinity ISP') }}</title>
        <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">
        <script src="{{ asset(mix('js/app.js')) }}" defer></script>
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
