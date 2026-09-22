@extends('layouts.app')

@section('title', 'Cotización dólar')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">Cotización dólar</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Cambio actual</p>
            @if($actual)
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $actual->etiqueta() }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                    Vigente desde {{ $actual->fecha?->format('d/m/Y') ?? '—' }}
                    @if($actual->usuario)
                        · {{ $actual->usuario->name }}
                    @endif
                </p>
            @else
                <p class="text-lg font-medium text-amber-600 dark:text-amber-400">Todavía no hay cotización cargada</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Registrá el valor actual para usarlo como referencia en compras y ventas.</p>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Registrar cambio actual</h2>
            <form action="{{ route('cotizacion-dolar.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="valor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">1 USD equivale a (Gs) *</label>
                    <input type="number" name="valor" id="valor" value="{{ old('valor', $actual ? \App\Models\Producto::valorInput($actual->valor) : '') }}"
                        step="0.01" min="1" max="999999" required placeholder="Ej: 7300"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                    @error('valor')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="fecha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
                    <input type="date" name="fecha" id="fecha" value="{{ old('fecha', date('Y-m-d')) }}" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                    @error('fecha')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                    <input type="text" name="notas" id="notas" value="{{ old('notas') }}" maxlength="255" placeholder="Ej: Cotización BCP"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-colors">
                    @error('notas')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                    Guardar cotización
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Historial</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">1 USD</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notas</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Usuario</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($cotizaciones as $cotizacion)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ $loop->first ? 'bg-purple-50/60 dark:bg-purple-900/20' : '' }}">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $cotizacion->fecha?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ \App\Models\Producto::formatoNumero($cotizacion->valor) }} Gs</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $cotizacion->notas ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $cotizacion->usuario?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay cotizaciones registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cotizaciones->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                {{ $cotizaciones->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
