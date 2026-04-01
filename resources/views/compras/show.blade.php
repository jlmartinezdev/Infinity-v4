@extends('layouts.app')

@section('title', 'Compra #' . $compra->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Compra #{{ $compra->id }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('compras.edit', $compra) }}" class="inline-flex items-center px-4 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600">Editar</a>
            <a href="{{ route('compras.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">Volver</a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $compra->proveedor?->nombre ?? '—' }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $compra->proveedor?->ruc ?? '' }}</p>
                </div>
                <div class="text-right md:text-left">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fecha</p>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $compra->fecha?->format('d/m/Y') ?? '—' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Nº Factura</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ $compra->numero_factura ?? '—' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Estado</p>
                    @php
                        $estadoBadge = match($compra->estado ?? '') {
                            'pagado' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                            'parcial' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                            'anulado' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100',
                            default => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                        };
                    @endphp
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $estadoBadge }}">{{ $compra->estado ?? 'pendiente' }}</span>
                </div>
            </div>
            @if($compra->notas)
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"><strong>Notas:</strong> {{ $compra->notas }}</p>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Producto</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cant.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">P. unit.</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($compra->detalles as $d)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $d->producto?->nombre ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->cantidad, 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($d->precio_unitario, 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ number_format($d->subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex justify-end">
                <div class="text-right space-y-1">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Subtotal: <span class="font-medium">{{ number_format($compra->subtotal ?? 0, 2, ',', '.') }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Descuento: <span class="font-medium">-{{ number_format($compra->descuento ?? 0, 2, ',', '.') }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Impuesto: <span class="font-medium">+{{ number_format($compra->impuesto ?? 0, 2, ',', '.') }}</span></p>
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Total: {{ number_format($compra->total ?? 0, 2, ',', '.') }}</p>
                    <p class="text-sm {{ $compra->estaPagado() ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }} mt-2">
                        Pagado: <span class="font-medium">{{ number_format($compra->pagado ?? 0, 2, ',', '.') }}</span>
                        @if(!$compra->estaPagado())
                            · Saldo: <span class="font-medium">{{ number_format($compra->saldoPendiente(), 2, ',', '.') }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        @if(!$compra->estaPagado() && $compra->estado !== 'anulado')
        <div class="p-6 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Registrar pago</h3>
            <form action="{{ route('pagos.store') }}" method="POST" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="tipo" value="compra">
                <input type="hidden" name="referencia_id" value="{{ $compra->id }}">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="{{ date('Y-m-d') }}" required
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Monto</label>
                    <input type="number" name="monto" step="0.01" min="0.01" max="{{ $compra->saldoPendiente() }}" value="{{ $compra->saldoPendiente() }}" required
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm w-28">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Método</label>
                    <select name="metodo_pago" required class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="cheque">Cheque</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Ref. pago</label>
                    <input type="text" name="referencia_pago" maxlength="100" placeholder="Nº operación"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm w-32">
                </div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 text-sm">Registrar pago</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
