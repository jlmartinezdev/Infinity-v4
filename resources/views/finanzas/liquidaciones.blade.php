@extends('layouts.app')
@section('title', 'Liquidaciones Bancard')
@section('content')
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', '.'); $cls = 'mt-1 w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm'; @endphp
<div class="max-w-4xl mx-auto">
    @include('finanzas._nav')
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Liquidaciones Bancard</h1>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">El bruto ya entró con el recibo. Acá se descuenta la comisión y el neto se transfiere al banco. Esos importes no vuelven a contarse como ingreso.</p>
    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-lg bg-red-100 text-red-800 text-sm">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('finanzas.liquidaciones.store') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label class="text-sm">Fecha de liquidación</label><input type="date" name="fecha_liquidacion" required value="{{ now()->toDateString() }}" class="{{ $cls }}"></div>
            <div><label class="text-sm">Referencia</label><input name="referencia" required class="{{ $cls }}"></div>
            <div><label class="text-sm">Comisión</label><input name="comision" id="comision" class="{{ $cls }}" placeholder="{{ $comisionSugerida ?? '0' }}"></div>
        </div>
        <p class="text-sm">Bruto seleccionado: <strong id="bruto">0</strong></p>
        <div class="max-h-80 overflow-auto border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="min-w-full text-sm">
                <tbody>
                @forelse($pendientes as $movimiento)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2"><input type="checkbox" name="cobro_ids[]" value="{{ $movimiento->referencia_id }}" data-importe="{{ $movimiento->importe }}" class="cobro-liq"></td>
                        <td class="px-3 py-2">{{ $movimiento->fecha_efectiva?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $movimiento->concepto }}</td>
                        <td class="px-3 py-2 text-right">{{ $fmt($movimiento->importe) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-3 py-6 text-center text-gray-500">No hay cobros Bancard pendientes. Entran cuando la fecha de corte está definida y el recibo se mapea a Bancard.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div><label class="text-sm">Observaciones</label><textarea name="observaciones" class="{{ $cls }}" rows="2"></textarea></div>
        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Liquidar</button>
    </form>
    <div class="mt-6 space-y-2 text-sm">
        @foreach($filas as $fila)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2">{{ $fila->fecha_liquidacion?->format('d/m/Y') }} · {{ $fila->referencia }} · bruto {{ $fmt($fila->bruto) }} · comisión {{ $fmt($fila->comision) }} · neto {{ $fmt($fila->neto) }}</div>
        @endforeach
    </div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('.cobro-liq').forEach((el) => el.addEventListener('change', () => {
    let total = 0;
    document.querySelectorAll('.cobro-liq:checked').forEach((c) => total += Number(c.dataset.importe || 0));
    const bruto = document.getElementById('bruto');
    if (bruto) bruto.textContent = total.toLocaleString('es-PY');
    const comision = document.getElementById('comision');
    const sugerida = @json($comisionSugerida);
    if (comision && comision.value === '' && sugerida) comision.value = Math.round(total * sugerida) / 100;
}));
</script>
@endpush
