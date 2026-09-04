@extends('layouts.app')

@section('title', 'Configuración - Backup de base de datos')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="{{ route('configuracion.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 text-sm font-medium">&larr; Configuración</a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Backup de base de datos</h1>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-lg bg-green-100 dark:bg-green-900/20 text-green-800 dark:text-green-200 text-sm border border-green-200 dark:border-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-lg bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-200 text-sm border border-red-200 dark:border-red-800">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Descargar copia</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Genera un archivo para guardar fuera del servidor. MySQL/MariaDB: archivo <code class="text-xs bg-gray-200 dark:bg-gray-600 px-1 rounded">.sql</code>. SQLite: copia del archivo <code class="text-xs bg-gray-200 dark:bg-gray-600 px-1 rounded">.sqlite</code>.</p>
        </div>

        <div class="p-6 space-y-4">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Motor</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $info['label'] }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400">Base / archivo</dt>
                    <dd class="font-mono text-xs break-all text-gray-900 dark:text-gray-100">{{ $info['database'] ?? '—' }}</dd>
                </div>
            </dl>

            @if($supported)
                <form action="{{ route('configuracion.backup.download') }}" method="POST" class="pt-2">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                        Descargar backup
                    </button>
                </form>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    En Windows, si el proyecto está en <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">xampp\htdocs\…</code>, se usa automáticamente <code class="break-all bg-gray-100 dark:bg-gray-700 px-1 rounded">mysqldump.exe</code> de esa instalación XAMPP. Si sigue fallando, definí <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">MYSQLDUMP_PATH</code> en <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">.env</code> con la ruta completa al ejecutable.
                </p>
            @else
                <p class="text-sm text-amber-700 dark:text-amber-300">Este proyecto usa un driver de base de datos que no está soportado para backup automático desde aquí.</p>
            @endif
        </div>
    </div>

    <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Google Drive</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Sube una copia a la carpeta configurada. El schedule reintenta después de la hora si el PC estaba apagado o si el backup falló.</p>
        </div>
        <div class="p-6 space-y-4">
            @php
                $workerOk = !empty($schedule['worker']);
                $latido = $schedule['latido'] ?? null;
            @endphp
            <div class="text-sm rounded-lg border px-3 py-2 {{ $workerOk ? 'border-emerald-200 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-200' : 'border-rose-200 bg-rose-50 dark:bg-rose-900/20 text-rose-800 dark:text-rose-200' }}">
                Worker InfinitySchedule:
                <strong>{{ $workerOk ? 'activo' : 'sin latido' }}</strong>
                @if($latido)
                    · último {{ $latido->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
                @endif
                · hoy: {{ !empty($schedule['backup_ok_hoy']) ? 'backup OK' : 'aún no hay backup OK' }}
            </div>

            <form action="{{ route('configuracion.backup.hora') }}" method="POST" class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hora automática</label>
                    <input type="time" name="hora" required value="{{ old('hora', $hora ?? '02:30') }}"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Guardar hora
                </button>
            </form>
            @php
                $driveTokenExpired = ($driveTokenStatus ?? '') === 'expired'
                    || session('drive_token_expired');
            @endphp

            @if($driveFolderId)
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Carpeta:
                    <a href="https://drive.google.com/drive/folders/{{ $driveFolderId }}" target="_blank" rel="noopener"
                       class="font-mono text-xs text-blue-600 dark:text-blue-400 hover:underline break-all">
                        Infinity Backups
                    </a>
                </p>
            @endif

            @if($driveCanAuth && $driveTokenExpired)
                <div class="text-sm rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 px-3 py-3 space-y-3">
                    <p>El token de Google Drive expiró o fue revocado. Solicitá acceso de nuevo para poder subir backups.</p>
                    <form action="{{ route('configuracion.backup.drive.auth') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                            Solicitar acceso
                        </button>
                    </form>
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        Se usa la misma URI que el comando artisan
                        (<code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">http://127.0.0.1:8765/</code>)
                        para evitar el error <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">redirect_uri_mismatch</code>.
                    </p>
                </div>
            @elseif($driveReady)
                @if($supported)
                    <form action="{{ route('configuracion.backup.drive') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                            Subir a Google Drive
                        </button>
                    </form>
                @endif
            @else
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    Drive no está listo. Configurá <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">BACKUP_DRIVE_ENABLED</code>,
                    credenciales OAuth y <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">GOOGLE_DRIVE_FOLDER_ID</code> en <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">.env</code>.
                </p>
                @if($driveCanAuth)
                    <form action="{{ route('configuracion.backup.drive.auth') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors">
                            Solicitar acceso
                        </button>
                    </form>
                @else
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        Después ejecutá <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">php artisan backup:drive-auth</code>.
                    </p>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
