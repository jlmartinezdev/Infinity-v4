@extends('layouts.app')

@section('title', 'Configuración')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Configuración</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('configuracion.impresion') }}" class="block p-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-600 hover:shadow-md transition-all">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Impresión</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tamaño de papel, recibo con o sin gráfico (matricial).</p>
        </a>
        @if(auth()->user()->tienePermiso('configuracion.ver'))
        <a href="{{ route('configuracion.ajustes') }}" class="block p-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-600 hover:shadow-md transition-all">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Ajustes generales</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Nombre de empresa, logo, contactos y sitio web.</p>
        </a>
        <a href="{{ route('configuracion.facturacion') }}" class="block p-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-600 hover:shadow-md transition-all">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Facturación y servicios</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Fecha creación automática de facturas, cortes por falta de pago, notificaciones.</p>
        </a>
        <a href="{{ route('tareas-periodicas.index') }}" class="block p-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-600 hover:shadow-md transition-all">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Tareas periódicas</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Ver y gestionar tareas automáticas con nombre, acción, resultado y nodo.</p>
        </a>
        <a href="{{ route('configuracion.backup') }}" class="block p-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 hover:border-green-400 dark:hover:border-green-600 hover:shadow-md transition-all">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Backup base de datos</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Descargar copia SQL (MySQL/MariaDB) o archivo SQLite.</p>
        </a>
        @endif
    </div>
</div>
@endsection
