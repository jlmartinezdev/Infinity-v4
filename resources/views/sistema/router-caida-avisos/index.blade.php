@extends('layouts.app')

@section('title', 'Alertas caída de router')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Alertas WhatsApp — caída de router</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Si un router no responde al ping durante varias consultas seguidas, se avisa por WhatsApp a los usuarios elegidos.
                No genera entradas en auditoría ni en la campana del panel.
            </p>
        </div>
        <a href="{{ route('sistema.red-monitoreo.index') }}"
            class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Monitoreo de red</a>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-200 dark:border-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-200 dark:border-red-800 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 space-y-5">
        <form method="POST" action="{{ route('sistema.router-caida-avisos.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                <input type="checkbox" name="enabled" value="1" @checked(!empty($config['enabled']))
                    class="rounded border-gray-300 dark:border-gray-600 text-rose-600 focus:ring-rose-500">
                Activar avisos automáticos por WhatsApp
            </label>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Consultas fallidas seguidas antes de alertar
                </label>
                <input type="number" name="confirmaciones" min="1" max="20" required
                    value="{{ old('confirmaciones', $config['confirmaciones'] ?? 3) }}"
                    class="w-32 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Con el ping cada 60&nbsp;s, el valor 3 ≈ 3 minutos sin respuesta. Solo se envía un aviso hasta que el router vuelva a responder.
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quién recibe el aviso</label>
                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($staff as $u)
                        <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer">
                            <input type="checkbox" name="usuario_ids[]" value="{{ $u->usuario_id }}"
                                @checked(in_array((int) $u->usuario_id, $config['usuario_ids'] ?? [], true))
                                class="rounded border-gray-300 dark:border-gray-600 text-rose-600 focus:ring-rose-500">
                            <span class="flex-1 text-gray-900 dark:text-gray-100">{{ $u->name }}</span>
                            <span class="text-xs text-gray-400">{{ $u->telefono ?: 'sin teléfono' }}</span>
                        </label>
                    @empty
                        <p class="px-3 py-2 text-sm text-gray-500">No hay personal activo.</p>
                    @endforelse
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Cada usuario debe tener teléfono WhatsApp cargado en su ficha. Plantilla Meta: ver
                    <code class="text-xs">docs/whatsapp-plantilla-router-caido.md</code>.
                </p>
            </div>

            <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit"
                    class="px-4 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-700 font-medium">
                    Guardar
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('sistema.router-caida-avisos.probar') }}"
            onsubmit="return confirm('¿Enviar aviso de prueba [PRUEBA] a los destinatarios ya guardados?\n\nSi cambiaste la lista, guardá primero.');">
            @csrf
            <button type="submit"
                class="px-4 py-2 text-sm rounded-lg border border-emerald-400 dark:border-emerald-600 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 font-medium">
                Probar envío
            </button>
        </form>
    </div>
</div>
@endsection
