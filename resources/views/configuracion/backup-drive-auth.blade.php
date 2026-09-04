@extends('layouts.app')

@section('title', 'Solicitar acceso a Google Drive')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.backup') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Backup</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Solicitar acceso a Google Drive</h1>

    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 space-y-4 text-sm text-gray-700 dark:text-gray-300">
            <p>Este cliente OAuth es de escritorio: Google solo acepta <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ $redirectUri }}</code> (la misma URI que <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">backup:drive-auth</code>).</p>

            <ol class="list-decimal list-inside space-y-2">
                <li>Abrí Google con el botón. Usá Chrome o Edge (no el navegador de Cursor).</li>
                <li>Aceptá el permiso con la cuenta de Drive.</li>
                @if(!empty($catcherOk))
                    <li>Si todo sale bien, esa pestaña vuelve sola a Backup.</li>
                    <li>Si el navegador queda en «No se puede acceder a este sitio» en 127.0.0.1, copiá la URL completa de la barra y pegala abajo.</li>
                @else
                    <li>El navegador va a quedar en «No se puede acceder a este sitio» en 127.0.0.1:8765. Eso es normal: copiá la URL completa de la barra y pegala abajo.</li>
                @endif
            </ol>

            <a href="{{ $authUrl }}" target="_blank" rel="noopener"
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                Continuar con Google
            </a>

            <form action="{{ route('configuracion.backup.drive.auth.completar') }}" method="POST" class="space-y-3 pt-2">
                @csrf
                <label for="oauth_url" class="block font-medium text-gray-800 dark:text-gray-200">Pegá la URL de 127.0.0.1</label>
                <textarea id="oauth_url" name="oauth_url" rows="3" required
                          placeholder="http://127.0.0.1:8765/?state=...&amp;code=..."
                          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-mono"></textarea>
                <button type="submit"
                        class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                    Confirmar acceso
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
